<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\SeoService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoIndexAdminTablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public static function contentResources(): array
    {
        return [
            'pages' => [Page::class, ListPages::class, EditPage::class],
            'posts' => [Post::class, ListPosts::class, EditPost::class],
            'projects' => [Project::class, ListProjects::class, EditProject::class],
            'products' => [Product::class, ListProducts::class, EditProduct::class],
        ];
    }

    #[DataProvider('contentResources')]
    public function test_badges_filters_and_sorting_follow_the_stored_preference(string $model, string $list, string $edit): void
    {
        $table = (new $model)->getTable();
        // The shipped schema is NOT NULL. Simulate a legacy nullable schema only in :memory:.
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->boolean('robots_index')->nullable()->default(true)->change());

        $indexDraft = $model::factory()->create(['robots_index' => true, 'robots_follow' => false, 'status' => 'draft']);
        $indexPublished = $model::factory()->published()->create(['robots_index' => true, 'robots_follow' => true]);
        $noindexDraft = $model::factory()->create(['robots_index' => false, 'robots_follow' => true, 'status' => 'draft']);
        $noindexPublished = $model::factory()->published()->create(['robots_index' => false, 'robots_follow' => false]);
        $legacy = $model::factory()->create(['robots_index' => null]);
        // Omit the preference at insertion to exercise the existing database default.
        $attributes = $model::factory()->raw();
        unset($attributes['robots_index']);
        $default = $model::query()->create($attributes)->fresh();
        $this->assertTrue($default->robots_index);

        $component = Livewire::test($list)
            ->assertTableColumnExists('robots_index', fn (TextColumn $column): bool => $column->getLabel() === 'ایندکس' && $column->isBadge() && $column->isSortable() && $column->isToggleable())
            ->assertCanRenderTableColumn('robots_index')
            ->assertTableColumnStateSet('robots_index', true, $indexDraft)
            ->assertTableColumnStateSet('robots_index', true, $legacy)
            ->assertTableColumnFormattedStateSet('robots_index', 'Index', $default)
            ->assertTableColumnFormattedStateSet('robots_index', 'Noindex', $noindexPublished)
            ->assertTableColumnExists('robots_index', fn (TextColumn $column): bool => $column->getColor($column->getState()) === 'success', $indexDraft)
            ->assertTableColumnExists('robots_index', fn (TextColumn $column): bool => $column->getColor($column->getState()) === 'danger', $noindexDraft)
            ->assertCountTableRecords(6);

        $columns = array_keys($component->instance()->getTable()->getColumns());
        $this->assertSame('robots_index', $columns[array_search('status', $columns, true) + 1]);

        $component->filterTable('robots_index', '1')
            ->assertCanSeeTableRecords([$indexDraft, $indexPublished, $legacy, $default])
            ->assertCanNotSeeTableRecords([$noindexDraft, $noindexPublished])
            ->assertCountTableRecords(4)
            ->filterTable('status', 'draft')
            ->assertCanNotSeeTableRecords([$indexPublished, $noindexDraft, $noindexPublished])
            ->removeTableFilter('status')
            ->filterTable('robots_index', '0')
            ->assertCanSeeTableRecords([$noindexDraft, $noindexPublished])
            ->assertCountTableRecords(2)
            ->removeTableFilter('robots_index')
            ->assertCountTableRecords(6);

        foreach (['asc' => [false, false, true, true, true, true], 'desc' => [true, true, true, true, false, false]] as $direction => $expected) {
            $component->sortTable('robots_index', $direction);
            $this->assertSame($expected, $component->instance()->getTableRecords()->map(fn ($record): bool => $record->robots_index ?? true)->values()->all());
        }

        $this->assertSame('index, follow', app(SeoService::class)->make(['robots_index' => $legacy->robots_index])->robots);
    }

    #[DataProvider('contentResources')]
    public function test_filter_composes_with_search_other_filters_and_pagination(string $model, string $list, string $edit): void
    {
        $matches = $model::factory()->count(13)->create(['title' => 'Needle indexed', 'robots_index' => true, 'status' => 'draft']);
        $excluded = $model::factory()->create(['title' => 'Needle excluded', 'robots_index' => false, 'status' => 'draft']);
        $model::factory()->published()->create(['title' => 'Needle published', 'robots_index' => true]);
        $model::factory()->create(['title' => 'Different title', 'robots_index' => true, 'status' => 'draft']);

        $component = Livewire::test($list)
            ->searchTable('Needle')
            ->filterTable('status', 'draft')
            ->filterTable('robots_index', '1')
            ->sortTable('id')
            ->set('tableRecordsPerPage', 10)
            ->assertCountTableRecords(13)
            ->assertCanSeeTableRecords($matches->take(10))
            ->assertCanNotSeeTableRecords([$excluded, ...$matches->skip(10)])
            ->call('gotoPage', 2, 'page')
            ->assertCanSeeTableRecords($matches->skip(10));

        $component->filterTable('robots_index', '0')
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$excluded]);
    }

    public static function storedPreferences(): array
    {
        $cases = [];
        foreach (self::contentResources() as $name => $resource) {
            foreach (['index' => true, 'noindex' => false, 'legacy' => null] as $preference => $value) {
                $cases[$name.' '.$preference] = [...$resource, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('storedPreferences')]
    public function test_edit_preserves_seo_on_unrelated_save_and_reflects_explicit_changes(string $model, string $list, string $edit, ?bool $preference): void
    {
        if ($preference === null) {
            Schema::table((new $model)->getTable(), fn (Blueprint $table) => $table->boolean('robots_index')->nullable()->default(true)->change());
        }
        $record = $model::factory()->create(['robots_index' => $preference, 'robots_follow' => false, 'status' => 'draft']);

        $component = Livewire::test($edit, ['record' => $record->getRouteKey()])
            ->assertFormSet(['robots_index' => $preference ?? true, 'robots_follow' => false])
            ->fillForm(['title' => 'Updated title'])
            ->call('save')
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertSame('Updated title', $record->title);
        $this->assertSame($preference, $record->robots_index);
        $this->assertFalse($record->robots_follow);

        $newPreference = ! ($preference ?? true);
        $component->fillForm(['robots_index' => $newPreference])->call('save')->assertHasNoFormErrors();
        $record->refresh();
        $this->assertSame($newPreference, $record->robots_index);
        $this->assertFalse($record->robots_follow);
        $this->assertSame('draft', $record->status);
        Livewire::test($list)->assertTableColumnFormattedStateSet('robots_index', $newPreference ? 'Index' : 'Noindex', $record);
    }

    public function test_services_keep_the_existing_publication_based_runtime_without_a_preference_ui(): void
    {
        $this->assertFalse(Schema::hasColumn('services', 'robots_index'));
        $component = Livewire::test(ListServices::class)->assertTableColumnDoesNotExist('robots_index');
        $this->assertNull($component->instance()->getTable()->getFilter('robots_index'));

        foreach ([Service::STATUS_DRAFT => 'noindex, nofollow', Service::STATUS_ACTIVE => 'index, follow', Service::STATUS_PUBLISHED => 'index, follow'] as $status => $robots) {
            $service = Service::query()->create(['name' => $status, 'slug' => $status, 'status' => $status]);
            $this->assertSame($robots, app(SeoService::class)->forService($service)->robots);
        }
    }
}
