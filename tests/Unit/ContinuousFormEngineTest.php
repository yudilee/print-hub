<?php

use App\Models\PrintTemplate;
use App\Services\ContinuousFormEngine;
use Tests\TestCase;

class ContinuousFormEngineTest extends TestCase
{
    public function test_engine_generates_pdf_for_simple_label()
    {
        $template = new PrintTemplate([
            'name'           => 'test',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'elements'       => [
                ['type' => 'label', 'text' => 'Hello World', 'x' => 10, 'y' => 10, 'font_size' => 12],
            ],
        ]);

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, []);

        $this->assertIsString($pdf);
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_renders_field_values_from_data()
    {
        $template = new PrintTemplate([
            'name'           => 'test',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'elements'       => [
                ['type' => 'field', 'key' => 'customer.name', 'x' => 20, 'y' => 20, 'font_size' => 10],
            ],
        ]);

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, ['customer' => ['name' => 'John Doe']]);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_renders_tables_with_multiple_pages()
    {
        $template = new PrintTemplate([
            'name'           => 'test',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 100,
            'elements'       => [
                [
                    'type'       => 'table',
                    'key'        => 'items',
                    'x'          => 10,
                    'y'          => 10,
                    'row_height' => 6,
                    'columns'    => [
                        ['key' => 'name', 'label' => 'Name', 'width' => 50],
                        ['key' => 'qty', 'label' => 'Qty', 'width' => 30],
                    ],
                ],
            ],
        ]);

        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['name' => "Item {$i}", 'qty' => $i];
        }

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, ['items' => $rows]);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_handles_empty_data_gracefully()
    {
        $template = new PrintTemplate([
            'name'           => 'test',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'elements'       => [],
        ]);

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, []);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_renders_lines()
    {
        $template = new PrintTemplate([
            'name'           => 'test',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'elements'       => [
                ['type' => 'line', 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 0.5, 'lineColor' => '#FF0000'],
            ],
        ]);

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, []);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_respects_custom_paper_size_from_options()
    {
        $template = new PrintTemplate([
            'name'           => 'test',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'elements'       => [
                ['type' => 'label', 'text' => 'Test', 'x' => 10, 'y' => 10],
            ],
        ]);

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, [], [
            'paper_width_mm'  => 100,
            'paper_height_mm' => 150,
            'orientation'     => 'portrait',
        ]);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_applies_style_index_to_elements()
    {
        $template = new PrintTemplate([
            'name'           => 'test',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'styles'         => [
                ['font_size' => 14, 'bold' => true],
            ],
            'elements'       => [
                ['type' => 'label', 'text' => 'Styled', 'x' => 10, 'y' => 10, 'styleIdx' => 0],
            ],
        ]);

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, []);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_renders_new_components()
    {
        $template = new PrintTemplate([
            'name'           => 'test_new_components',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'elements'       => [
                ['type' => 'rectangle', 'x' => 10, 'y' => 10, 'width' => 190, 'height' => 40, 'border' => true, 'fillColor' => '#f8fafc'],
                ['type' => 'text_block', 'x' => 15, 'y' => 15, 'width' => 100, 'height' => 20, 'text' => "Terms & Conditions:\n1. Payment within 30 days.\n2. Goods received in good condition."],
                ['type' => 'checkbox', 'x' => 15, 'y' => 38, 'size' => 4, 'key' => 'is_paid', 'checkedValue' => '1', 'label' => 'Paid in Full'],
                ['type' => 'expression', 'x' => 120, 'y' => 15, 'width' => 50, 'height' => 8, 'expression' => '{subtotal} * 0.11', 'prefix' => 'PPN: Rp '],
                ['type' => 'page_number', 'x' => 120, 'y' => 35, 'width' => 50, 'height' => 6, 'format' => 'Page {page} of {pages}', 'align' => 'R'],
            ],
        ]);

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, [
            'is_paid' => 1,
            'subtotal' => 1000000,
        ]);

        $this->assertIsString($pdf);
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_engine_renders_table_with_summary_rows()
    {
        $template = new PrintTemplate([
            'name'           => 'test_table_summaries',
            'paper_width_mm' => 210,
            'paper_height_mm'=> 297,
            'elements'       => [
                [
                    'type'       => 'table',
                    'key'        => 'items',
                    'x'          => 10,
                    'y'          => 10,
                    'row_height' => 6,
                    'columns'    => [
                        ['key' => 'name', 'label' => 'Item Description', 'width' => 80],
                        ['key' => 'qty', 'label' => 'Qty', 'width' => 30, 'align' => 'R'],
                        ['key' => 'price', 'label' => 'Price', 'width' => 40, 'align' => 'R'],
                        ['key' => 'total', 'label' => 'Total', 'width' => 40, 'align' => 'R'],
                    ],
                    'summaryRows' => [
                        ['label' => 'Subtotal:', 'expression' => 'SUM(total)', 'colspan' => 3, 'align' => 'R'],
                        ['label' => 'PPN (11%):', 'expression' => 'SUM(total) * 0.11', 'colspan' => 3, 'align' => 'R'],
                        ['label' => 'Grand Total:', 'expression' => 'SUM(total) * 1.11', 'colspan' => 3, 'align' => 'R', 'bold' => true],
                    ],
                ],
            ],
        ]);

        $rows = [
            ['name' => 'Widget A', 'qty' => 2, 'price' => 15000, 'total' => 30000],
            ['name' => 'Widget B', 'qty' => 1, 'price' => 20000, 'total' => 20000],
        ];

        $engine = new ContinuousFormEngine();
        $pdf = $engine->generate($template, ['items' => $rows]);

        $this->assertIsString($pdf);
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
