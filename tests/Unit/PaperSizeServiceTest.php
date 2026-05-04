<?php

use App\Services\PaperSizeService;
use Tests\TestCase;

class PaperSizeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('paper.sizes', [
            'A4'     => [210.0, 297.0],
            'A5'     => [148.0, 210.0],
            'Letter' => [215.9, 279.4],
            'Legal'  => [215.9, 355.6],
            'F4'     => [210.0, 330.0],
        ]);
    }

    public function test_get_size_in_mm_returns_correct_dimensions()
    {
        $this->assertSame([210.0, 297.0], PaperSizeService::getSizeInMm('A4'));
        $this->assertSame([148.0, 210.0], PaperSizeService::getSizeInMm('A5'));
        $this->assertNull(PaperSizeService::getSizeInMm('Unknown'));
    }

    public function test_get_width_and_height_return_correct_values()
    {
        $this->assertEquals(210.0, PaperSizeService::getWidth('A4'));
        $this->assertEquals(297.0, PaperSizeService::getHeight('A4'));
        $this->assertNull(PaperSizeService::getWidth('Unknown'));
    }

    public function test_exists_returns_true_for_known_sizes()
    {
        $this->assertTrue(PaperSizeService::exists('A4'));
        $this->assertFalse(PaperSizeService::exists('Unknown'));
    }

    public function test_all_returns_known_sizes()
    {
        $all = PaperSizeService::all();
        $this->assertContains('A4', $all);
        $this->assertContains('Letter', $all);
        $this->assertContains('F4', $all);
    }

    public function test_resolve_from_profile_returns_custom_dimensions()
    {
        $profile = new \App\Models\PrintProfile([
            'is_custom'     => true,
            'custom_width'  => 100.0,
            'custom_height' => 200.0,
        ]);

        $result = PaperSizeService::resolveFromProfile($profile);
        $this->assertSame(['width_mm' => 100.0, 'height_mm' => 200.0], $result);
    }

    public function test_resolve_from_profile_returns_standard_dimensions()
    {
        $profile = new \App\Models\PrintProfile([
            'is_custom'  => false,
            'paper_size' => 'A4',
        ]);

        $result = PaperSizeService::resolveFromProfile($profile);
        $this->assertSame(['width_mm' => 210.0, 'height_mm' => 297.0], $result);
    }

    public function test_resolve_from_profile_falls_back_to_a4()
    {
        $profile = new \App\Models\PrintProfile([
            'is_custom'  => false,
            'paper_size' => 'Banana',
        ]);

        $result = PaperSizeService::resolveFromProfile($profile);
        $this->assertSame(['width_mm' => 210.0, 'height_mm' => 297.0], $result);
    }
}
