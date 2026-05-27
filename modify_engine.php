<?php
$path = "/var/www/html/app/Services/ContinuousFormEngine.php";
$content = file_get_contents($path);

// Step 1: Add page duplication logic between eco mode and watermark in generate()
$search1 = "        // Apply eco mode transformations after rendering
        if (\$ecoMode || \$grayscaleForce || \$pagesPerSheet > 1 || \$removeImages) {
            \$this->applyEcoMode(\$ecoMode, \$grayscaleForce, \$pagesPerSheet, \$removeImages, \$options);
        }

        // Apply watermark if configured
        \$this->applyWatermark(\$options);";

$replacement1 = "        // Apply eco mode transformations after rendering
        if (\$ecoMode || \$grayscaleForce || \$pagesPerSheet > 1 || \$removeImages) {
            \$this->applyEcoMode(\$ecoMode, \$grayscaleForce, \$pagesPerSheet, \$removeImages, \$options);
        }

        // ── Per-Copy Watermark: Duplicate pages if per-copy texts are set ──
        \$watermarkCopyTexts = \$options['watermark_copy_texts'] ?? null;
        if (!empty(\$watermarkCopyTexts) && is_array(\$watermarkCopyTexts)) {
            // We have N texts for N copies - duplicate the original pages
            \$originalPageCount = \$this->pdf->n;
            \$copyCount = count(\$watermarkCopyTexts);

            // Only duplicate if we have more copies than 1 and texts match
            if (\$copyCount > 1 && \$originalPageCount > 0) {
                for (\$c = 1; \$c < \$copyCount; \$c++) {
                    for (\$p = 1; \$p <= \$originalPageCount; \$p++) {
                        \$this->pdf->n++;
                        \$this->pdf->pages[\$this->pdf->n] = \$this->pdf->pages[\$p];
                    }
                }
                // Override copies to 1 since all copies are now embedded in the PDF
                \$options['copies'] = 1;
            }
        }
        // ────────────────────────────────────────────────────────────────────

        // Apply watermark if configured
        \$this->applyWatermark(\$options);";

$content = str_replace($search1, $replacement1, $content);

// Step 2: Rewrite applyWatermark() to support per-copy watermarks
$search2 = '    protected function applyWatermark(array $options): void
    {
        $wm = $options[\'watermark\'] ?? $options;

        $text     = $wm[\'watermark_text\'] ?? null;
        if (!$text) {
            return;
        }

        $opacity  = (float) ($wm[\'watermark_opacity\'] ?? 0.3);
        $rotation = (int) ($wm[\'watermark_rotation\'] ?? -45);
        $position = $wm[\'watermark_position\'] ?? \'center\';

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
    }';

$replacement2 = '    protected function applyWatermark(array $options): void
    {
        $wm = $options[\'watermark\'] ?? $options;

        // ── Per-Copy Watermark Mode ─────────────────────────────────────
        $watermarkCopyTexts = $wm[\'watermark_copy_texts\'] ?? null;
        if (!empty($watermarkCopyTexts) && is_array($watermarkCopyTexts)) {
            $totalPages = $this->pdf->n;
            $copyCount = count($watermarkCopyTexts);

            if ($copyCount > 0 && $totalPages > 0) {
                $pagesPerCopy = intdiv($totalPages, $copyCount);
                if ($pagesPerCopy < 1) $pagesPerCopy = 1;

                $opacity  = (float) ($wm[\'watermark_opacity\'] ?? 0.3);
                $rotation = (int) ($wm[\'watermark_rotation\'] ?? -45);
                $position = $wm[\'watermark_position\'] ?? \'center\';
                $opacity  = max(0.1, min(1.0, $opacity));
                $rotation = max(-90, min(90, $rotation));

                $pageW = $this->pdf->w;
                $pageH = $this->pdf->h;
                $fontSize = min($pageW, $pageH) / 8;

                for ($c = 0; $c < $copyCount; $c++) {
                    $text = $watermarkCopyTexts[$c] ?? \'\';
                    if (empty($text)) continue;

                    $startPage = $c * $pagesPerCopy + 1;
                    $endPage = min(($c + 1) * $pagesPerCopy, $totalPages);

                    for ($p = $startPage; $p <= $endPage; $p++) {
                        $this->pdf->page = $p;
                        $this->renderWatermarkOnPage($text, $fontSize, $opacity, $rotation, $position, $pageW, $pageH);
                    }
                }

                $this->pdf->page = 1;
                return;
            }
        }
        // ────────────────────────────────────────────────────────────────

        // ── Original Single Watermark (backward compatible) ────────────
        $text     = $wm[\'watermark_text\'] ?? null;
        if (!$text) {
            return;
        }

        $opacity  = (float) ($wm[\'watermark_opacity\'] ?? 0.3);
        $rotation = (int) ($wm[\'watermark_rotation\'] ?? -45);
        $position = $wm[\'watermark_position\'] ?? \'center\';

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
    }';

$content = str_replace($search2, $replacement2, $content);

file_put_contents($path, $content);
echo "ContinuousFormEngine.php updated successfully";
