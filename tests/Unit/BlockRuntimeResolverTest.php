<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use Tests\TestCase;

class BlockRuntimeResolverTest extends TestCase
{
    public function test_runtime_uses_registered_block_template_view_before_convention(): void
    {
        $this->app->instance(BlockRegistry::class, new BlockRegistry($this->app, [
            'runtime_probe' => RegisteredRuntimeProbeBlock::class,
        ]));

        $html = view('partials.page-blocks', [
            'blocks' => [[
                'type' => 'runtime_probe',
                'data' => ['code' => '<p>Registry renderer output</p>'],
            ]],
        ])->render();

        $this->assertStringContainsString('<p>Registry renderer output</p>', $html);
    }

    public function test_unregistered_legacy_block_keeps_convention_fallback(): void
    {
        $html = view('partials.page-blocks', [
            'blocks' => [[
                'type' => 'custom_html',
                'data' => ['code' => '<p>Legacy convention output</p>'],
            ]],
        ])->render();

        $this->assertStringContainsString('<p>Legacy convention output</p>', $html);
    }

    public function test_missing_registered_view_falls_back_to_legacy_convention(): void
    {
        $this->app->instance(BlockRegistry::class, new BlockRegistry($this->app, [
            'custom_html' => MissingRuntimeViewBlock::class,
        ]));

        $html = view('partials.page-blocks', [
            'blocks' => [[
                'type' => 'custom_html',
                'data' => ['code' => '<p>Safe registered fallback</p>'],
            ]],
        ])->render();

        $this->assertStringContainsString('<p>Safe registered fallback</p>', $html);
    }
}

class RegisteredRuntimeProbeBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'runtime_probe';
    }

    public function label(): string
    {
        return 'Runtime probe';
    }

    public function templates(): array
    {
        return [
            'default' => new BlockTemplate('default', 'Default', 'partials.blocks.custom_html'),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'default';
    }

    public function filamentSchema(string $context): array
    {
        return [];
    }
}

final class MissingRuntimeViewBlock extends RegisteredRuntimeProbeBlock
{
    public function key(): string
    {
        return 'custom_html';
    }

    public function templates(): array
    {
        return [
            'default' => new BlockTemplate('default', 'Missing', 'partials.blocks.missing_runtime_view'),
        ];
    }
}
