<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'sample_data',
        'elements'
    ];

    protected $casts = [
        'elements'          => 'array',
        'styles'            => 'array',
        'background_config' => 'array',
        'sample_data'       => 'array',
    ];

    // ── Relationships ────────────────────────────────────────

    public function dataSchema(): BelongsTo
    {
        return $this->belongsTo(DataSchema::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class, 'print_template_id');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class, 'print_template_id')->latestOfMany();
    }

    // ── Version Helpers ───────────────────────────────────────

    /**
     * Create a snapshot of the current template state.
     */
    public function createSnapshot(string $label = null, string $changelog = null, ?\App\Models\User $user = null): TemplateVersion
    {
        $maxVersion = $this->versions()->max('version_number') ?? 0;

        return $this->versions()->create([
            'version_number' => $maxVersion + 1,
            'elements'       => $this->elements,
            'styles'         => $this->styles,
            'properties'     => [
                'paper_width_mm'        => $this->paper_width_mm,
                'paper_height_mm'       => $this->paper_height_mm,
                'background_image_path' => $this->background_image_path,
                'data_schema_id'        => $this->data_schema_id,
                'data_schema_version'   => $this->data_schema_version,
                'background_config'     => $this->background_config,
                'sample_data'           => $this->sample_data,
            ],
            'label'     => $label,
            'changelog' => $changelog,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Restore template state from a given version.
     */
    public function restoreVersion(TemplateVersion $version): void
    {
        $properties = $version->properties ?? [];

        $this->update([
            'elements'              => $version->elements,
            'styles'                => $version->styles,
            'paper_width_mm'        => $properties['paper_width_mm'] ?? $this->paper_width_mm,
            'paper_height_mm'       => $properties['paper_height_mm'] ?? $this->paper_height_mm,
            'background_image_path' => $properties['background_image_path'] ?? $this->background_image_path,
            'data_schema_id'        => $properties['data_schema_id'] ?? $this->data_schema_id,
            'data_schema_version'   => $properties['data_schema_version'] ?? $this->data_schema_version,
            'background_config'     => $properties['background_config'] ?? $this->background_config,
            'sample_data'           => $properties['sample_data'] ?? $this->sample_data,
        ]);
    }

    // ── Schema Helpers ───────────────────────────────────────

    /**
     * Get a flat array of all elements, supporting both legacy flat format
     * and the new sections-based format ({ sections: {...}, elements: [...] }).
     */
    protected function getFlatElements(): array
    {
        $elements = $this->elements ?? [];

        // Legacy flat format: numeric array of element objects
        if (array_is_list($elements)) {
            return $elements;
        }

        // Sections format: { sections: {...}, elements: [...] }
        if (isset($elements['sections']) && isset($elements['elements'])) {
            $flat = [];
            $sectionOrder = ['pageHeader', 'reportHeader', 'detail', 'reportFooter', 'pageFooter'];
            foreach ($sectionOrder as $key) {
                $sec = $elements['sections'][$key] ?? [];
                if (!empty($sec['elements']) && is_array($sec['elements'])) {
                    array_push($flat, ...$sec['elements']);
                }
            }
            return $flat;
        }

        // Fallback: return as-is (may be empty or unexpected format)
        return is_array($elements) ? $elements : [];
    }

    /**
     * Get all field keys used by this template's elements.
     */
    public function getUsedFieldKeys(): array
    {
        $elements = $this->getFlatElements();
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
        $elements = $this->getFlatElements();
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

        $elements = $this->getFlatElements();
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
