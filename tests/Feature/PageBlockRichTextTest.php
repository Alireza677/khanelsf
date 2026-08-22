<?php

namespace Tests\Feature;

use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use App\Support\RichText;
use Filament\Forms\Components\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageBlockRichTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_rich_block_text_renders_paragraphs_formatting_lists_and_links(): void
    {
        $page = Page::factory()->published()->create([
            'slug' => 'rich-block',
            'blocks' => [[
                'type' => 'stats_section',
                'data' => [
                    'section_title' => 'Rich section',
                    'section_description' => '<p>First paragraph</p><p><strong>Bold</strong> and <em>italic</em></p><ul><li>Item</li></ul><p><a href="/contact">Contact</a></p>',
                    'items' => [],
                ],
            ]],
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('<p>First paragraph</p><p><strong>Bold</strong> and <em>italic</em></p>', false)
            ->assertSee('<ul><li>Item</li></ul>', false)
            ->assertSee('<a href="/contact">Contact</a>', false);
    }

    public function test_legacy_plain_text_keeps_paragraphs_and_line_breaks_and_html_is_sanitized(): void
    {
        $legacy = RichText::render("First line\nsecond line\n\nSecond paragraph")->toHtml();
        $unsafe = RichText::render('<p>Safe</p><script>alert(1)</script>')->toHtml();

        $this->assertMatchesRegularExpression('/<p>First line<br\s*\/?>(?:\r?\n)?second line<\/p>/', $legacy);
        $this->assertStringContainsString('<p>Second paragraph</p>', $legacy);
        $this->assertStringContainsString('<p>Safe</p>', $unsafe);
        $this->assertStringNotContainsString('<script', $unsafe);
    }

    public function test_page_content_is_absent_from_authoring_ui_and_survives_edit_save(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['content' => '<p>Legacy page body</p>']);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $names = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->map(fn (Component $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

        $this->assertNotContains('content', $names);

        $component->set('data.title', 'Updated title')->call('save')->assertHasNoFormErrors();

        $this->assertSame('<p>Legacy page body</p>', $page->fresh()->content);
    }

    public function test_legacy_page_fallback_renders_and_hero_normalization_remains_idempotent(): void
    {
        $page = Page::factory()->published()->create([
            'slug' => 'legacy-page',
            'content' => '<p>Legacy fallback body</p>',
            'blocks' => null,
        ]);
        $normalizer = app(HeroDataNormalizer::class);
        $once = $normalizer->normalize([
            'template' => 'hero_2',
            'title' => 'Title',
            'description' => '<p>First</p><p>Second</p>',
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('<p>Legacy fallback body</p>', false);
        $this->assertSame($once, $normalizer->normalize($once));
    }
}
