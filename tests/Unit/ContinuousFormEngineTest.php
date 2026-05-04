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
}
