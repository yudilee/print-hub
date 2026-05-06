<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'parameters',
        'data_options',
        'elements'
    ];

    protected $casts = [
        'elements'          => 'array',
        'styles'            => 'array',
        'background_config' => 'array',
        'sample_data'       => 'array',
        'parameters'        => 'array',
        'data_options'      => 'array',
    ];

    // ── Relationships ────────────────────────────────────────

    public function dataSchema(): BelongsTo
    {
        return $this->belongsTo(DataSchema::class);
    }

    /**
     * Many-to-many: additional schemas attached via pivot table.
     * The pivot stores an optional "alias" (e.g. "CRM", "Accounting").
     */
    public function schemas(): BelongsToMany
    {
        return $this->belongsToMany(DataSchema::class, 'print_template_data_schema')
            ->withPivot('alias')
            ->withTimestamps();
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(TestScenario::class);
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
    // ── Runtime Parameters ────────────────────────────────────

    /**
     * Get the template's parameter definitions.
     *
     * Each parameter has:
     *  - name        (string)  – unique key
     *  - label       (string)  – human-readable prompt
     *  - type        (string)  – text | number | date | boolean | select
     *  - default     (mixed)   – optional default value
     *  - options     (array)   – required when type=select
     *  - required    (bool)    – whether the user must fill it
     *
     * @return array<int, array>
     */
    public function getParameters(): array
    {
        return $this->parameters ?? [];
    }

    /**
     * Merge user-supplied parameter values into the data array.
     * Missing optional parameters use their declared default.
     * Required parameters that are missing or empty are skipped
     * (the caller may decide how to respond).
     *
     * @param  array  $userParams  Key-value pairs supplied by the caller.
     * @param  array  $data        The current data payload.
     * @return array                Updated data with resolved parameter values.
     */
    public function resolveParameters(array $userParams, array $data): array
    {
        $params = $this->getParameters();

        foreach ($params as $param) {
            $key = $param['name'] ?? null;
            if ($key === null) {
                continue;
            }

            // Use supplied value, fall back to default
            $value = array_key_exists($key, $userParams)
                ? $userParams[$key]
                : ($param['default'] ?? null);

            // Cast booleans properly
            if (($param['type'] ?? 'text') === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }

            // Store in data so the engine can use {param_name} references
            $data[$key] = $value;
        }

        return $data;
    }

    // ── Helpers ────────────────────────────────────────────────

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
     * Get ALL schemas attached to this template, including both the legacy
     * single schema (data_schema_id) and any additional pivot schemas.
     * Returns a collection keyed by a unique context key.
     */
    public function getAllSchemas(): \Illuminate\Support\Collection
    {
        $schemas = collect();

        // Legacy single schema
        if ($this->dataSchema) {
            $schemas->push([
                'schema'          => $this->dataSchema,
                'alias'           => null,
                'is_primary'      => true,
                'source'          => 'legacy',
                'client_app_name' => $this->dataSchema->clientApp?->name,
            ]);
        }

        // Additional pivot schemas (skip if same id as primary to avoid dupes)
        $primaryId = $this->data_schema_id;
        foreach ($this->schemas as $schema) {
            if ($schema->id === $primaryId) continue;
            $schemas->push([
                'schema'          => $schema,
                'alias'           => $schema->pivot->alias,
                'is_primary'      => false,
                'source'          => 'pivot',
                'client_app_name' => $schema->clientApp?->name,
            ]);
        }

        return $schemas;
    }

    /**
     * Get ALL unique field keys across all attached schemas.
     * When multiple schemas are present, keys are prefixed with
     * the alias (or client_app_name) to avoid collisions, e.g.
     * "crm.customer.name" or "accounting.invoice.total".
     */
    public function getAllFieldKeys(): array
    {
        $allSchemas = $this->getAllSchemas();
        $result     = [];

        foreach ($allSchemas as $entry) {
            $schema  = $entry['schema'];
            $prefix  = $entry['alias'] ?? $entry['client_app_name'] ?? null;
            $fields  = $schema->getFieldKeys();

            $singleSchema = $allSchemas->count() === 1;

            foreach ($fields as $key) {
                if ($singleSchema || !$prefix) {
                    $result[] = $key;
                } else {
                    // Prefix with lowercase alias to avoid collisions
                    $prefixKey = strtolower($prefix) . '.' . $key;
                    $result[]  = $prefixKey;
                }
            }
        }

        return array_unique($result);
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
