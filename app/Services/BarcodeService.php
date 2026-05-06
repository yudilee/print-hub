<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BarcodeService
{
    /**
     * Render a 1D barcode as a base64-encoded PNG data URI.
     *
     * @param string $value     The data to encode
     * @param string $symbology Barcode symbology (code128, code39, ean13, ean8, upca, itf14)
     * @param int    $width     Image width in pixels
     * @param int    $height    Image height in pixels
     * @return string           Base64-encoded PNG data URI
     */
    public function renderBarcode(string $value, string $symbology = 'code128', int $width = 300, int $height = 60): string
    {
        $generator = new BarcodeGeneratorPNG();

        $type = $this->mapSymbology($symbology);

        try {
            $png = $generator->getBarcode($value, $type, $widthFactor = max(1, (int) round($width / max(strlen($value), 1))), $height);
        } catch (\Exception $e) {
            // Return a placeholder if barcode generation fails (e.g., invalid value for symbology)
            $png = $this->createPlaceholderImage($width, $height, 'Invalid: ' . $e->getMessage());
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Render a QR code as a base64-encoded PNG data URI.
     *
     * @param string $value            The data to encode
     * @param int    $size             Image size in pixels
     * @param string $errorCorrection  Error correction level (L, M, Q, H)
     * @return string                  Base64-encoded PNG data URI
     */
    public function renderQrCode(string $value, int $size = 200, string $errorCorrection = 'M'): string
    {
        try {
            $png = QrCode::format('png')
                ->size($size)
                ->errorCorrection($errorCorrection)
                ->generate($value);
        } catch (\Exception $e) {
            $png = $this->createPlaceholderImage($size, $size, 'QR Error');
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Get the list of supported barcode symbologies.
     *
     * @return array
     */
    public function getSupportedTypes(): array
    {
        return [
            'code128' => 'Code 128',
            'code39'  => 'Code 39',
            'ean13'   => 'EAN-13',
            'ean8'    => 'EAN-8',
            'upca'    => 'UPC-A',
            'itf14'   => 'ITF-14',
        ];
    }

    /**
     * Map shortcut symbology names to BarcodeGeneratorPNG type constants.
     */
    private function mapSymbology(string $symbology): string
    {
        $map = [
            'code128' => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128,
            'code39'  => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_39,
            'ean13'   => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_EAN_13,
            'ean8'    => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_EAN_8,
            'upca'    => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_UPC_A,
            'itf14'   => \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_ITF_14,
        ];

        return $map[$symbology] ?? \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128;
    }

    /**
     * Create a simple placeholder image as fallback.
     */
    private function createPlaceholderImage(int $width, int $height, string $text = ''): string
    {
        $img = @imagecreatetruecolor(max(1, $width), max(1, $height));
        if (!$img) {
            return '';
        }

        $bgColor = @imagecolorallocate($img, 240, 240, 240);
        $textColor = @imagecolorallocate($img, 180, 180, 180);
        @imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);

        if ($text) {
            $fontSize = max(1, min(5, (int) ($width / 10)));
            @imagestring($img, $fontSize, 5, (int) ($height / 2) - 5, $text, $textColor);
        }

        ob_start();
        @imagepng($img);
        $png = ob_get_clean();
        @imagedestroy($img);

        return $png ?: '';
    }
}
