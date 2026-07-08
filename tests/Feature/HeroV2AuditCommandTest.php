<?php

namespace Tests\Feature;

use App\CMS\Blocks\Hero\HeroV2AuditService;
use App\Models\Page;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeroV2AuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_json_audit_detects_versions_media_identity_and_templates_without_writes(): void
    {
        $page = Page::factory()->create(['blocks' => [
            ['type' => 'hero', 'data' => [
                'block_id' => self::ID, 'template' => 'hero_1', 'title' => 'Legacy',
                'image' => '/manual.jpg', 'hero_1_theme' => 'image',
            ]],
            ['type' => 'hero', 'data' => [
                'block_id' => self::ID, 'schema_version' => 2, 'template' => 'custom_future',
                'content' => [
                    'title' => 'V2',
                    'media' => ['url' => '/missing.jpg', 'source_id' => 999, 'video_url' => 'not a url', 'poster_url' => null],
                    'primary_cta' => ['label' => 'Only label', 'url' => null],
                    'selector' => ['items' => []], 'stats' => [], 'social_links' => [],
                ],
                'settings' => [],
            ]],
            ['type' => 'faq', 'data' => ['title' => 'Ignored']],
        ]]);
        $template = Template::query()->create([
            'title' => 'Audit', 'slug' => 'audit', 'type' => 'page', 'status' => 'draft',
            'blocks' => [['type' => 'hero', 'data' => [
                'schema_version' => 9, 'template' => null, 'title' => null,
            ]]],
        ]);
        $pageBefore = $page->fresh()->blocks;
        $templateBefore = $template->fresh()->blocks;
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $report = app(HeroV2AuditService::class)->audit();
        $this->assertSame(3, $report['total']);
        $this->assertSame(['legacy' => 1, 'v2' => 1, 'unknown' => 1], $report['versions']);
        $this->assertArrayHasKey('duplicate_block_id', $report['issue_counts']);
        $this->assertArrayHasKey('invalid_image_source_id', $report['issue_counts']);
        $this->assertArrayHasKey('unknown_template', $report['issue_counts']);
        $this->assertSame(3, $report['query_count']);
        $this->assertFalse($report['rollout_ready']);
        $this->assertSame('blocked', $report['rollout_status']);
        $this->assertSame(2, $report['summary']['records_scanned']);
        $this->assertSame(3, $report['summary']['hero_blocks_scanned']);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertSame($report['issues'], $report['findings']);

        $this->artisan('cms:hero-v2:audit', ['--json' => true])
            ->expectsOutputToContain('"total"')
            ->assertExitCode(1);

        $this->assertSame([], $writes);
        $this->assertSame($pageBefore, $page->fresh()->blocks);
        $this->assertSame($templateBefore, $template->fresh()->blocks);
    }

    public function test_console_audit_reports_metrics_and_ignores_non_hero_blocks(): void
    {
        Page::factory()->create(['blocks' => [
            ['type' => 'hero', 'data' => ['block_id' => self::ID, 'template' => 'default', 'title' => 'Ready']],
            ['type' => 'custom', 'data' => ['schema_version' => 99]],
        ]]);

        $this->artisan('cms:hero-v2:audit')
            ->expectsOutputToContain('Hero v2 Audit')
            ->expectsOutputToContain('Processed 1 Hero blocks')
            ->expectsOutputToContain('ROLLOUT READY')
            ->assertSuccessful();
    }

    public function test_warnings_only_do_not_block_rollout_or_fail_command(): void
    {
        Page::factory()->create(['blocks' => [[
            'type' => 'hero',
            'data' => ['template' => 'default', 'title' => 'Recoverable legacy Hero'],
        ]]]);

        $report = app(HeroV2AuditService::class)->audit();

        $this->assertSame(1, $report['warnings']);
        $this->assertSame(0, $report['critical']);
        $this->assertTrue($report['rollout_ready']);
        $this->assertGreaterThan(0, $report['summary']['warning']);
        $this->artisan('cms:hero-v2:audit', ['--json' => true])->assertSuccessful();
    }

    public function test_malformed_hero_contract_and_non_strict_schema_version_are_critical(): void
    {
        Page::factory()->create(['blocks' => [
            ['type' => 'hero', 'data' => 'not-an-array'],
            ['type' => 'hero', 'data' => [
                'block_id' => self::ID,
                'schema_version' => '2',
                'template' => 'default',
                'content' => ['title' => 'Wrong JSON type'],
            ]],
        ]]);

        $report = app(HeroV2AuditService::class)->audit();

        $this->assertSame(1, $report['versions']['legacy']);
        $this->assertSame(1, $report['versions']['unknown']);
        $this->assertSame(2, $report['critical']);
        $this->assertArrayHasKey('unexpected_contract_violation', $report['issue_counts']);
        $this->assertArrayHasKey('invalid_schema_version', $report['issue_counts']);
    }

    public function test_duplicate_identity_is_detected_across_page_and_template_documents(): void
    {
        Page::factory()->create(['blocks' => [[
            'type' => 'hero',
            'data' => ['block_id' => self::ID, 'template' => 'default', 'title' => 'Page'],
        ]]]);
        Template::query()->create([
            'title' => 'Duplicate', 'slug' => 'duplicate', 'type' => 'page', 'status' => 'draft',
            'blocks' => [[
                'type' => 'hero',
                'data' => ['block_id' => self::ID, 'template' => 'default', 'title' => 'Template'],
            ]],
        ]);

        $report = app(HeroV2AuditService::class)->audit();

        $this->assertSame(1, $report['issue_counts']['duplicate_block_id']);
        $this->assertSame('template', collect($report['issues'])->firstWhere('code', 'duplicate_block_id')['source']);
    }
}
