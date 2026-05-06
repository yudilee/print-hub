<?php

namespace App\Services;

use App\Models\PrintFont;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * FontService handles conversion of TTF/OTF fonts to FPDF-compatible
 * definition files and manages font loading for PDF generation.
 */
class FontService
{
    /**
     * FPDF font definition files cache (family => definition filename).
     */
    protected array $fpdfDefinitions = [];

    /**
     * Convert an uploaded TTF/OTF font file to FPDF-compatible definition files.
     *
     * This uses the FPDF MakeFont utility to generate:
     *   - {fontname}.php   (font definition)
     *   - {fontname}.z     (compressed font data)
     *
     * @param string $fontPath Full path to the TTF/OTF font file
     * @return array{definition: string, compressed: string} Generated file names
     */
    public function convertToFpdf(string $fontPath): array
    {
        $ext = strtolower(pathinfo($fontPath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['ttf', 'otf'])) {
            throw new \InvalidArgumentException("Unsupported font format: .{$ext}");
        }

        $fontsDisk = Storage::disk('fonts');
        $fontsDir = $fontsDisk->path('');

        // Remember current working directory so we can restore it
        $cwd = getcwd();

        try {
            // Change to fonts storage directory so MakeFont saves files there
            chdir($fontsDir);

            // Include FPDF MakeFont utility
            $makeFontPath = base_path('vendor/setasign/fpdf/makefont/makefont.php');
            if (!file_exists($makeFontPath)) {
                throw new \RuntimeException('FPDF MakeFont utility not found at: ' . $makeFontPath);
            }

            // The MakeFont utility defines functions; require_once to avoid redefinition
            require_once $makeFontPath;

            // Run MakeFont to generate .php and .z files
            // MakeFont(fontfile, encoding, embed, subset)
            \MakeFont($fontPath, 'cp1252', true, true);

            $basename = pathinfo($fontPath, PATHINFO_FILENAME);

            $definitionFile = $basename . '.php';
            $compressedFile = $basename . '.z';

            // Verify files were created
            if (!file_exists($fontsDir . '/' . $definitionFile)) {
                throw new \RuntimeException("FPDF definition file was not generated: {$definitionFile}");
            }

            return [
                'definition' => $definitionFile,
                'compressed' => $compressedFile,
            ];
        } catch (\Throwable $e) {
            Log::error('Font conversion failed: ' . $e->getMessage(), [
                'font_path' => $fontPath,
            ]);
            throw $e;
        } finally {
            // Restore original working directory
            chdir($cwd);
        }
    }

    /**
     * Load a custom font into the FPDF instance.
     *
     * Looks up the PrintFont model by font_family, locates the definition
     * file on the fonts disk, and registers it with FPDF via AddFont().
     *
     * @param \FPDF $pdf The FPDF instance
     * @param string $fontFamily The font family name (or 'Arial' for default)
     * @param string $style Font style: '', 'B', 'I', or 'BI'
     * @return string The resolved font family name to use with SetFont()
     */
    public function loadFontForPdf(\FPDF $pdf, string $fontFamily, string $style = ''): string
    {
        $normalized = strtolower(trim($fontFamily));

        // Default font - no loading needed
        if ($normalized === '' || $normalized === 'arial') {
            return 'Arial';
        }

        $cacheKey = $normalized . $style;
        if (isset($this->fpdfDefinitions[$cacheKey])) {
            // Already loaded
            return $this->fpdfDefinitions[$cacheKey];
        }

        try {
            // Look up the font in the database
            $font = PrintFont::where('font_family', $fontFamily)
                ->where('is_active', true)
                ->first();

            if (!$font || !$font->file_path) {
                Log::warning("Custom font not found or inactive: {$fontFamily}");
                return 'Arial';
            }

            $fontsDisk = Storage::disk('fonts');
            $fontsDir = $fontsDisk->path('');

            // The definition file is {basename}.php
            $basename = pathinfo($font->file_path, PATHINFO_FILENAME);
            $defFile = $basename . '.php';
            $defFilePath = $fontsDir . '/' . $defFile;

            // If definition file doesn't exist, try to generate it
            if (!file_exists($defFilePath)) {
                $fullFontPath = $fontsDir . '/' . $font->file_path;
                if (file_exists($fullFontPath)) {
                    $this->convertToFpdf($fullFontPath);
                } else {
                    Log::error("Font file not found on disk: {$fullFontPath}");
                    return 'Arial';
                }
            }

            // Add font to FPDF with the fonts directory as the search path
            $pdf->AddFont($fontFamily, $style, $defFile, $fontsDir);

            $this->fpdfDefinitions[$cacheKey] = $fontFamily;
            return $fontFamily;

        } catch (\Throwable $e) {
            Log::error("Failed to load font '{$fontFamily}' for PDF: " . $e->getMessage());
            return 'Arial';
        }
    }
}
