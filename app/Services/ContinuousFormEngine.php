<?php

namespace App\Services;

use App\Models\PrintTemplate;
use FPDF;

class ContinuousFormEngine
{
    protected $pdf;
    protected $template;
    protected $data;

    /**
     * Generate PDF binary from template and data.
     */
    public function generate(PrintTemplate $template, array $data)
    {
        $this->template = $template;
        $this->data = $data;

        // Custom paper size in mm [width, height]
        // FPDF expects the array to be [width, height] for custom sizes
        $this->pdf = new FPDF('P', 'mm', [$template->paper_width_mm, $template->paper_height_mm]);
        $this->pdf->SetAutoPageBreak(false); 
        $this->pdf->SetMargins(0, 0, 0);

        // Find the table element if any
        $tableElement = collect($template->elements)->firstWhere('type', 'table');
        $rows = [];
        if ($tableElement) {
            $rows = $this->resolveValue($tableElement['key'], $data) ?: [];
        }

        if (empty($rows) || !$tableElement) {
            // Static single page
            $this->renderPage(0, []);
        } else {
            // Multipage loop
            $this->renderMultipageTable($tableElement, $rows);
        }

        return $this->pdf->Output('S'); 
    }

    protected function renderPage($rowIndex, $rows, $isMultipage = false)
    {
        $this->pdf->AddPage();
        
        // Render all non-table elements (static headers/footers)
        $elements = $this->template->elements ?? [];
        foreach ($elements as $el) {
            if ($el['type'] === 'field') {
                $this->renderField($el);
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

        $currentY = $startY;
        $this->renderPage(0, $rows, true);
        
        // Render Header on first page
        $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize);
        $currentY += $headerHeight;

        foreach ($rows as $index => $rowData) {
            // Page break check
            if ($currentY + $rowHeight > ($this->template->paper_height_mm - $bottomPadding)) {
                $this->renderPage(0, $rows, true);
                $currentY = $startY;
                $this->renderTableHeader($x, $currentY, $columns, $headerHeight, $fontSize);
                $currentY += $headerHeight;
            }

            $this->renderTableRow($x, $currentY, $columns, $rowData, $rowHeight, $fontSize);
            $currentY += $rowHeight;
        }
    }

    protected function renderField($el)
    {
        $value = $this->resolveValue($el['key'], $this->data);
        if ($value === null) return;

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

    protected function renderTableHeader($x, $y, $columns, $height, $fontSize)
    {
        $this->pdf->SetFont('Arial', 'B', $fontSize);
        $currentX = $x;
        foreach ($columns as $col) {
            $this->pdf->SetXY($currentX, $y);
            // Border support: hide/show
            $border = !empty($col['show_border']) ? 1 : 0;
            $this->pdf->Cell($col['width'], $height, $col['label'], $border, 0, 'C');
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
            
            $this->pdf->SetXY($currentX, $y);
            $this->pdf->Cell($col['width'], $height, $val, $border, 0, $align);
            $currentX += $col['width'];
        }
    }

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
}
