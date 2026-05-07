<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Setting represents a key-value configuration entry in the system.
 *
 * Settings are stored in a flat key-value table with a type column
 * to support proper casting when retrieving values.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get a setting value by key, with optional default.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json'    => json_decode($setting->value, true),
            default   => $setting->value,
        };
    }

    /**
     * Set a setting value by key. Creates or updates the record.
     */
    public static function setValue(string $key, mixed $value, string $type = 'string'): self
    {
        $stringValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json'    => json_encode($value),
            'integer' => (string) (int) $value,
            default   => (string) $value,
        };

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue, 'type' => $type]
        );
    }

    /**
     * Get all settings as a key-value array with proper type casting.
     */
    public static function getAllCasted(): array
    {
        return static::all()->mapWithKeys(function ($setting) {
            return [$setting->key => static::getValue($setting->key)];
        })->toArray();
    }
}
