<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class DataSchema extends Model
{
    protected $fillable = [
        'client_app_id',
        'schema_name',
        'version',
        'is_latest',
        'label',
        'fields',
        'tables',
        'sample_data',
        'changelog',
    ];

    protected $casts = [
        'fields'      => 'array',
        'tables'      => 'array',
        'sample_data' => 'array',
        'changelog'   => 'array',
        'is_latest'   => 'boolean',
        'version'     => 'integer',
    ];

    // ── Scopes ───────────────────────────────────────────────

    public function scopeLatest(Builder $query): Builder
    {
        return $query->where('is_latest', true);
    }

    public function scopeForSchema(Builder $query, string $schemaName): Builder
    {
        return $query->where('schema_name', $schemaName);
    }

    // ── Relationships ────────────────────────────────────────

    public function clientApp(): BelongsTo
    {
        return $this->belongsTo(ClientApp::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(PrintTemplate::class);
    }

    // ── Versioning ───────────────────────────────────────────

    /**
     * Create a new version of this schema.
     * Marks all previous versions of the same schema_name as not latest.
     */
    public static function createNewVersion(string $schemaName, array $data): self
    {
        $currentLatest = static::forSchema($schemaName)->latest()->first();
        $newVersion = $currentLatest ? $currentLatest->version + 1 : 1;

        // Build changelog by diffing fields/tables
        $changelog = null;
        if ($currentLatest) {
            $changelog = static::buildChangelog($currentLatest, $data);

            // Mark previous as not latest
            static::forSchema($schemaName)->update(['is_latest' => false]);
        }

        return static::create(array_merge($data, [
            'schema_name' => $schemaName,
            'version'     => $newVersion,
            'is_latest'   => true,
            'changelog'   => $changelog,
        ]));
    }

    /**
     * Build a changelog array comparing old schema to new data.
     */
    protected static function buildChangelog(self $old, array $newData): array
    {
        $changes = [];

        // Diff fields
        $oldFields = $old->fields ?? [];
        $newFields = $newData['fields'] ?? [];
        foreach ($newFields as $key => $meta) {
            if (!isset($oldFields[$key])) {
                $changes[] = ['type' => 'field_added', 'key' => $key, 'meta' => $meta];
            } elseif ($oldFields[$key] != $meta) {
                $changes[] = ['type' => 'field_changed', 'key' => $key, 'old' => $oldFields[$key], 'new' => $meta];
            }
        }
        foreach ($oldFields as $key => $meta) {
            if (!isset($newFields[$key])) {
                $changes[] = ['type' => 'field_removed', 'key' => $key, 'meta' => $meta];
            }
        }

        // Diff tables
        $oldTables = $old->tables ?? [];
        $newTables = $newData['tables'] ?? [];
        foreach ($newTables as $key => $meta) {
            if (!isset($oldTables[$key])) {
                $changes[] = ['type' => 'table_added', 'key' => $key];
            } elseif ($oldTables[$key] != $meta) {
                $changes[] = ['type' => 'table_changed', 'key' => $key];
            }
        }
        foreach ($oldTables as $key => $meta) {
            if (!isset($newTables[$key])) {
                $changes[] = ['type' => 'table_removed', 'key' => $key];
            }
        }

        return $changes;
    }

    // ── Field Helpers ────────────────────────────────────────

    /**
     * Get all field keys as a flat list.
     */
    public function getFieldKeys(): array
    {
        return array_keys($this->fields ?? []);
    }

    /**
     * Get all table keys with their column keys.
     */
    public function getTableStructure(): array
    {
        $result = [];
        foreach ($this->tables ?? [] as $tableKey => $tableMeta) {
            $result[$tableKey] = array_keys($tableMeta['columns'] ?? []);
        }
        return $result;
    }

    /**
     * Format a value according to the field's type metadata.
     */
    public function formatFieldValue(string $fieldKey, $value): string
    {
        $fields = $this->fields ?? [];
        $meta = $fields[$fieldKey] ?? null;
        if (!$meta || $value === null) {
            return (string) $value;
        }

        $type   = $meta['type'] ?? 'string';
        $format = $meta['format'] ?? null;

        return static::applyFormat($value, $type, $format, $meta);
    }

    /**
     * Apply formatting to a value based on type and format.
     */
    public static function applyFormat($value, string $type, ?string $format, array $meta = []): string
    {
        switch ($type) {
            case 'number':
                $decimals = $meta['decimal_places'] ?? 2;
                if ($format === 'currency') {
                    $currency = $meta['currency_code'] ?? 'IDR';
                    $formatted = number_format((float) $value, $decimals, ',', '.');
                    return $currency === 'IDR' ? "Rp {$formatted}" : "{$currency} {$formatted}";
                }
                if ($format === 'integer') {
                    return number_format((float) $value, 0, ',', '.');
                }
                if ($format === 'terbilang') {
                    return static::terbilang((float) $value);
                }
                return number_format((float) $value, $decimals, ',', '.');

            case 'date':
                if (!$value) return '';
                try {
                    $date = new \DateTime($value);
                    $phpFormat = static::dateFormatToPhp($format ?: 'dd/MM/yyyy');
                    return $date->format($phpFormat);
                } catch (\Exception $e) {
                    return (string) $value;
                }

            case 'boolean':
                return $value ? 'Yes' : 'No';

            default:
                return (string) $value;
        }
    }

    /**
     * Convert number to Indonesian words (Terbilang).
     */
    public static function terbilang(float $number): string
    {
        $number = abs($number);
        $words = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
        $result = "";

        if ($number < 12) {
            $result = " " . $words[$number];
        } else if ($number < 20) {
            $result = static::terbilang($number - 10) . " Belas";
        } else if ($number < 100) {
            $result = static::terbilang(floor($number / 10)) . " Puluh" . static::terbilang($number % 10);
        } else if ($number < 200) {
            $result = " Seratus" . static::terbilang($number - 100);
        } else if ($number < 1000) {
            $result = static::terbilang(floor($number / 100)) . " Ratus" . static::terbilang($number % 100);
        } else if ($number < 2000) {
            $result = " Seribu" . static::terbilang($number - 1000);
        } else if ($number < 1000000) {
            $result = static::terbilang(floor($number / 1000)) . " Ribu" . static::terbilang($number % 1000);
        } else if ($number < 1000000000) {
            $result = static::terbilang(floor($number / 1000000)) . " Juta" . static::terbilang($number % 1000000);
        } else if ($number < 1000000000000) {
            $result = static::terbilang(floor($number / 1000000000)) . " Milyar" . static::terbilang($number % 1000000000);
        } else if ($number < 1000000000000000) {
            $result = static::terbilang(floor($number / 1000000000000)) . " Trilyun" . static::terbilang($number % 1000000000000);
        }

        return trim($result);
    }

    /**
     * Convert a simple date format string to PHP date() format.
     */
    protected static function dateFormatToPhp(string $format): string
    {
        $map = [
            'dd'   => 'd',
            'MM'   => 'm',
            'yyyy' => 'Y',
            'yy'   => 'y',
            'MMMM' => 'F',
            'MMM'  => 'M',
            'HH'   => 'H',
            'mm'   => 'i',
            'ss'   => 's',
        ];
        return strtr($format, $map);
    }

    /**
     * Validate print data against schema fields.
     * Returns array of error messages (empty = valid).
     */
    public function validateData(array $data): array
    {
        $errors = [];
        $fields = $this->fields ?? [];

        foreach ($fields as $key => $meta) {
            $required = $meta['required'] ?? false;
            $value = $this->resolveNestedValue($key, $data);

            if ($required && ($value === null || $value === '')) {
                $label = $meta['label'] ?? $key;
                $errors[] = "Missing required field: {$label} ({$key})";
                continue;
            }

            if ($value !== null && $value !== '') {
                $type = $meta['type'] ?? 'string';
                if ($type === 'number' && !is_numeric($value)) {
                    $errors[] = "Field '{$key}' expected numeric value, got: " . gettype($value);
                }
            }
        }

        // Validate tables
        $tables = $this->tables ?? [];
        foreach ($tables as $tableKey => $tableMeta) {
            $rows = $this->resolveNestedValue($tableKey, $data);
            if ($rows !== null && !is_array($rows)) {
                $errors[] = "Table '{$tableKey}' expected array of rows.";
                continue;
            }

            $minRows = $tableMeta['min_rows'] ?? null;
            if ($minRows && is_array($rows) && count($rows) < $minRows) {
                $errors[] = "Table '{$tableKey}' requires at least {$minRows} row(s), got " . count($rows) . ".";
            }
        }

        return $errors;
    }

    protected function resolveNestedValue(string $key, array $data)
    {
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
