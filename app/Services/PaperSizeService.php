<?php

namespace App\Services;

class PaperSizeService
{
    /**
     * Get width and height in mm for a given paper size name.
     */
    public static function getSizeInMm(string $paperSize): ?array
    {
        $sizes = config('paper.sizes');
        return $sizes[$paperSize] ?? null;
    }

    /**
     * Get width in mm for a given paper size name.
     */
    public static function getWidth(string $paperSize): ?float
    {
        $size = static::getSizeInMm($paperSize);
        return $size ? $size[0] : null;
    }

    /**
     * Get height in mm for a given paper size name.
     */
    public static function getHeight(string $paperSize): ?float
    {
        $size = static::getSizeInMm($paperSize);
        return $size ? $size[1] : null;
    }

    /**
     * Check if a paper size name is known.
     */
    public static function exists(string $paperSize): bool
    {
        return array_key_exists($paperSize, config('paper.sizes'));
    }

    /**
     * List all known paper size names.
     */
    public static function all(): array
    {
        return array_keys(config('paper.sizes'));
    }

    /**
     * Resolve paper dimensions from a PrintProfile, handling both standard and custom sizes.
     *
     * @param \App\Models\PrintProfile $profile
     * @return array{width_mm: float, height_mm: float}
     */
    public static function resolveFromProfile(\App\Models\PrintProfile $profile): array
    {
        if ($profile->is_custom) {
            return [
                'width_mm'  => (float) $profile->custom_width,
                'height_mm' => (float) $profile->custom_height,
            ];
        }

        $size = static::getSizeInMm($profile->paper_size);
        if ($size) {
            return [
                'width_mm'  => (float) $size[0],
                'height_mm' => (float) $size[1],
            ];
        }

        // Fallback to A4
        return ['width_mm' => 210.0, 'height_mm' => 297.0];
    }
}
