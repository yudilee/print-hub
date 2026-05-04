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

    /**
     * Generate PDF binary from template and data.
     */
    public function generate(PrintTemplate $template, array $data, array $options = [])
    {
        $this->template = $template;
        $this->data = $data;
        $this->schema = $template->dataSchema;

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
        // If eco_mode is enabled, force duplex and grayscale
        $ecoMode = !empty($options['eco_mode']);
        $grayscaleForce = !empty($options['grayscale_force']) || $ecoMode;
        $pagesPerSheet = (int)($options['pages_per_sheet'] ?? 1);
        $removeImages = !empty($options['remove_images']);
        // ─────────────────────────────────────────────────────────

        // Find the table element if any
        $elements = $template->elements ?? [];
        $tableElement = collect($elements)->firstWhere('type', 'table');
        $rows = [];
        if ($tableElement) {
            $rows = $this->resolveValue($tableElement['key'], $data) ?: [];
        }

        if (empty($rows) || !$tableElement) {
            // Static single page
            $this->renderPage();
        } else {
            // Multipage loop
            $this->renderMultipageTable($tableElement, $rows);
        }

        // Apply eco mode transformations after rendering
        if ($ecoMode || $grayscaleForce || $pagesPerSheet > 1 || $removeImages) {
            $this->applyEcoMode($ecoMode, $grayscaleForce, $pagesPerSheet, $removeImages, $options);
        }

        // Apply watermark if configured
        $this->applyWatermark($options);

        return $this->pdf->Output('S');
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
            // Page break check
            if ($currentY + $rowHeight > ($this->template->paper_height_mm - $bottomPadding)) {
                $this->renderPage();
                $currentY = $startY;
                $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize, $el);
                $currentY += $headerHeight;
            }

            $this->renderTableRow($x, $currentY, $columns, $rowData, $rowHeight, $fontSize);
            $currentY += $rowHeight;
        }
    }

    protected function renderField($el)
    {
        $el = $this->applyStyle($el);
        $value = $this->resolveValue($el['key'], $this->data);
        if ($value === null) return;

        // Apply formatting (manual override or schema)
        $value = $this->formatValue($el, $value);

        $this->renderTextCell($el, (string) $value);
    }

    protected function renderLabel($el)
    {
        $el = $this->applyStyle($el);
        $text = $el['text'] ?? '';
        if ($text === '') return;

        $this->renderTextCell($el, $text);
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

        if (!$src) return;

        try {
            // FPDF Image($file, $x, $y, $w, $h, $type, $link)
            // If w or h is 0, it is automatically calculated from the image properties
            $this->pdf->Image($src, $x, $y, $w, $h);
        } catch (\Exception $e) {
            // Log or skip if image not found/invalid
            \Illuminate\Support\Facades\Log::warning("PDF Engine: Image render failed for {$src}. " . $e->getMessage());
        }
    }

    protected function renderTextCell($el, string $value)
    {
        $x = $el['x'] ?? 0;
        $y = $el['y'] ?? 0;
        $width = $el['width'] ?? 0;
        $fontSize = $el['font_size'] ?? 10;
        $align = $el['align'] ?? 'L';
        $bold = !empty($el['bold']) ? 'B' : '';
        $border = !empty($el['border']) ? 1 : 0;

        $this->pdf->SetFont('Arial', $bold, $fontSize);
        $this->pdf->SetXY($x, $y);
        
        if ($width > 0) {
            $this->pdf->MultiCell($width, $fontSize * 0.5, $value, $border, $align);
        } else {
            $this->pdf->Cell(0, $fontSize * 0.5, $value, $border, 0, $align);
        }
    }

    protected function applyStyle($el)
    {
        if (isset($el['styleIdx']) && isset($this->template->styles[$el['styleIdx']])) {
            $style = $this->template->styles[$el['styleIdx']];
            $el['font_size'] = $style['font_size'] ?? $el['font_size'];
            $el['bold'] = $style['bold'] ?? $el['bold'];
        }
        return $el;
    }

    protected function renderTableHeader($x, $y, $columns, $height, $fontSize, $el = [])
    {
        $headerBgColor = $el['header_bg_color'] ?? null;

        $this->pdf->SetFont('Arial', 'B', $fontSize);
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
        // Replace column references with their numeric values
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

    // ── Helpers ──────────────────────────────────────────────

    protected function resolveValue($key, $data)
    {
        if (!$key) return '';
        $keys = explode('.', $key);
        $val = $data;
        foreach ($keys as $k) {
            if (isset($val[$k])) {
                $val = $val[$k];
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

    // ── Watermarking ─────────────────────────────────────────

    /**
     * Apply watermark to all pages of the PDF.
     *
     * Watermark configuration can come from $options['watermark'] array or
     * individual $options keys (watermark_text, watermark_opacity, etc.).
     */
    protected function applyWatermark(array $options): void
    {
        $wm = $options['watermark'] ?? $options;

        $text     = $wm['watermark_text'] ?? null;
        if (!$text) {
            return;
        }

        $opacity  = (float) ($wm['watermark_opacity'] ?? 0.3);
        $rotation = (int) ($wm['watermark_rotation'] ?? -45);
        $position = $wm['watermark_position'] ?? 'center';

        // Clamp values
        $opacity  = max(0.1, min(1.0, $opacity));
        $rotation = max(-90, min(90, $rotation));

        $pageW = $this->pdf->w;
        $pageH = $this->pdf->h;

        // Font size relative to page (about 1/8 of shortest side)
        $fontSize = min($pageW, $pageH) / 8;

        $pages = $this->pdf->page; // total pages rendered
        for ($i = 1; $i <= $pages; $i++) {
            $this->pdf->page = $i;
            $this->renderWatermarkOnPage($text, $fontSize, $opacity, $rotation, $position, $pageW, $pageH);
        }

        // Reset to first page
        $this->pdf->page = 1;
    }

    /**
     * Render watermark on a single page.
     */
    protected function renderWatermarkOnPage(
        string $text,
        float $fontSize,
        float $opacity,
        int $rotation,
        string $position,
        float $pageW,
        float $pageH
    ): void {
        // Set the alpha channel for the watermark
        $this->pdf->SetAlpha($opacity);

        // Set watermark color (light gray)
        $this->pdf->SetTextColor(180, 180, 180);
        $this->pdf->SetFont('Arial', 'B', $fontSize);

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
