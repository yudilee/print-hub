<?php

namespace App\Services;

use App\Models\DataSchema;
use App\Models\PrintTemplate;
use FPDF;

class ContinuousFormEngine
{
    protected $pdf;
    protected $template;
    protected $data;
    protected ?DataSchema $schema = null;
    protected FontService $fontService;
    private array $runningTotals = [];
    private array $runningTotalCounts = [];

    /** @var array Tracks rendered field values for suppress_if_duplicate */
    private array $renderedFieldValues = [];

    /** @var array|null Current conditional style from highlighting rules */
    protected ?array $currentConditionalStyle = null;

    /** @var array|null Sections data from template elements */
    protected ?array $sections = null;

    /** @var array Flat element list extracted from sections or legacy format */
    protected array $flatElements = [];

    const SECTION_ORDER = ['pageHeader', 'reportHeader', 'detail', 'reportFooter', 'pageFooter'];

    /**
     * Generate PDF binary from template and data.
     */
    public function generate(PrintTemplate $template, array $data, array $options = [], array $parameters = [])
    {
        $this->template = $template;
        $this->data = $data;

        // Merge runtime parameters into data so they are accessible
        // as {param_name} in expressions and field lookups.
        if (!empty($parameters)) {
            foreach ($parameters as $key => $value) {
                if (!array_key_exists($key, $this->data)) {
                    $this->data[$key] = $value;
                }
            }
        }
        $this->schema = $template->dataSchema;
        $this->fontService = app(FontService::class);
        $this->initRunningTotals();

        // Parse sections structure from template elements
        $this->parseSections();

        // Determine paper size (priority: options > template)
        $pW = $options['paper_width_mm'] ?? $template->paper_width_mm;
        $pH = $options['paper_height_mm'] ?? $template->paper_height_mm;
        $orientation = (($options['orientation'] ?? 'portrait') === 'landscape') ? 'L' : 'P';

        // Custom paper size in mm [width, height]
        // FPDF(orientation, unit, size)
        $this->pdf = new FPDF($orientation, 'mm', [$pW, $pH]);
        $this->pdf->SetAutoPageBreak(false);

        // Set Margins (priority: options > 0)
        $mT = (float)($options['margin_top'] ?? 0);
        $mB = (float)($options['margin_bottom'] ?? 0);
        $mL = (float)($options['margin_left'] ?? 0);
        $mR = (float)($options['margin_right'] ?? 0);
        $this->pdf->SetMargins($mL, $mT, $mR);

        // ── Eco Mode / Sustainability ─────────────────────────────
        $ecoMode = !empty($options['eco_mode']);
        $grayscaleForce = !empty($options['grayscale_force']) || $ecoMode;
        $pagesPerSheet = (int)($options['pages_per_sheet'] ?? 1);
        $removeImages = !empty($options['remove_images']);
        // ─────────────────────────────────────────────────────────

        // ── Resolve data options (sorting, grouping, filtering) ───
        $dataOptions = $this->getDataOptions();

        if ($this->sections !== null) {
            // Section-based rendering
            $tableElement = $this->findTableInSections();
            $rows = [];
            if ($tableElement) {
                $rows = $this->resolveValue($tableElement['key'], $data) ?: [];
            }

            // Guardrail: row limit
            $maxRows = config('print.max_rows', 10000);
            if (count($rows) > $maxRows) {
                throw new \RuntimeException("PDF generation exceeded row limit of {$maxRows} (got " . count($rows) . " rows).");
            }

            if (empty($rows) || !$tableElement) {
                // Static single page with sections
                $this->renderSectionedPage();
            } else {
                // Apply Sorting & Filtering (grouping is applied inside renderMultipageWithSections)
                $rows = $this->applySorting($rows, $dataOptions['sortFields'] ?? null);
                $rows = $this->applyFilter($rows, $dataOptions['filterExpression'] ?? null);
                // Multipage with sections
                $this->renderMultipageWithSections($tableElement, $rows);
            }
        } else {
            // Legacy flat rendering (backward compatible)
            $elements = $template->elements ?? [];
            $tableElement = collect($elements)->firstWhere('type', 'table');
            $rows = [];
            if ($tableElement) {
                $rows = $this->resolveValue($tableElement['key'], $data) ?: [];
            }

            // Guardrail: row limit
            $maxRows = config('print.max_rows', 10000);
            if (count($rows) > $maxRows) {
                throw new \RuntimeException("PDF generation exceeded row limit of {$maxRows} (got " . count($rows) . " rows).");
            }

            if (empty($rows) || !$tableElement) {
                $this->renderPage();
            } else {
                // Apply Sorting & Filtering
                $rows = $this->applySorting($rows, $dataOptions['sortFields'] ?? null);
                $rows = $this->applyFilter($rows, $dataOptions['filterExpression'] ?? null);
                $this->renderMultipageTable($tableElement, $rows);
            }
        }

        // Apply eco mode transformations after rendering
        if ($ecoMode || $grayscaleForce || $pagesPerSheet > 1 || $removeImages) {
            $this->applyEcoMode($ecoMode, $grayscaleForce, $pagesPerSheet, $removeImages, $options);
        }

        // ── Per-Copy Watermark: Duplicate pages if per-copy configs are set ──
        $watermarkCopies = $options['watermark_copies'] ?? null;
        if (!empty($watermarkCopies) && is_array($watermarkCopies)) {
            // We have N configs for N copies - duplicate the original pages
            $originalPageCount = $this->pdf->n;
            $copyCount = count($watermarkCopies);

            // Only duplicate if we have more copies than 1
            if ($copyCount > 1 && $originalPageCount > 0) {
                for ($c = 1; $c < $copyCount; $c++) {
                    for ($p = 1; $p <= $originalPageCount; $p++) {
                        $this->pdf->n++;
                        $this->pdf->pages[$this->pdf->n] = $this->pdf->pages[$p];
                    }
                }
                // Override copies to 1 since all copies are now embedded in the PDF
                $options['copies'] = 1;
            }
        }
        // ────────────────────────────────────────────────────────────────────

        // Apply watermark if configured
        $this->applyWatermark($options);

        return $this->pdf->Output('S');
    }

    /**
     * Parse sections structure from template elements.
     */
    protected function parseSections(): void
    {
        $elements = $this->template->elements ?? [];

        if (is_array($elements) && isset($elements['sections'])) {
            $this->sections = $elements['sections'];
            $this->flatElements = $elements['elements'] ?? [];

            // Ensure all section keys exist with defaults
            $defaults = [
                'pageHeader' => ['enabled' => true, 'height' => 15, 'elements' => [], 'suppressIfBlank' => false, 'keepWithBody' => false],
                'reportHeader' => ['enabled' => false, 'height' => 20, 'elements' => [], 'suppressIfBlank' => true, 'keepWithBody' => false],
                'detail' => ['enabled' => true, 'height' => 10, 'elements' => [], 'keepTogether' => false],
                'reportFooter' => ['enabled' => false, 'height' => 15, 'elements' => [], 'suppressIfBlank' => true, 'keepWithBody' => false],
                'pageFooter' => ['enabled' => true, 'height' => 10, 'elements' => [], 'suppressIfBlank' => false, 'keepWithBody' => false],
            ];

            foreach (self::SECTION_ORDER as $key) {
                if (!isset($this->sections[$key])) {
                    $this->sections[$key] = $defaults[$key];
                }
                if (!isset($this->sections[$key]['elements'])) {
                    $this->sections[$key]['elements'] = [];
                }
            }
        } else {
            $this->sections = null;
            $this->flatElements = is_array($elements) ? $elements : [];
        }
    }

    /**
     * Find the first table element across all sections.
     */
    protected function findTableInSections(): ?array
    {
        if ($this->sections === null) return null;

        foreach (self::SECTION_ORDER as $key) {
            $sec = $this->sections[$key] ?? [];
            if (!($sec['enabled'] ?? false)) continue;
            foreach ($sec['elements'] ?? [] as $el) {
                if (($el['type'] ?? '') === 'table') return $el;
            }
        }
        return null;
    }

    /**
     * Render a single page with all enabled sections.
     */
    protected function renderSectionedPage(): void
    {
        $this->pdf->AddPage();
        $this->renderBackground();

        $pH = (float)($this->template->paper_height_mm ?? 297.0);
        $pageHeaderH = (!empty($this->sections['pageHeader']['enabled'])) ? (float)($this->sections['pageHeader']['height'] ?? 0) : 0;
        $reportHeaderH = (!empty($this->sections['reportHeader']['enabled'])) ? (float)($this->sections['reportHeader']['height'] ?? 0) : 0;
        $pageFooterH = (!empty($this->sections['pageFooter']['enabled'])) ? (float)($this->sections['pageFooter']['height'] ?? 0) : 0;
        $reportFooterH = (!empty($this->sections['reportFooter']['enabled'])) ? (float)($this->sections['reportFooter']['height'] ?? 0) : 0;

        foreach (self::SECTION_ORDER as $key) {
            $sec = $this->sections[$key] ?? [];
            if (empty($sec['enabled'])) continue;

            $elements = $sec['elements'] ?? [];
            if (!empty($sec['suppressIfBlank']) && empty($elements)) continue;

            $sectionTop = match ($key) {
                'pageHeader' => 0.0,
                'reportHeader' => $pageHeaderH,
                'detail' => $pageHeaderH + $reportHeaderH,
                'reportFooter' => max($pageHeaderH + $reportHeaderH, $pH - $pageFooterH - $reportFooterH),
                'pageFooter' => max($pageHeaderH + $reportHeaderH + $reportFooterH, $pH - $pageFooterH),
                default => 0.0,
            };

            foreach ($elements as $el) {
                if (!empty($el['hidden'])) continue;
                if (($el['type'] ?? '') === 'table') continue;

                $renderedEl = $el;
                $renderedEl['y'] = ($el['y'] ?? 0) + $sectionTop;
                $this->renderSingleElement($renderedEl);
            }
        }
    }

    /**
     * Render multipage output with section headers/footers repeating on each page.
     */
    protected function renderMultipageWithSections(array $tableEl, array $rows): void
    {
        $x = $tableEl['x'] ?? 0;
        $startY = $tableEl['y'] ?? 0;
        $bottomPadding = $tableEl['bottom_padding'] ?? 10;
        $columns = $tableEl['columns'] ?? [];
        $headerHeight = $tableEl['header_height'] ?? 7;
        $rowHeight = $tableEl['row_height'] ?? 6;
        $fontSize = $tableEl['font_size'] ?? 9;
        $pH = $this->template->paper_height_mm;

        // Evaluate computed columns
        $rows = $this->evaluateComputedRows($tableEl, $rows);

        // ── Apply grouping ──────────────────────────────────────
        $dataOptions = $this->getDataOptions();
        $groupFields = $dataOptions['groupFields'] ?? null;
        $hasGrouping = !empty($groupFields);
        if ($hasGrouping) {
            // Auto-sort by group fields so rows are contiguous per group
            $rows = $this->applySorting($rows, $groupFields);
        }
        // ─────────────────────────────────────────────────────────

        // Calculate total section header height (pageHeader + reportHeader)
        $sectionHeaderHeight = 0;
        foreach (self::SECTION_ORDER as $key) {
            if ($key === 'detail') break;
            $sec = $this->sections[$key] ?? [];
            if (!($sec['enabled'] ?? false)) continue;
            $sectionHeaderHeight += ($sec['height'] ?? 10) + 2;
        }

        // Calculate section footer height (reportFooter)
        $sectionFooterHeight = 0;
        $foundDetail = false;
        foreach (self::SECTION_ORDER as $key) {
            if ($key === 'detail') { $foundDetail = true; continue; }
            if (!$foundDetail) continue;
            $sec = $this->sections[$key] ?? [];
            if (!($sec['enabled'] ?? false)) continue;
            if ($key === 'pageFooter') continue; // pageFooter handled separately
            $sectionFooterHeight += ($sec['height'] ?? 10) + 2;
        }

        // Page footer height
        $pageFooterH = 0;
        $pfSec = $this->sections['pageFooter'] ?? [];
        if ($pfSec['enabled'] ?? false) {
            $pageFooterH = ($pfSec['height'] ?? 10) + 2;
        }

        // First page: render page header + report header
        $this->startNewSectionedPage($sectionHeaderHeight);

        // Calculate available height for detail rows
        $availableH = $pH - $startY - $bottomPadding - $sectionFooterHeight - $pageFooterH;

        // Render table header in the detail section
        $currentY = $startY;
        $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize, $tableEl);
        $currentY += $headerHeight;

        $rowIndex = 0;
        $totalRows = count($rows);

        // Track previous group values for group header detection
        $previousGroupValues = $hasGrouping ? array_fill_keys(
            array_map(fn($gf) => $gf['field'] ?? '', $groupFields),
            null
        ) : [];

        while ($rowIndex < $totalRows) {
            // Guardrail: Page count limit
            $maxPages = config('print.max_pages', 200);
            if ($this->pdf->PageNo() > $maxPages) {
                throw new \RuntimeException("PDF generation exceeded page limit of {$maxPages}.");
            }

            // ── Group header detection ──────────────────────────
            if ($hasGrouping) {
                $groupChanged = false;
                $currentGroupValues = [];

                foreach ($groupFields as $gf) {
                    $field = $gf['field'] ?? '';
                    $value = $this->resolveValue($field, $rows[$rowIndex]);
                    $currentGroupValues[$field] = $value;

                    if ($previousGroupValues[$field] !== $value) {
                        $groupChanged = true;
                        // When one field changes, the rest are implicitly new too
                        break;
                    }
                }

                if ($groupChanged && $rowIndex > 0) {
                    // Reset running totals at group boundary
                    $this->resetRunningTotalsOnPage();

                    // Calculate total width of all columns for the group header cell
                    $totalWidth = array_sum(array_map(fn($c) => $c['width'] ?? 0, $columns));

                    // Check if group header fits on current page
                    $groupHeaderHeight = $rowHeight;
                    if ($currentY + $groupHeaderHeight > $availableH) {
                        $this->renderReportFooterOnPage($currentY);
                        $this->startNewSectionedPage($sectionHeaderHeight);
                        $currentY = $startY;
                        $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize, $tableEl);
                        $currentY += $headerHeight;
                    }

                    // Render group header row (bold, full-width, with group field name: value)
                    $firstField = $groupFields[0]['field'] ?? '';
                    $firstValue = $currentGroupValues[$firstField] ?? '';
                    $groupLabel = ucwords(str_replace('_', ' ', $firstField)) . ': ' . (string)$firstValue;

                    // Use bold font for group header
                    $fontFamily = $tableEl['fontFamily'] ?? 'Arial';
                    $resolvedFamily = $this->fontService->loadFontForPdf($this->pdf, $fontFamily, 'B');
                    $this->pdf->SetFont($resolvedFamily, 'B', $fontSize);

                    // Light background for group header
                    $this->pdf->SetFillColor(230, 240, 255);
                    $this->pdf->SetXY($x, $currentY);
                    $this->pdf->Cell($totalWidth, $groupHeaderHeight, '  ' . $groupLabel, 1, 0, 'L', true);
                    $currentY += $groupHeaderHeight;

                    // Re-render column headers after group header for clarity
                    $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize, $tableEl);
                    $currentY += $headerHeight;
                }

                $previousGroupValues = $currentGroupValues;
            }
            // ─────────────────────────────────────────────────────

            // Check if we need a page break
            if ($currentY + $rowHeight > $availableH) {
                // Render report footer on current page before break
                $this->renderReportFooterOnPage($currentY);

                // Start new page
                $this->startNewSectionedPage($sectionHeaderHeight);
                $currentY = $startY;

                // Re-render table header
                $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize, $tableEl);
                $currentY += $headerHeight;
            }

            // Render data row
            $this->renderTableRow($x, $currentY, $columns, $rows[$rowIndex], $rowHeight, $fontSize);
            $currentY += $rowHeight;

            // Accumulate running totals with group field info
            $groupField = $hasGrouping ? ($groupFields[0]['field'] ?? null) : null;
            $this->accumulateRunningTotals($rows[$rowIndex], $groupField);
            $rowIndex++;
        }

        // Render report footer after all detail rows
        $this->renderReportFooterOnPage($currentY);

        // Render page footer on last page
        $this->renderPageFooterOnPage();
    }

    /**
     * Start a new page and render repeating sections (pageHeader, reportHeader, pageFooter).
     */
    protected function startNewSectionedPage(float $sectionHeaderHeight): void
    {
        $this->resetRunningTotalsOnPage();
        $this->pdf->AddPage();
        $this->renderBackground();

        $currentY = 0;
        foreach (self::SECTION_ORDER as $key) {
            if ($key === 'detail') break;
            $sec = $this->sections[$key] ?? [];
            if (!($sec['enabled'] ?? false)) continue;

            $elements = $sec['elements'] ?? [];
            if (!empty($sec['suppressIfBlank']) && empty($elements)) continue;

            foreach ($elements as $el) {
                if (!empty($el['hidden'])) continue;
                $renderedEl = $el;
                $renderedEl['y'] = ($el['y'] ?? 0) + $currentY;
                $this->renderSingleElement($renderedEl);
            }

            $currentY += ($sec['height'] ?? 10) + 2;
        }

        // Page footer is rendered at the bottom - store for later use
        $this->currentPageFooterY = $currentY;
    }

    protected float $currentPageFooterY = 0;

    /**
     * Render report footer section elements.
     */
    protected function renderReportFooterOnPage(float &$currentY): void
    {
        foreach (self::SECTION_ORDER as $key) {
            if ($key === 'reportFooter') {
                $sec = $this->sections[$key] ?? [];
                if (!($sec['enabled'] ?? false)) break;

                $elements = $sec['elements'] ?? [];
                if (!empty($sec['suppressIfBlank']) && empty($elements)) break;

                foreach ($elements as $el) {
                    if (!empty($el['hidden'])) continue;
                    $renderedEl = $el;
                    $renderedEl['y'] = ($el['y'] ?? 0) + $currentY;
                    $this->renderSingleElement($renderedEl);
                }
                $currentY += ($sec['height'] ?? 10) + 2;
                break;
            }
        }
    }

    /**
     * Render page footer section at the bottom of the page.
     */
    protected function renderPageFooterOnPage(): void
    {
        $sec = $this->sections['pageFooter'] ?? [];
        if (!($sec['enabled'] ?? false)) return;

        $elements = $sec['elements'] ?? [];
        if (!empty($sec['suppressIfBlank']) && empty($elements)) return;

        $pH = $this->template->paper_height_mm;
        $footerY = $pH - ($sec['height'] ?? 10) - 2;

        foreach ($elements as $el) {
            if (!empty($el['hidden'])) continue;
            $renderedEl = $el;
            $renderedEl['y'] = ($el['y'] ?? 0) + $footerY;
            $this->renderSingleElement($renderedEl);
        }
    }

    /**
     * Render a single element based on its type.
     */
    protected function renderSingleElement(array $el): void
    {
        $type = $el['type'] ?? '';
        switch ($type) {
            case 'field':
                $this->renderField($el);
                break;
            case 'label':
                $this->renderLabel($el);
                break;
            case 'line':
                $this->renderLine($el);
                break;
            case 'image':
                $this->renderImage($el);
                break;
            case 'barcode':
                $this->renderBarcode($el);
                break;
            case 'qrcode':
                $this->renderQrCode($el);
                break;
            case 'running_total':
                $this->renderRunningTotal($el);
                break;
        }
    }

    protected function renderPage()
    {
        $this->pdf->AddPage();
        
        $this->renderBackground();

        // Render all non-table elements (static headers/footers)
        $elements = $this->template->elements ?? [];
        foreach ($elements as $el) {
            if (!empty($el['hidden'])) continue;
            if ($el['type'] === 'field') {
                $this->renderField($el);
            } elseif ($el['type'] === 'label') {
                $this->renderLabel($el);
            } elseif ($el['type'] === 'line') {
                $this->renderLine($el);
            } elseif ($el['type'] === 'image') {
                $this->renderImage($el);
            } elseif ($el['type'] === 'barcode') {
                $this->renderBarcode($el);
            } elseif ($el['type'] === 'qrcode') {
                $this->renderQrCode($el);
            } elseif ($el['type'] === 'running_total') {
                $this->renderRunningTotal($el);
            }
        }
    }

    protected function renderBackground()
    {
        $config = $this->template->background_config ?? [];
        $isPrinted = $config['is_printed'] ?? false;
        $path = $this->template->background_image_path;

        if ($isPrinted && $path) {
            $localPath = null;
            if (str_contains($path, 'storage/')) {
                $relative = explode('storage/', $path)[1];
                $localPath = storage_path('app/public/' . $relative);
            } else {
                $localPath = storage_path('app/public/' . $path);
            }

            if (file_exists($localPath)) {
                $this->pdf->Image($localPath, 0, 0, $this->template->paper_width_mm, $this->template->paper_height_mm);
            }
        }
    }

    protected function resetRunningTotalsOnPage(): void
    {
        $elements = $this->template->elements ?? [];
        foreach ($elements as $el) {
            if (($el['type'] ?? '') !== 'running_total') continue;
            if (($el['reset'] ?? 'never') === 'on_page') {
                $field = $el['field'] ?? '';
                $operation = $el['operation'] ?? 'sum';
                $key = $field . '_' . $operation;
                $this->runningTotals[$key] = 0;
                $this->runningTotalCounts[$key] = 0;
            }
        }
    }

    protected function renderMultipageTable($el, $rows)
    {
        $x = $el['x'] ?? 0;
        $startY = $el['y'] ?? 0;
        $bottomPadding = $el['bottom_padding'] ?? 10;
        $columns = $el['columns'] ?? [];
        $headerHeight = $el['header_height'] ?? 7;
        $rowHeight = $el['row_height'] ?? 6;
        $fontSize = $el['font_size'] ?? 9;

        // Evaluate computed columns
        $rows = $this->evaluateComputedRows($el, $rows);

        $currentY = $startY;
        $this->renderPage();
        
        // Render Header on first page
        $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize, $el);
        $currentY += $headerHeight;

        foreach ($rows as $index => $rowData) {
            // Guardrail: Page count limit
            $maxPages = config('print.max_pages', 200);
            if ($this->pdf->PageNo() > $maxPages) {
                throw new \RuntimeException("PDF generation exceeded page limit of {$maxPages}.");
            }

            // Page break check
            if ($currentY + $rowHeight > ($this->template->paper_height_mm - $bottomPadding)) {
                $this->resetRunningTotalsOnPage();
                $this->renderPage();
                $currentY = $startY;
                $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize, $el);
                $currentY += $headerHeight;
            }

            $this->renderTableRow($x, $currentY, $columns, $rowData, $rowHeight, $fontSize);
            $currentY += $rowHeight;

            // Accumulate running totals after each data row
            $this->accumulateRunningTotals($rowData);
        }
    }

    protected function renderField($el)
    {
        // Check print_when expression — skip if expression evaluates to false
        if (!empty($el['print_when'])) {
            $printResult = $this->evaluateExpression($el['print_when'], $this->data);
            if (empty($printResult) || $printResult === '0' || $printResult === 0 || $printResult === false || $printResult === 'false') {
                return;
            }
        }

        $this->applyRotation($el);
        $el = $this->applyStyle($el);
        $value = $this->resolveValue($el['key'], $this->data);
        if ($value === null) { $this->resetRotation($el); return; }

        // Check suppress_if_duplicate — skip if same value was already rendered for this field key
        if (!empty($el['suppress_if_duplicate'])) {
            $dupKey = $el['key'] . '|' . (string) $value;
            if (isset($this->renderedFieldValues[$dupKey])) {
                $this->resetRotation($el);
                return;
            }
            $this->renderedFieldValues[$dupKey] = true;
        }

        // Apply formatting (manual override or schema)
        $value = $this->formatValue($el, $value);

        // Evaluate conditional formatting for this field
        $this->currentConditionalStyle = $this->getConditionalStyle($el, $value);

        $this->renderTextCell($el, (string) $value);

        // Reset conditional style after rendering
        $this->currentConditionalStyle = null;

        $this->resetRotation($el);
    }

    protected function renderLabel($el)
    {
        // Check print_when expression — skip if expression evaluates to false
        if (!empty($el['print_when'])) {
            $printResult = $this->evaluateExpression($el['print_when'], $this->data);
            if (empty($printResult) || $printResult === '0' || $printResult === 0 || $printResult === false || $printResult === 'false') {
                return;
            }
        }

        $this->applyRotation($el);
        $el = $this->applyStyle($el);
        $text = $el['text'] ?? '';
        if ($text === '') { $this->resetRotation($el); return; }

        $this->renderTextCell($el, $text);
        $this->resetRotation($el);
    }

    protected function renderLine($el)
    {
        $x = (float) ($el['x'] ?? 0);
        $y = (float) ($el['y'] ?? 0);
        $width = (float) ($el['width'] ?? 10);
        $lineColor = $el['lineColor'] ?? '#000000';

        $r = hexdec(substr(ltrim($lineColor, '#'), 0, 2));
        $g = hexdec(substr(ltrim($lineColor, '#'), 2, 2));
        $b = hexdec(substr(ltrim($lineColor, '#'), 4, 2));

        $this->pdf->SetDrawColor($r, $g, $b);
        $this->pdf->SetLineWidth((float) ($el['height'] ?? 0.3));
        $this->pdf->Line($x, $y, $x + $width, $y);
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.2);
    }

    protected function renderImage($el)
    {
        $this->applyRotation($el);
        $x = (float) ($el['x'] ?? 0);
        $y = (float) ($el['y'] ?? 0);
        $w = (float) ($el['width'] ?? 0);
        $h = (float) ($el['height'] ?? 0);
        
        $src = $el['src'] ?? null;
        
        // If image has a data key, resolve it dynamically
        if (!empty($el['key'])) {
            $dynamicSrc = $this->resolveValue($el['key'], $this->data);
            if ($dynamicSrc) $src = $dynamicSrc;
        }

        if (!$src) { $this->resetRotation($el); return; }

        try {
            // FPDF Image($file, $x, $y, $w, $h, $type, $link)
            // If w or h is 0, it is automatically calculated from the image properties
            $this->pdf->Image($src, $x, $y, $w, $h);
        } catch (\Exception $e) {
            // Log or skip if image not found/invalid
            \Illuminate\Support\Facades\Log::warning("PDF Engine: Image render failed for {$src}. " . $e->getMessage());
        }
        $this->resetRotation($el);
    }

    protected function renderBarcode($el)
    {
        $barcodeService = app(BarcodeService::class);
        $rawValue = $el['value'] ?? '';
        $value = $this->resolveValue($rawValue, $this->currentData ?? []);
        if ($value === null || $value === '') {
            $value = $rawValue;
        }
        if (empty($value)) return;

        $base64 = $barcodeService->renderBarcode(
            $value,
            $el['symbology'] ?? 'code128',
            intval(($el['width'] ?? 80) * 3.78),  // mm to pixels at ~96dpi
            intval(($el['height_mm'] ?? 20) * 3.78)
        );

        // Decode base64 and save to temp file
        $imageData = base64_decode(explode(',', $base64)[1]);
        if ($imageData === false) return;
        $tempPath = tempnam(sys_get_temp_dir(), 'barcode_') . '.png';
        file_put_contents($tempPath, $imageData);

        try {
            // Place in PDF
            $this->pdf->Image($tempPath, (float)($el['x'] ?? 0), (float)($el['y'] ?? 0), (float)($el['width'] ?? 80), (float)($el['height_mm'] ?? 20));
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        // Show human-readable text if enabled
        if (!empty($el['showText'])) {
            $labelY = (float)($el['y'] ?? 0) + (float)($el['height_mm'] ?? 20) + 1;
            $this->pdf->SetXY((float)($el['x'] ?? 0), $labelY);
            $this->pdf->SetFont('Arial', '', 8);
            $this->pdf->Cell((float)($el['width'] ?? 80), 4, $value, 0, 0, 'C');
        }
    }

    protected function renderQrCode($el)
    {
        $barcodeService = app(BarcodeService::class);
        $rawValue = $el['value'] ?? '';
        $value = $this->resolveValue($rawValue, $this->currentData ?? []);
        if ($value === null || $value === '') {
            $value = $rawValue;
        }
        if (empty($value)) return;

        $base64 = $barcodeService->renderQrCode(
            $value,
            intval(($el['size'] ?? 25) * 10),  // Larger size for QR detail
            $el['errorCorrection'] ?? 'M'
        );

        $imageData = base64_decode(explode(',', $base64)[1]);
        if ($imageData === false) return;
        $tempPath = tempnam(sys_get_temp_dir(), 'qrcode_') . '.png';
        file_put_contents($tempPath, $imageData);

        try {
            $size = (float)($el['size'] ?? 25);
            $this->pdf->Image($tempPath, (float)($el['x'] ?? 0), (float)($el['y'] ?? 0), $size, $size);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    // ── Rotation ──────────────────────────────────────────────

    protected function applyRotation($el): void
    {
        if (!empty($el['rotation']) && $el['rotation'] != 0) {
            $angle = floatval($el['rotation']);
            $x = $el['x'] + ($el['width'] ?? 0) / 2;
            $y = $el['y'] + ($el['height'] ?? 0) / 2;
            $this->pdf->_out(sprintf(
                'q %.4f %.4f %.4f %.4f %.4f %.4f cm',
                cos($angle * M_PI / 180),
                sin($angle * M_PI / 180),
                -sin($angle * M_PI / 180),
                cos($angle * M_PI / 180),
                $x * $this->pdf->k,
                ($this->pdf->h - $y) * $this->pdf->k
            ));
        }
    }

    protected function resetRotation($el): void
    {
        if (!empty($el['rotation']) && $el['rotation'] != 0) {
            $this->pdf->_out('Q');
        }
    }

    // ── Running Totals ────────────────────────────────────────

    protected function initRunningTotals(): void
    {
        $this->runningTotals = [];
        $this->runningTotalCounts = [];
    }

    protected function renderRunningTotal($el): void
    {
        $field = $el['field'] ?? '';
        $operation = $el['operation'] ?? 'sum';
        $key = $field . '_' . $operation;

        // Calculate current value
        $value = $this->runningTotals[$key] ?? 0;

        // Format and display
        $formatted = $this->formatValue($el, $value);
        $this->pdf->SetFont($el['fontFamily'] ?? 'Arial', '', $el['fontSize'] ?? 10);
        $this->pdf->SetXY($el['x'], $el['y']);
        $this->pdf->Cell($el['width'], $el['height'], $formatted, 0, 0, 'L');
    }

    protected function accumulateRunningTotals(array $rowData, string $groupField = null): void
    {
        $elements = $this->template->elements ?? [];
        foreach ($elements as $el) {
            if (($el['type'] ?? '') !== 'running_total') continue;

            $field = $el['field'] ?? '';
            $operation = $el['operation'] ?? 'sum';
            $reset = $el['reset'] ?? 'never';
            $key = $field . '_' . $operation;

            if (!isset($this->runningTotals[$key])) {
                $this->runningTotals[$key] = 0;
                $this->runningTotalCounts[$key] = 0;
            }

            // Reset if group changed
            if ($reset === 'on_group' && !empty($el['resetGroup'])) {
                $currentGroup = $rowData[$el['resetGroup']] ?? null;
                static $lastGroup = null;
                if ($lastGroup !== null && $lastGroup !== $currentGroup) {
                    $this->runningTotals[$key] = 0;
                    $this->runningTotalCounts[$key] = 0;
                }
                $lastGroup = $currentGroup;
            }

            // Accumulate
            $fieldValue = $rowData[$field] ?? 0;
            $this->runningTotalCounts[$key]++;
            switch ($operation) {
                case 'sum':
                    $this->runningTotals[$key] += $fieldValue;
                    break;
                case 'count':
                    $this->runningTotals[$key] = $this->runningTotalCounts[$key];
                    break;
                case 'min':
                    if ($this->runningTotalCounts[$key] === 1) {
                        $this->runningTotals[$key] = $fieldValue;
                    } else {
                        $this->runningTotals[$key] = min($this->runningTotals[$key], $fieldValue);
                    }
                    break;
                case 'max':
                    if ($this->runningTotalCounts[$key] === 1) {
                        $this->runningTotals[$key] = $fieldValue;
                    } else {
                        $this->runningTotals[$key] = max($this->runningTotals[$key], $fieldValue);
                    }
                    break;
                case 'average':
                    // Running average: prevAvg + (newValue - prevAvg) / count
                    $prevAvg = $this->runningTotals[$key];
                    $this->runningTotals[$key] = $prevAvg + ($fieldValue - $prevAvg) / $this->runningTotalCounts[$key];
                    break;
            }

            // Reset on new page — flag for page break handling
            if ($reset === 'on_page') {
                // Reset is triggered via resetRunningTotalsOnPage() at page breaks
            }
        }
    }

    // ── Text Cell ─────────────────────────────────────────────

    protected function renderTextCell($el, string $value)
    {
        $x = $el['x'] ?? 0;
        $y = $el['y'] ?? 0;
        $width = $el['width'] ?? 0;
        $height = $el['height'] ?? 0;
        $fontSize = $el['font_size'] ?? 10;
        $align = $el['align'] ?? 'L';
        $bold = !empty($el['bold']) ? 'B' : '';
        $fill = false;

        // ── Border Style ──────────────────────────────────────
        // 'border' can be: bool (legacy), 'none', 'solid', 'dashed', 'dotted'
        $borderStyle = $el['border'] ?? null;
        if ($borderStyle === true || $borderStyle === 1 || $borderStyle === 'true') {
            $borderStyle = 'solid';
        } elseif ($borderStyle === false || $borderStyle === 0 || $borderStyle === 'false' || $borderStyle === 'none' || $borderStyle === null) {
            $borderStyle = 'none';
        }
        $border = ($borderStyle !== 'none') ? 1 : 0;

        // ── Padding ───────────────────────────────────────────
        $padding = $el['padding'] ?? null;
        $padTop = 0;
        $padRight = 0;
        $padBottom = 0;
        $padLeft = 0;
        if (is_array($padding)) {
            $padTop    = (float)($padding['top'] ?? 0);
            $padRight  = (float)($padding['right'] ?? 0);
            $padBottom = (float)($padding['bottom'] ?? 0);
            $padLeft   = (float)($padding['left'] ?? 0);
        }

        // ── Opacity ───────────────────────────────────────────
        $opacity = $el['opacity'] ?? null;
        $hasOpacity = ($opacity !== null && $opacity >= 0 && $opacity < 100);
        if ($hasOpacity) {
            $alpha = max(0, min(1, (float)$opacity / 100));
            $this->pdf->SetAlpha($alpha);
        }

        // Apply conditional formatting style if available
        $condStyle = $this->currentConditionalStyle;
        if ($condStyle) {
            if (!empty($condStyle['color'])) {
                $hex = ltrim($condStyle['color'], '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $this->pdf->SetTextColor($r, $g, $b);
            }
            if (!empty($condStyle['backgroundColor'])) {
                $hex = ltrim($condStyle['backgroundColor'], '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $this->pdf->SetFillColor($r, $g, $b);
                $fill = true;
            }
            if (!empty($condStyle['bold'])) {
                $bold = 'B';
            }
            if (!empty($condStyle['italic'])) {
                $bold .= 'I';
            }
            if (!empty($condStyle['underline'])) {
                $bold .= 'U';
            }
        }

        // ── Draw custom border (dashed/dotted) ────────────────
        // FPDF native borders only support solid; draw dashed/dotted manually
        if ($borderStyle === 'dashed' || $borderStyle === 'dotted') {
            $this->drawCustomBorder($x, $y, $width, $height, $borderStyle);
            $border = 0; // Don't use FPDF's border since we drew our own
        }

        // ── Hyperlink ─────────────────────────────────────────
        $link = $this->resolveLink($el);

        // Resolve custom font if specified; fallback to Arial
        $fontFamily = $el['fontFamily'] ?? 'Arial';
        $resolvedFamily = $this->fontService->loadFontForPdf($this->pdf, $fontFamily, $bold);
        $this->pdf->SetFont($resolvedFamily, $bold, $fontSize);

        // Apply padding offsets to position
        $cellX = $x + $padLeft;
        $cellY = $y + $padTop;
        $cellWidth = $width - $padLeft - $padRight;
        $this->pdf->SetXY($cellX, $cellY);
        
        $lineH = $fontSize * 0.5;
        $isMultiLine = !empty($el['multiline']) || str_contains($value, "\n");

        if ($isMultiLine && $cellWidth > 0) {
            // MultiCell doesn't accept link param natively; use Link() after rendering
            $this->pdf->MultiCell($cellWidth, $lineH, $value, $border, $align, $fill);
            if ($link) {
                $lineCount = max(1, ceil($this->pdf->GetStringWidth($value) / max($cellWidth, 1)));
                $renderedH = $lineCount * $lineH;
                $this->pdf->Link($cellX, $cellY, $cellWidth, $renderedH, $link);
            }
        } else {
            $this->pdf->Cell($cellWidth > 0 ? $cellWidth : 0, $lineH, $value, $border, 0, $align, $fill, $link);
        }

        // Reset text color to default (black) after rendering
        if ($condStyle && !empty($condStyle['color'])) {
            $this->pdf->SetTextColor(0, 0, 0);
        }
        // Reset fill color to default (black) after rendering
        if ($condStyle && !empty($condStyle['backgroundColor'])) {
            $this->pdf->SetFillColor(0, 0, 0);
        }

        // Reset opacity
        if ($hasOpacity) {
            $this->pdf->SetAlpha(1);
        }
    }

    protected function applyStyle($el)
    {
        if (isset($el['styleIdx']) && isset($this->template->styles[$el['styleIdx']])) {
            $style = $this->template->styles[$el['styleIdx']];
            $el['font_size'] = $style['font_size'] ?? $el['font_size'];
            $el['bold'] = $style['bold'] ?? $el['bold'];
            $el['fontFamily'] = $style['fontFamily'] ?? $el['fontFamily'] ?? 'Arial';
        }
        return $el;
    }

    protected function renderTableHeader($x, $y, $columns, $height, $fontSize, $el = [])
    {
        $headerBgColor = $el['header_bg_color'] ?? null;

        // Resolve custom font for table header; default to Arial
        $fontFamily = $el['fontFamily'] ?? 'Arial';
        $resolvedFamily = $this->fontService->loadFontForPdf($this->pdf, $fontFamily, 'B');
        $this->pdf->SetFont($resolvedFamily, 'B', $fontSize);
        $currentX = $x;
        foreach ($columns as $col) {
            // Header background color
            if ($headerBgColor) {
                $this->setFillColorHex($headerBgColor);
                $this->pdf->SetXY($currentX, $y);
                $border = !empty($col['show_border']) ? 1 : 0;
                $this->pdf->Cell($col['width'], $height, $col['label'], $border, 0, 'C', true);
            } else {
                $this->pdf->SetXY($currentX, $y);
                $border = !empty($col['show_border']) ? 1 : 0;
                $this->pdf->Cell($col['width'], $height, $col['label'], $border, 0, 'C');
            }
            $currentX += $col['width'];
        }
    }

    protected function renderTableRow($x, $y, $columns, $rowData, $height, $fontSize)
    {
        // Use Arial for table row data; columns don't carry fontFamily individually
        $this->pdf->SetFont('Arial', '', $fontSize);
        $currentX = $x;
        foreach ($columns as $col) {
            $val = $this->resolveValue($col['key'], $rowData);
            $align = $col['align'] ?? 'L';
            $border = !empty($col['show_border']) ? 1 : 0; 

            // Apply column-level formatting
            $val = $this->formatTableColumnValue($col, $val);
            
            $this->pdf->SetXY($currentX, $y);
            $this->pdf->Cell($col['width'], $height, $val, $border, 0, $align);
            $currentX += $col['width'];
        }
    }

    // ── Formatting ──────────────────────────────────────────

    /**
     * Format a field value using manual settings or schema metadata.
     */
    protected function formatValue(array $el, $value): string
    {
        if ($value === null) return '';

        // Check for manual formatting override from designer
        $manualType   = $el['format_type'] ?? null;
        $manualFormat = $el['format_string'] ?? null;
        
        if ($manualType && $manualType !== 'none') {
            // Recast format_string if currency
            $meta = $el;
            if ($manualType === 'currency') $meta['currency_code'] = $manualFormat;
            
            return DataSchema::applyFormat($value, $manualType, $manualFormat, $meta);
        }

        if (!$this->schema) {
            return (string) $value;
        }

        return $this->schema->formatFieldValue($el['key'], $value);
    }

    /**
     * Format a table column value using schema metadata or column-level format hint.
     */
    protected function formatTableColumnValue(array $col, $value): string
    {
        if ($value === null) return '';

        // Check column-level manual formatting override from designer
        $manualType   = $col['format_type'] ?? null;
        $manualFormat = $col['format_string'] ?? null;

        if ($manualType && $manualType !== 'none') {
            // Recast if currency
            $meta = $col;
            if ($manualType === 'currency') $meta['currency_code'] = $manualFormat;
            
            return DataSchema::applyFormat($value, $manualType, $manualFormat, $meta);
        }

        // Backward compatibility check for old 'format'/'type' keys
        $type   = $col['type'] ?? null;
        $format = $col['format'] ?? null;
        if ($type && $format && $type !== 'string') {
            return DataSchema::applyFormat($value, $type, $format, $col);
        }

        // If we have a schema, try to resolve from the table's column metadata
        if ($this->schema) {
            $tableKey = null;
            $elements = $this->template->elements ?? [];
            foreach ($elements as $el) {
                if (($el['type'] ?? '') === 'table') {
                    foreach ($el['columns'] ?? [] as $c) {
                        if ($c['key'] === $col['key']) {
                            $tableKey = $el['key'];
                            break 2;
                        }
                    }
                }
            }

            if ($tableKey && isset($this->schema->tables[$tableKey]['columns'][$col['key']])) {
                $colMeta = $this->schema->tables[$tableKey]['columns'][$col['key']];
                $colType   = $colMeta['type'] ?? 'string';
                $colFormat = $colMeta['format'] ?? null;
                if ($colType !== 'string' || $colFormat) {
                    return DataSchema::applyFormat($value, $colType, $colFormat, $colMeta);
                }
            }
        }

        return (string) $value;
    }

    // ── Computed Columns ─────────────────────────────────────

    /**
     * Evaluate computed column expressions for each row.
     * Supports simple expressions like "qty * unit_price"
     */
    protected function evaluateComputedRows(array $el, array $rows): array
    {
        $columns = $el['columns'] ?? [];
        $computedCols = [];

        foreach ($columns as $col) {
            if (!empty($col['computed'])) {
                $computedCols[$col['key']] = $col['computed'];
            }
        }

        // Also check schema for computed column definitions
        if ($this->schema && isset($this->schema->tables[$el['key']]['columns'])) {
            foreach ($this->schema->tables[$el['key']]['columns'] as $colKey => $colMeta) {
                if (!empty($colMeta['computed']) && !isset($computedCols[$colKey])) {
                    $computedCols[$colKey] = $colMeta['computed'];
                }
            }
        }

        if (empty($computedCols)) {
            return $rows;
        }

        return array_map(function ($row) use ($computedCols) {
            foreach ($computedCols as $colKey => $expression) {
                // Only compute if value is not already provided by client
                if (!isset($row[$colKey]) || $row[$colKey] === null || $row[$colKey] === '') {
                    $row[$colKey] = $this->evaluateExpression($expression, $row);
                }
            }
            return $row;
        }, $rows);
    }

    protected function evaluateExpression(string $expression, array $rowData)
    {
        // ── Resolve {param_name} references from the full data set ──
        // Parameters injected at the template level may not be in $rowData,
        // so we look them up from $this->data as a fallback.
        $expression = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_.]*)\}/', function ($m) {
            $key = $m[1];
            $val = $this->resolveValue($key, $this->data);
            if (is_numeric($val)) {
                return (string) $val;
            }
            // Non-numeric values are replaced with a quoted string or empty
            return $val !== null ? ('"' . addslashes((string) $val) . '"') : '""';
        }, $expression);

        // Try the FormulaService first (supports custom functions like SUM, AVG, etc.)
        try {
            /** @var \App\Services\FormulaService $formulaService */
            $formulaService = app(FormulaService::class);
            return $formulaService->evaluate($expression, $rowData);
        } catch (\Exception $e) {
            // Fallback: replace column references with their numeric values
            $resolved = preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_.]*)\b/', function ($m) use ($rowData) {
                $key = $m[1];
                $val = $this->resolveValue($key, $rowData);
                return is_numeric($val) ? (string) $val : '0';
            }, $expression);

            // Safety: only allow numbers and basic operators
            if (!preg_match('/^[\d\s\.\+\-\*\/\(\)]+$/', $resolved)) {
                return 0;
            }

            try {
                $el = new \Symfony\Component\ExpressionLanguage\ExpressionLanguage();
                return $el->evaluate($resolved);
            } catch (\Throwable $e) {
                return 0;
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────

    protected function resolveValue($key, $data)
    {
        if ($key === null || $key === '') return '';
        // Support syntax like items[0].name or items.0.name
        $normalizedKey = str_replace(['[', ']'], ['.', ''], $key);
        $keys = explode('.', $normalizedKey);
        $val = $data;

        foreach ($keys as $k) {
            if ($k === '') continue;
            if (is_array($val)) {
                if (array_key_exists($k, $val)) {
                    $val = $val[$k];
                } elseif (is_numeric($k) && isset($val[(int)$k])) {
                    $val = $val[(int)$k];
                } else {
                    return null;
                }
            } elseif (is_object($val)) {
                if (isset($val->$k)) {
                    $val = $val->$k;
                } elseif (method_exists($val, $k)) {
                    $val = $val->$k();
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $val;
    }

    /**
     * Set fill color from hex string.
     */
    protected function setFillColorHex(string $hex): void
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $this->pdf->SetFillColor($r, $g, $b);
    }

    // ── Conditional Formatting ────────────────────────────────

    /**
     * Evaluate conditional formatting rules for an element and return the first matching style.
     */
    protected function getConditionalStyle(array $el, $value): ?array
    {
        if (empty($el['conditionalFormats'])) {
            return null;
        }

        foreach ($el['conditionalFormats'] as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }

            // Determine the field value to test
            $ruleField = $rule['field'] ?? ($el['field'] ?? '');
            $fieldValue = $value;

            // If rule specifies a different field than the element's field, resolve it from data
            if (!empty($rule['field']) && $rule['field'] !== ($el['field'] ?? $el['key'] ?? '')) {
                $fieldValue = $this->resolveValue($rule['field'], $this->data);
            } elseif (!empty($rule['field']) && $rule['field'] === ($el['field'] ?? $el['key'] ?? '')) {
                // Same field — use the already-resolved value
                $fieldValue = $value;
            }

            $matched = $this->evaluateCondition(
                $rule['operator'] ?? 'equals',
                $fieldValue,
                $rule['value'] ?? '',
                $rule['value2'] ?? null
            );

            if ($matched) {
                return $rule['style'] ?? null;
            }
        }

        return null;
    }

    /**
     * Evaluate a single conditional operator against field and compare values.
     */
    protected function evaluateCondition(string $operator, $fieldValue, $compareValue, $compareValue2 = null): bool
    {
        return match ($operator) {
            'equals' => (string) $fieldValue === (string) $compareValue,
            'not_equals' => (string) $fieldValue !== (string) $compareValue,
            'greater_than' => is_numeric($fieldValue) && is_numeric($compareValue) && floatval($fieldValue) > floatval($compareValue),
            'less_than' => is_numeric($fieldValue) && is_numeric($compareValue) && floatval($fieldValue) < floatval($compareValue),
            'greater_equal' => is_numeric($fieldValue) && is_numeric($compareValue) && floatval($fieldValue) >= floatval($compareValue),
            'less_equal' => is_numeric($fieldValue) && is_numeric($compareValue) && floatval($fieldValue) <= floatval($compareValue),
            'between' => is_numeric($fieldValue) && is_numeric($compareValue) && is_numeric($compareValue2)
                && floatval($fieldValue) >= floatval($compareValue) && floatval($fieldValue) <= floatval($compareValue2),
            'contains' => str_contains((string) $fieldValue, (string) $compareValue),
            'starts_with' => str_starts_with((string) $fieldValue, (string) $compareValue),
            'ends_with' => str_ends_with((string) $fieldValue, (string) $compareValue),
            'is_null' => $fieldValue === null || $fieldValue === '' || $fieldValue === [],
            'is_not_null' => $fieldValue !== null && $fieldValue !== '' && $fieldValue !== [],
            default => false,
        };
    }

    // ── Watermarking ─────────────────────────────────────────

    /**
     * Apply watermark to all pages of the PDF.
     *
    /**
     * Convert a hex color string (e.g. "#FF0000" or "B4B4B4") to an RGB array [r, g, b].
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return [180, 180, 180];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Resolve dynamic placeholders in watermark text (e.g. {date}, {time}, {copy_number}, {branch_name}, {field}).
     */
    protected function resolveWatermarkText(string $text, int $copyIndex = 0): string
    {
        $data = $this->data ?? [];

        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($copyIndex, $data) {
            $key = strtolower($matches[1]);
            if ($key === 'date') {
                return now()->format('d-M-Y');
            }
            if ($key === 'time') {
                return now()->format('H:i');
            }
            if ($key === 'copy_number') {
                return (string) ($copyIndex + 1);
            }
            if ($key === 'branch_name' && !empty($data['branch_name'])) {
                return (string) $data['branch_name'];
            }
            if ($key === 'company_name' && !empty($data['company_name'])) {
                return (string) $data['company_name'];
            }
            // Look up from payload data
            if (array_key_exists($matches[1], $data)) {
                return (string) $data[$matches[1]];
            }
            if (array_key_exists($key, $data)) {
                return (string) $data[$key];
            }
            return $matches[0];
        }, $text);
    }

    /**
     * Apply watermark to all pages of the PDF.
     *
     * Watermark configuration can come from $options['watermark'] array or
     * individual $options keys (watermark_text, watermark_opacity, etc.).
     */
    protected function applyWatermark(array $options): void
    {
        $wm = $options['watermark'] ?? $options;

        $globalColor       = $wm['watermark_color'] ?? '#B4B4B4';
        $globalFontSize    = !empty($wm['watermark_font_size']) ? (float) $wm['watermark_font_size'] : null;
        $globalFontFamily  = $wm['watermark_font_family'] ?? 'Arial';
        $globalFontStyle   = $wm['watermark_font_style'] ?? 'B';
        $globalOpacity     = (float) ($wm['watermark_transparency'] ?? $wm['watermark_opacity'] ?? 0.3);
        $globalRotation    = (int) ($wm['watermark_rotation'] ?? -45);
        $globalPosition    = $wm['watermark_position'] ?? 'center';

        // ── Per-Copy Watermark Mode ─────────────────────────────────────
        // Each entry in watermark_copies is an object: {text, opacity, rotation, position, color, font_size}
        $watermarkCopies = $wm['watermark_copies'] ?? null;
        if (!empty($watermarkCopies) && is_array($watermarkCopies)) {
            $totalPages = $this->pdf->n;
            $copyCount = count($watermarkCopies);

            if ($copyCount > 0 && $totalPages > 0) {
                $pagesPerCopy = intdiv($totalPages, $copyCount);
                if ($pagesPerCopy < 1) $pagesPerCopy = 1;

                $pageW = $this->pdf->w;
                $pageH = $this->pdf->h;

                for ($c = 0; $c < $copyCount; $c++) {
                    $copyConfig = $watermarkCopies[$c] ?? [];
                    // Support both object format and legacy string format
                    if (is_string($copyConfig)) {
                        $rawText  = $copyConfig;
                        $opacity  = $globalOpacity;
                        $rotation = $globalRotation;
                        $position = $globalPosition;
                        $color    = $globalColor;
                        $fontSize = $globalFontSize;
                    } else {
                        $rawText  = $copyConfig['text'] ?? '';
                        $opacity  = (float) ($copyConfig['transparency'] ?? $copyConfig['opacity'] ?? $globalOpacity);
                        $rotation = (int) ($copyConfig['rotation'] ?? $globalRotation);
                        $position = $copyConfig['position'] ?? $globalPosition;
                        $color    = $copyConfig['color'] ?? $globalColor;
                        $fontSize = !empty($copyConfig['font_size']) ? (float) $copyConfig['font_size'] : $globalFontSize;
                    }
                    if (empty($rawText)) continue;

                    $text     = $this->resolveWatermarkText($rawText, $c);
                    $opacity  = max(0.05, min(1.0, $opacity));
                    $rotation = max(-90, min(90, $rotation));
                    $fontSize = $fontSize ?: (min($pageW, $pageH) / 8);

                    $startPage = $c * $pagesPerCopy + 1;
                    $endPage = min(($c + 1) * $pagesPerCopy, $totalPages);

                    for ($p = $startPage; $p <= $endPage; $p++) {
                        $this->pdf->page = $p;
                        $this->renderWatermarkOnPage($text, $fontSize, $opacity, $rotation, $position, $pageW, $pageH, $color, $globalFontFamily, $globalFontStyle);
                    }
                }

                $this->pdf->page = 1;
                return;
            }
        }
        // ────────────────────────────────────────────────────────────────

        // ── Original Single Watermark (backward compatible) ────────────
        $rawText = $wm['watermark_text'] ?? null;
        if (!$rawText) {
            return;
        }

        $text     = $this->resolveWatermarkText($rawText, 0);
        $opacity  = max(0.05, min(1.0, $globalOpacity));
        $rotation = max(-90, min(90, $globalRotation));
        $position = $globalPosition;

        $pageW = $this->pdf->w;
        $pageH = $this->pdf->h;

        // Font size relative to page (about 1/8 of shortest side) or custom
        $fontSize = $globalFontSize ?: (min($pageW, $pageH) / 8);

        $pages = $this->pdf->page; // total pages rendered
        for ($i = 1; $i <= $pages; $i++) {
            $this->pdf->page = $i;
            $this->renderWatermarkOnPage($text, $fontSize, $opacity, $rotation, $position, $pageW, $pageH, $globalColor, $globalFontFamily, $globalFontStyle);
        }

        // Reset to first page
        $this->pdf->page = 1;
    }

    /**
     * Render watermark on a single page with customizable styling.
     */
    protected function renderWatermarkOnPage(
        string $text,
        float $fontSize,
        float $opacity,
        int $rotation,
        string $position,
        float $pageW,
        float $pageH,
        string $color = '#B4B4B4',
        string $fontFamily = 'Arial',
        string $fontStyle = 'B'
    ): void {
        // Set the alpha channel for the watermark
        $this->pdf->SetAlpha($opacity);

        // Set watermark color
        [$r, $g, $b] = $this->hexToRgb($color);
        $this->pdf->SetTextColor($r, $g, $b);
        $this->pdf->SetFont($fontFamily, $fontStyle, $fontSize);

        if ($position === 'tile') {
            $this->renderTiledWatermark($text, $fontSize, $rotation, $pageW, $pageH);
        } else {
            [$cx, $cy] = $this->getWatermarkPosition($position, $pageW, $pageH, $fontSize);
            $this->pdf->Rotate($rotation, $cx, $cy);
            $this->pdf->SetXY($cx - 10, $cy - $fontSize / 2);
            $this->pdf->Cell(0, $fontSize, $text, 0, 0, 'C');
            $this->pdf->Rotate(0);
        }

        // Reset alpha
        $this->pdf->SetAlpha(1);
    }

    /**
     * Get center coordinates for the given watermark position.
     */
    protected function getWatermarkPosition(string $position, float $pageW, float $pageH, float $fontSize): array
    {
        return match ($position) {
            'top-left'      => [$pageW * 0.15, $pageH * 0.15],
            'top-right'     => [$pageW * 0.85, $pageH * 0.15],
            'bottom-left'   => [$pageW * 0.15, $pageH * 0.85],
            'bottom-right'  => [$pageW * 0.85, $pageH * 0.85],
            default         => [$pageW / 2, $pageH / 2], // center
        };
    }

    /**
     * Render a tiled (repeating) watermark across the page.
     */
    protected function renderTiledWatermark(string $text, float $fontSize, int $rotation, float $pageW, float $pageH): void
    {
        $spacing = $fontSize * 2.5;
        $cols = ceil($pageW / $spacing) + 1;
        $rows = ceil($pageH / $spacing) + 1;

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $tx = $c * $spacing;
                $ty = $r * $spacing + ($c % 2 === 0 ? 0 : $fontSize);
                $this->pdf->Rotate($rotation, $tx, $ty);
                $this->pdf->SetXY($tx, $ty);
                $this->pdf->Cell($spacing, $fontSize, $text, 0, 0, 'C');
                $this->pdf->Rotate(0);
            }
        }
    }

    // ── Sorting, Grouping & Filtering (SGF) ─────────────────────

    /**
     * Get data options (sorting, grouping, filtering) from template.
     */
    protected function getDataOptions(): array
    {
        return $this->template->data_options ?? [];
    }

    /**
     * Apply multi-level sorting to rows based on sort field configuration.
     *
     * Each sort field: ['field' => 'column_name', 'direction' => 'asc'|'desc']
     */
    protected function applySorting(array $rows, ?array $sortFields): array
    {
        if (empty($sortFields)) {
            return $rows;
        }

        usort($rows, function ($a, $b) use ($sortFields) {
            foreach ($sortFields as $sf) {
                $field = $sf['field'] ?? '';
                $direction = strtolower($sf['direction'] ?? $sf['sortDirection'] ?? 'asc');

                $valA = $this->resolveValue($field, $a);
                $valB = $this->resolveValue($field, $b);

                // Handle nulls: sort nulls to end regardless of direction
                if ($valA === null && $valB === null) continue;
                if ($valA === null) return 1;
                if ($valB === null) return -1;

                // Compare: numeric vs string comparison
                $cmp = is_numeric($valA) && is_numeric($valB)
                    ? ($valA <=> $valB)
                    : strcasecmp((string) $valA, (string) $valB);

                if ($cmp !== 0) {
                    return ($direction === 'desc') ? -$cmp : $cmp;
                }
            }
            return 0;
        });

        return $rows;
    }

    /**
     * Apply filter expression to rows, returning only matching rows.
     *
     * The expression is evaluated per-row using the same engine as computed columns.
     * Returns empty array when no rows match.
     */
    protected function applyFilter(array $rows, ?string $expression): array
    {
        if (empty($expression)) {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) use ($expression) {
            $result = $this->evaluateExpression($expression, $row);
            // Consider "truthy" values as passing the filter
            return !empty($result) && $result !== '0' && $result !== 0 && $result !== false && $result !== 'false';
        }));
    }

    // ── Advanced Element Properties ──────────────────────────────

    /**
     * Draw a custom border (dashed or dotted) around a rectangular area.
     *
     * FPDF's Cell border only supports solid lines. This method draws
     * dashed or dotted borders using individual line segments.
     */
    protected function drawCustomBorder(float $x, float $y, float $w, float $h, string $style): void
    {
        if ($w <= 0 || $h <= 0) return;

        $isDashed = ($style === 'dashed');
        $segLen = $isDashed ? 1.5 : 0.5;
        $gapLen = $isDashed ? 1.0 : 0.8;

        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.2);

        // Top edge: (x, y) → (x+w, y)
        $total = $w;
        $pos = 0;
        while ($pos < $total) {
            $end = min($pos + $segLen, $total);
            $this->pdf->Line($x + $pos, $y, $x + $end, $y);
            $pos += $segLen + $gapLen;
        }

        // Right edge: (x+w, y) → (x+w, y+h)
        $total = $h;
        $pos = 0;
        while ($pos < $total) {
            $end = min($pos + $segLen, $total);
            $this->pdf->Line($x + $w, $y + $pos, $x + $w, $y + $end);
            $pos += $segLen + $gapLen;
        }

        // Bottom edge: (x, y+h) → (x+w, y+h)
        $total = $w;
        $pos = 0;
        while ($pos < $total) {
            $end = min($pos + $segLen, $total);
            $this->pdf->Line($x + $total - $pos, $y + $h, $x + $total - $end, $y + $h);
            $pos += $segLen + $gapLen;
        }

        // Left edge: (x, y) → (x, y+h)
        $total = $h;
        $pos = 0;
        while ($pos < $total) {
            $end = min($pos + $segLen, $total);
            $this->pdf->Line($x, $y + $total - $pos, $x, $y + $total - $end);
            $pos += $segLen + $gapLen;
        }

        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetLineWidth(0.2);
    }

    // ── Hyperlinks & Drill-Down ──────────────────────────────────

    /**
     * Resolve a hyperlink from element properties.
     *
     * Supports:
     * - 'url' type: direct URL or with @{{field}} placeholders
     * - 'email' type: constructs mailto: link
     * - 'none' or empty: returns null (no link)
     *
     * Placeholders like @{{field_name}} are resolved against current data row.
     */
    protected function resolveLink(array $el): ?string
    {
        $linkType = $el['linkType'] ?? 'none';
        if ($linkType === 'none' || empty($linkType)) {
            return null;
        }

        $linkUrl = $el['linkUrl'] ?? '';

        // Resolve @{{field}} placeholders in the URL
        $resolved = preg_replace_callback('/@\{\{(\w+(?:\.\w+)*)\}\}/', function ($m) {
            return $this->resolveValue($m[1], $this->data) ?? $m[0];
        }, $linkUrl);

        if (empty($resolved)) {
            return null;
        }

        return match ($linkType) {
            'email' => 'mailto:' . $resolved,
            'url'   => $resolved,
            default => null,
        };
    }

    // ── Eco Mode / Sustainability ─────────────────────────────

    /**
     * Apply eco-friendly transformations to the generated PDF.
     *
     * This method calculates estimated savings from eco-friendly print settings
     * such as forced duplex, N-up layout, grayscale, and image removal.
     * The actual printer-side settings are enforced by the TrayPrint agent.
     */
    protected function applyEcoMode(
        bool $ecoMode,
        bool $grayscaleForce,
        int $pagesPerSheet,
        bool $removeImages,
        array $options
    ): void {
        $pagesBefore = $this->pdf->page;
        $savings = [
            'eco_mode'         => $ecoMode,
            'grayscale_force'  => $grayscaleForce,
            'pages_per_sheet'  => $pagesPerSheet,
            'remove_images'    => $removeImages,
            'pages_before'     => $pagesBefore,
            'pages_after'      => $pagesBefore,
        ];

        // Estimate pages saved by N-up layout
        if ($pagesPerSheet > 1 && $pagesBefore > 1) {
            $pagesAfter = (int)ceil($pagesBefore / $pagesPerSheet);
            $savings['pages_after'] = $pagesAfter;
            $savings['pages_saved'] = $pagesBefore - $pagesAfter;
            // ~5g CO₂ saved per page not printed
            $savings['carbon_saved_grams'] = round(($pagesBefore - $pagesAfter) * 5, 2);
        } else {
            $savings['pages_saved'] = 0;
            $savings['carbon_saved_grams'] = 0;
        }

        // Forced duplex: each page printed on both sides saves ~50% paper
        if ($ecoMode && $pagesBefore > 1) {
            $duplexPagesSaved = (int)floor($pagesBefore / 2);
            $savings['duplex_saved'] = $duplexPagesSaved;
            $savings['carbon_saved_grams'] += round($duplexPagesSaved * 5, 2);
        } else {
            $savings['duplex_saved'] = 0;
        }

        // Log the eco savings
        \Illuminate\Support\Facades\Log::info('Eco Mode applied', [
            'eco_mode'         => $ecoMode,
            'grayscale_force'  => $grayscaleForce,
            'pages_per_sheet'  => $pagesPerSheet,
            'remove_images'    => $removeImages,
            'pages_before'     => $pagesBefore,
            'pages_after'      => $savings['pages_after'],
            'pages_saved'      => $savings['pages_saved'],
            'carbon_saved_g'   => $savings['carbon_saved_grams'],
            'duplex_saved'     => $savings['duplex_saved'],
        ]);

        // Store savings data on the instance for later retrieval
        $this->eco_savings = $savings;
    }

    /**
     * Get the eco savings data from the last generated document.
     */
    public function getEcoSavings(): ?array
    {
        return $this->eco_savings ?? null;
    }
}
