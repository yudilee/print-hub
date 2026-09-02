<?php

namespace Tests\Unit;

use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use App\Services\ContinuousFormEngine;
use App\Services\PrintJobOrchestrator;
use Tests\TestCase;

class WatermarkCustomizationTest extends TestCase
{
    public function test_hex_to_rgb_conversion()
    {
        $engine = new ContinuousFormEngine();
        $reflector = new \ReflectionClass($engine);
        $method = $reflector->getMethod('hexToRgb');
        $method->setAccessible(true);

        $this->assertEquals([255, 0, 0], $method->invoke($engine, '#FF0000'));
        $this->assertEquals([0, 128, 255], $method->invoke($engine, '0080FF'));
        $this->assertEquals([180, 180, 180], $method->invoke($engine, 'invalid'));
    }

    public function test_resolve_watermark_text_placeholders()
    {
        $engine = new ContinuousFormEngine();
        $reflector = new \ReflectionClass($engine);

        $dataProperty = $reflector->getProperty('data');
        $dataProperty->setAccessible(true);
        $dataProperty->setValue($engine, [
            'branch_name'  => 'SDP Jakarta',
            'company_name' => 'PT Hartono Motor',
            'invoice_no'   => 'INV-2026-001',
        ]);

        $method = $reflector->getMethod('resolveWatermarkText');
        $method->setAccessible(true);

        $resolved = $method->invoke($engine, 'COPY {copy_number} - {branch_name} ({date})', 1);
        $expectedDate = now()->format('d-M-Y');

        $this->assertEquals("COPY 2 - SDP Jakarta ({$expectedDate})", $resolved);

        $customField = $method->invoke($engine, 'INVOICE: {invoice_no}', 0);
        $this->assertEquals('INVOICE: INV-2026-001', $customField);
    }

    public function test_profile_options_merge_watermark_customization()
    {
        $profile = new PrintProfile([
            'name'                  => 'Custom Watermark Queue',
            'paper_size'            => 'A4',
            'orientation'           => 'portrait',
            'copies'                => 1,
            'watermark_text'        => 'DRAFT COPY',
            'watermark_color'       => '#E11D48',
            'watermark_font_size'   => 48,
            'watermark_font_family' => 'Helvetica',
            'watermark_font_style'  => 'BI',
            'watermark_transparency'=> 0.15,
        ]);

        $options = PrintJobOrchestrator::mergeProfileOptions($profile, []);

        $this->assertEquals('DRAFT COPY', $options['watermark_text']);
        $this->assertEquals('#E11D48', $options['watermark_color']);
        $this->assertEquals(48, $options['watermark_font_size']);
        $this->assertEquals('Helvetica', $options['watermark_font_family']);
        $this->assertEquals('BI', $options['watermark_font_style']);
        $this->assertEquals(0.15, $options['watermark_transparency']);
    }
}
