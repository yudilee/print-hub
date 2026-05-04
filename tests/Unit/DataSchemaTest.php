<?php

use App\Models\DataSchema;
use Tests\TestCase;

class DataSchemaTest extends TestCase
{
    protected DataSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = new DataSchema([
            'schema_name' => 'test_schema',
            'version'     => 1,
            'is_latest'   => true,
            'fields'      => [
                'name'   => ['type' => 'string', 'label' => 'Name', 'required' => true],
                'amount' => ['type' => 'number', 'label' => 'Amount', 'format' => 'currency', 'currency_code' => 'IDR', 'decimal_places' => 2],
                'qty'    => ['type' => 'number', 'label' => 'Qty', 'format' => 'integer'],
                'date'   => ['type' => 'date', 'label' => 'Date', 'format' => 'dd/MM/yyyy'],
                'active' => ['type' => 'boolean', 'label' => 'Active'],
            ],
            'tables' => [
                'items' => [
                    'columns'  => [
                        'item'  => ['type' => 'string'],
                        'price' => ['type' => 'number', 'format' => 'currency'],
                        'qty'   => ['type' => 'number'],
                    ],
                    'min_rows' => 1,
                ],
            ],
        ]);
    }

    public function test_validate_data_passes_with_required_fields()
    {
        $errors = $this->schema->validateData(['name' => 'Test', 'amount' => 10000]);
        $this->assertEmpty($errors);
    }

    public function test_validate_data_fails_when_required_field_missing()
    {
        $errors = $this->schema->validateData(['amount' => 10000]);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('name', $errors[0]);
    }

    public function test_validate_data_fails_on_non_numeric_value()
    {
        $errors = $this->schema->validateData(['name' => 'Test', 'amount' => 'not-a-number']);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('numeric', $errors[0]);
    }

    public function test_validate_data_checks_table_minimum_rows()
    {
        $errors = $this->schema->validateData(['name' => 'Test', 'items' => []]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('requires at least', $errors[0]);
    }

    public function test_format_currency_idr()
    {
        $result = DataSchema::applyFormat(1000000, 'number', 'currency', ['currency_code' => 'IDR', 'decimal_places' => 2]);
        $this->assertSame('Rp 1.000.000,00', $result);
    }

    public function test_format_integer_number()
    {
        $result = DataSchema::applyFormat(1234, 'number', 'integer', []);
        $this->assertSame('1.234', $result);
    }

    public function test_format_date()
    {
        $result = DataSchema::applyFormat('2026-04-30', 'date', 'dd/MM/yyyy', []);
        $this->assertSame('30/04/2026', $result);
    }

    public function test_format_boolean()
    {
        $this->assertSame('Yes', DataSchema::applyFormat(true, 'boolean', null, []));
        $this->assertSame('No', DataSchema::applyFormat(false, 'boolean', null, []));
    }

    public function test_terbilang_converts_numbers_to_indonesian_words()
    {
        $this->assertSame('Satu', DataSchema::terbilang(1));
        $this->assertSame('Dua Belas', DataSchema::terbilang(12));
        $this->assertSame('Lima Puluh', DataSchema::terbilang(50));
        $this->assertSame('SeratusDua PuluhLima', DataSchema::terbilang(125));
        $this->assertSame('SeribuLima Ratus', DataSchema::terbilang(1500));
        $this->assertSame('', DataSchema::terbilang(0));
    }

    public function test_get_field_keys()
    {
        $keys = $this->schema->getFieldKeys();
        $this->assertContains('name', $keys);
        $this->assertContains('amount', $keys);
    }

    public function test_get_table_structure()
    {
        $tables = $this->schema->getTableStructure();
        $this->assertContains('item', $tables['items']);
        $this->assertContains('price', $tables['items']);
    }
}
