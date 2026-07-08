<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HeroViewLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_loading_target_is_scoped_to_each_builder_block_theme_field(): void
    {
        $first = view('filament.forms.components.hero-view-loading', [
            'getStatePath' => fn (): string => 'data.blocks.hero-one.data.hero_1_theme_loading',
        ])->render();
        $second = view('filament.forms.components.hero-view-loading', [
            'getStatePath' => fn (): string => 'data.blocks.hero-two.data.hero_1_theme_loading',
        ])->render();

        $this->assertStringContainsString(
            'wire:target="data.blocks.hero-one.data.hero_1_theme"',
            $first,
        );
        $this->assertStringContainsString('در حال اعمال نمای هیرو...', $first);
        $this->assertStringNotContainsString('hero-two', $first);
        $this->assertStringContainsString(
            'wire:target="data.blocks.hero-two.data.hero_1_theme"',
            $second,
        );
    }

    public function test_theme_sections_follow_the_same_builder_item_without_a_full_render(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create([
            'blocks' => [[
                'type' => 'hero',
                'data' => [
                    'template' => 'hero_1',
                    'hero_1_theme' => 'image',
                    'title' => 'Theme hero',
                ],
            ]],
        ]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $blocks = $component->get('data')['blocks'];
        $uuid = array_key_first($blocks);
        $statePath = "data.blocks.{$uuid}.data.hero_1_theme";

        $component
            ->assertSeeHtml("\$wire.entangle(&#039;{$statePath}&#039;)")
            ->assertSeeHtml('heroTheme === &#039;animated_dotted_surface&#039;')
            ->assertSeeHtml('heroTheme === &#039;animated_paths&#039;')
            ->set($statePath, 'animated_paths')
            ->assertSet($statePath, 'animated_paths');
    }

    public function test_dotted_theme_settings_persist_reload_and_render_on_frontend(): void
    {
        [$page, $component, $path] = $this->editableHero();

        $component
            ->set("{$path}.hero_1_theme", 'animated_dotted_surface')
            ->assertSet("{$path}.hero_1_theme", 'animated_dotted_surface')
            ->set("{$path}.animated_background_color", '#123456')
            ->set("{$path}.animated_dots_color", '#abcdef')
            ->set("{$path}.animated_background_opacity", 0.65)
            ->set("{$path}.animated_background_speed", 'fast')
            ->set("{$path}.animated_background_enabled", true)
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame('animated_dotted_surface', $saved['hero_1_theme']);
        $this->assertSame('#123456', $saved['animated_background_color']);
        $this->assertSame('#abcdef', $saved['animated_dots_color']);
        $this->assertEquals(0.65, $saved['animated_background_opacity']);
        $this->assertSame('fast', $saved['animated_background_speed']);

        $reloaded = $this->reloadedHeroData($page);
        $this->assertSame('animated_dotted_surface', $reloaded['hero_1_theme']);
        $this->assertSame('#123456', $reloaded['animated_background_color']);
        $this->assertSame('#abcdef', $reloaded['animated_dots_color']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-hero-dotted-surface', false)
            ->assertSee('data-bg-color="#123456"', false)
            ->assertSee('data-dots-color="#abcdef"', false)
            ->assertSee('data-speed="fast"', false)
            ->assertSee('data-opacity="0.65"', false);
    }

    public function test_paths_theme_settings_persist_reload_and_render_on_frontend(): void
    {
        [$page, $component, $path] = $this->editableHero();

        $component
            ->set("{$path}.hero_1_theme", 'animated_paths')
            ->assertSet("{$path}.hero_1_theme", 'animated_paths')
            ->set("{$path}.paths_background_color", '#112233')
            ->set("{$path}.paths_color", '#fedcba')
            ->set("{$path}.paths_opacity", 0.55)
            ->set("{$path}.paths_speed", 'slow')
            ->set("{$path}.paths_animation_enabled", true)
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame('animated_paths', $saved['hero_1_theme']);
        $this->assertSame('#112233', $saved['paths_background_color']);
        $this->assertSame('#fedcba', $saved['paths_color']);
        $this->assertEquals(0.55, $saved['paths_opacity']);
        $this->assertSame('slow', $saved['paths_speed']);

        $reloaded = $this->reloadedHeroData($page);
        $this->assertSame('animated_paths', $reloaded['hero_1_theme']);
        $this->assertSame('#112233', $reloaded['paths_background_color']);
        $this->assertSame('#fedcba', $reloaded['paths_color']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-hero-animated-paths', false)
            ->assertSee('--hero-paths-background: #112233', false)
            ->assertSee('stroke="#fedcba"', false)
            ->assertSee('data-speed="slow"', false);
    }

    public function test_rapid_theme_changes_persist_only_the_last_livewire_state(): void
    {
        [$page, $component, $path] = $this->editableHero();

        $component
            ->set("{$path}.hero_1_theme", 'animated_dotted_surface')
            ->set("{$path}.hero_1_theme", 'animated_paths')
            ->set("{$path}.hero_1_theme", 'light_grid')
            ->assertSet("{$path}.hero_1_theme", 'light_grid')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('light_grid', $page->fresh()->blocks[0]['data']['hero_1_theme']);
        $this->assertSame('light_grid', $this->reloadedHeroData($page)['hero_1_theme']);
    }

    private function editableHero(): array
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->published()->create([
            'slug' => 'home',
            'blocks' => [[
                'type' => 'hero',
                'data' => [
                    'template' => 'hero_1',
                    'hero_1_theme' => 'image',
                    'title' => 'Persistence hero',
                ],
            ]],
        ]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $uuid = array_key_first($component->get('data')['blocks']);

        return [$page, $component, "data.blocks.{$uuid}.data"];
    }

    private function reloadedHeroData(Page $page): array
    {
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $blocks = $component->get('data')['blocks'];

        return $blocks[array_key_first($blocks)]['data'];
    }
}
