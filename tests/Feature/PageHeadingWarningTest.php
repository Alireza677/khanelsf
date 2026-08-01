<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PageHeadingWarningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cms.hero_v2_editor', false);
        config()->set('cms.hero_v2_editor_runtime', null);
        $this->actingAs(User::factory()->create());
    }

    public function test_page_with_two_visible_h1_heroes_saves_and_generates_a_warning(): void
    {
        $page = Page::factory()->create([
            'title' => 'Before save',
            'blocks' => [$this->hero('First hero', 'h1'), $this->hero('Second hero', 'h1')],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Saved with warning'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('هشدار ساختار سئو');

        $this->assertSame('Saved with warning', $page->fresh()->title);
        $this->assertCount(2, $page->fresh()->blocks);
    }

    public function test_page_with_one_visible_h1_saves_without_an_seo_warning(): void
    {
        $page = Page::factory()->create([
            'title' => 'Before save',
            'blocks' => [$this->hero('Primary hero', 'h1'), $this->hero('Secondary hero', 'h2')],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Saved without warning'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotNotified('هشدار ساختار سئو');

        $this->assertSame('Saved without warning', $page->fresh()->title);
    }

    private function hero(string $title, string $headingTag): array
    {
        return [
            'type' => 'hero',
            'data' => [
                'block_id' => (string) Str::ulid(),
                'template' => 'default',
                'title' => $title,
                'heading_tag' => $headingTag,
            ],
        ];
    }
}
