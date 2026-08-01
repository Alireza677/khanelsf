<?php

namespace Tests\Unit;

use App\CMS\Blocks\SiteHeader\SiteHeaderDataNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteHeaderDataNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_produces_an_idempotent_query_free_canonical_header(): void
    {
        $input = [
            'block_id' => '01JHEADER00000000000000000',
            'content' => [
                'top_actions' => [
                    [
                        'label' => ' پشتیبانی ',
                        'action' => [
                            'type' => 'page',
                            'reference_id' => '12',
                        ],
                    ],
                    [
                        'label' => 'همکاری',
                        'action' => [
                            'type' => 'custom_url',
                            'value' => 'javascript:alert(1)',
                        ],
                    ],
                ],
                'primary_action' => [
                    'label' => 'برآورد',
                    'action' => [
                        'type' => 'form',
                        'reference_id' => 8,
                        'display' => 'modal',
                    ],
                ],
            ],
            'settings' => [
                'menu_id' => '5',
                'search_enabled' => '1',
                'sticky_enabled' => false,
                'top_bar_enabled' => true,
            ],
        ];
        DB::enableQueryLog();
        $once = app(SiteHeaderDataNormalizer::class)->normalize($input);
        $twice = app(SiteHeaderDataNormalizer::class)->normalize($once);

        $this->assertSame($once, $twice);
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame(1, $once['schema_version']);
        $this->assertSame('industrial-header-v1', $once['template']);
        $this->assertSame('پشتیبانی', $once['content']['top_actions'][0]['label']);
        $this->assertSame(12, $once['content']['top_actions'][0]['action']['reference_id']);
        $this->assertNull($once['content']['top_actions'][1]['action']);
        $this->assertSame('modal', $once['content']['primary_action']['action']['display']);
        $this->assertSame(5, $once['settings']['menu_id']);
        $this->assertTrue($once['settings']['search_enabled']);
        $this->assertFalse($once['settings']['sticky_enabled']);
    }

    public function test_default_persian_labels_and_safe_settings_are_applied(): void
    {
        $header = app(SiteHeaderDataNormalizer::class)->normalize([]);

        $this->assertSame([
            'خدمات و پشتیبانی',
            'همکاری با ما',
        ], array_column($header['content']['top_actions'], 'label'));
        $this->assertSame(
            'محاسبه هزینه ساخت',
            $header['content']['primary_action']['label'],
        );
        $this->assertNull($header['settings']['menu_id']);
        $this->assertTrue($header['settings']['search_enabled']);
        $this->assertTrue($header['settings']['sticky_enabled']);
        $this->assertTrue($header['settings']['top_bar_enabled']);
    }
}
