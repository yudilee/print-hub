<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintTemplate extends Model
{
    protected $fillable = [
        'name',
        'data_schema_id',
        'data_schema_version',
        'paper_width_mm',
        'paper_height_mm',
        'background_image_path',
        'styles',
        'background_config',
        'elements'
    ];

    protected $casts = [
        'elements'          => 'array',
        'styles'            => 'array',
        'background_config' => 'array',
    ];

    // ── Relationships ────────────────────────────────────────

    public function dataSchema(): BelongsTo
    {
        return $this->belongsTo(DataSchema::class);
    }

    // ── Schema Helpers ───────────────────────────────────────

    /**
     * Get all field keys used by this template's elements.
     */
    public function getUsedFieldKeys(): array
    {
        $elements = $this->elements ?? [];
        return collect($elements)
            ->where('type', 'field')
            ->pluck('key')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get all table definitions used by this template.
     */
    public function getUsedTables(): array
    {
        $elements = $this->elements ?? [];
        return collect($elements)
            ->where('type', 'table')
            ->map(fn($el) => [
                'key'     => $el['key'],
                'columns' => collect($el['columns'] ?? [])->pluck('key')->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Build a required-data schema derived from this template's elements.
     * This is the "bidirectional" schema — what data does this template need?
     */
    public function buildRequiredSchema(): array
    {
        $result = [
            'template'        => $this->name,
            'paper_width_mm'  => $this->paper_width_mm,
            'paper_height_mm' => $this->paper_height_mm,
            'required_fields' => [],
            'required_tables' => [],
            'sample_data'     => null,
        ];

        $elements = $this->elements ?? [];
        $schema   = $this->dataSchema;

        // Fields
        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'field' && !empty($el['key'])) {
                $fieldMeta = null;
                if ($schema && isset($schema->fields[$el['key']])) {
                    $fieldMeta = $schema->fields[$el['key']];
                }
                $result['required_fields'][$el['key']] = $fieldMeta ?? [
                    'label' => $el['key'],
                    'type'  => 'string',
                ];
            }
        }

        // Tables
        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'table' && !empty($el['key'])) {
                $columns = [];
                foreach ($el['columns'] ?? [] as $col) {
                    $colMeta = null;
                    if ($schema && isset($schema->tables[$el['key']]['columns'][$col['key']])) {
                        $colMeta = $schema->tables[$el['key']]['columns'][$col['key']];
                    }
                    $columns[$col['key']] = $colMeta ?? [
                        'label' => $col['label'] ?? $col['key'],
                        'type'  => 'string',
                    ];
                }
                $result['required_tables'][$el['key']] = [
                    'columns' => $columns,
                ];
            }
        }

        // Include sample data from schema if available
        if ($schema && $schema->sample_data) {
            $result['sample_data'] = $schema->sample_data;
        }

        return $result;
    }

    /**
     * Check if the bound schema version is outdated.
     */
    public function isSchemaOutdated(): bool
    {
        if (!$this->data_schema_id) return false;

        $schema = $this->dataSchema;
        if (!$schema) return false;

        // Find the latest version for this schema_name
        $latestVersion = DataSchema::forSchema($schema->schema_name)
            ->latest()
            ->value('version');

        return $latestVersion && $latestVersion > $schema->version;
    }

    /**
     * Get the latest schema version number for comparison.
     */
    public function getLatestSchemaVersion(): ?int
    {
        if (!$this->dataSchema) return null;

        return DataSchema::forSchema($this->dataSchema->schema_name)
            ->latest()
            ->value('version');
    }
}
