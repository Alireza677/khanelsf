<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPickerService;
use App\CMS\Blocks\BlockEditorSaveManager;
use App\CMS\Blocks\FeatureGrid\FeatureGridBlock;
use App\CMS\Blocks\FeatureGrid\FeatureGridDataNormalizer;
use App\CMS\Blocks\FeatureGrid\FeatureGridRuntime;
use App\CMS\Blocks\FeatureGrid\FeatureGridVideoPicker;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Collections\Video\VideoCollectionAdapter;
use App\Contracts\VideoProviderAdapter;
use App\Models\Post;
use App\Models\Project;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Services\SettingsService;
use App\Services\Video\AparatVideoProvider;
use App\Services\VideoQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FeatureGridVideoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '2026_05_30_000000_create_cms_tables.php',
            '2026_05_30_000001_create_media_table.php',
            '2026_06_11_000003_create_project_tables.php',
            '2026_08_26_000001_create_media_usages_table.php',
            '2026_10_21_000000_create_video_library_tables.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $real = new AparatVideoProvider;
        $this->mock(VideoProviderAdapter::class, function ($mock) use ($real): void {
            $mock->shouldReceive('parseUrl')->andReturnUsing($real->parseUrl(...));
            $mock->shouldReceive('trustedThumbnailUrl')->andReturnUsing($real->trustedThumbnailUrl(...));
            $mock->shouldReceive('embedUrl')->andReturnUsing($real->embedUrl(...));
            $mock->shouldReceive('fetchMetadata')->andReturn([]);
        });
        Http::fake();
        app(SettingsService::class)->set('video_library_enabled', true);
    }

    public function test_additive_normalization_is_idempotent_and_drops_injected_video_snapshots(): void
    {
        $normalizer = app(FeatureGridDataNormalizer::class);
        $data = $normalizer->normalize(['block_id' => 'stable-id', 'items' => [
            ['title' => 'Old image', 'image' => '/old.png', 'button_label' => 'More', 'button_url' => '/old'],
            ['media_type' => 'video', 'video_id' => '4', 'embed_url' => 'https://evil.test', 'video' => ['title' => 'Snapshot']],
        ]]);
        $this->assertSame('stable-id', $data['block_id']);
        $this->assertSame(1, $data['schema_version']);
        $this->assertSame('image', $data['content']['items'][0]['media_type']);
        $this->assertSame('/old.png', $data['content']['items'][0]['image']);
        $this->assertSame(4, $data['content']['items'][1]['video_id']);
        $this->assertArrayNotHasKey('video', $data['content']['items'][1]);
        $this->assertArrayNotHasKey('embed_url', $data['content']['items'][1]);
        $this->assertSame($data, $normalizer->normalize($data));
    }

    public function test_static_references_resolve_in_one_batch_and_custom_text_survives_library_changes(): void
    {
        $video = $this->video('shared');
        $data = ['items' => [
            ['title' => 'Image', 'image' => '/old.png'],
            ['media_type' => 'video', 'video_id' => $video->id],
            ['media_type' => 'video', 'video_id' => $video->id, 'video_text_mode' => 'custom', 'title' => 'Custom', 'description' => 'Custom excerpt'],
        ]];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $grid = app(FeatureGridRuntime::class)->prepare($data);
        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "videos"'));
        $this->assertCount(1, $queries);
        $this->assertSame('shared', $grid['items'][1]['title']);
        $video->update(['title' => 'New title', 'excerpt' => 'New excerpt']);
        $grid = app(FeatureGridRuntime::class)->prepare($data);
        $this->assertSame('New title', $grid['items'][1]['title']);
        $this->assertSame('New excerpt', $grid['items'][1]['description']);
        $this->assertSame('Custom', $grid['items'][2]['title']);
        $this->assertSame('Custom excerpt', $grid['items'][2]['description']);
        $this->assertSame('01:05', $grid['items'][1]['video']['duration']);
        Http::assertNothingSent();
    }

    public function test_hidden_deleted_future_and_disabled_videos_fail_closed_in_public_and_preview(): void
    {
        $draft = $this->video('secret', ['status' => 'draft']);
        $future = $this->video('future', ['published_at' => now()->addDay()]);
        $data = ['items' => array_map(fn ($id): array => [
            'media_type' => 'video', 'video_id' => $id, 'title' => 'Injected snapshot', 'image' => '/leaked.png',
        ], [$draft->id, $future->id, 999999])];
        $runtime = app(FeatureGridRuntime::class);
        $this->assertSame([], $runtime->prepare($data)['items']);
        $preview = $runtime->prepare($data, preview: true);
        $this->assertCount(3, $preview['items']);
        $this->assertNull($preview['items'][0]['image']);
        $this->assertArrayNotHasKey('video', $preview['items'][0]);
        $this->assertSame('ویدئو در دسترس نیست', $preview['items'][0]['title']);
        $public = $this->video('public');
        app(SettingsService::class)->set('video_library_enabled', false);
        $this->assertSame([], $runtime->prepare(['items' => [['media_type' => 'video', 'video_id' => $public->id]]])['items']);
    }

    public function test_shared_cards_render_without_queries_or_iframes_and_keep_separate_actions(): void
    {
        $video = $this->video('play');
        $grid = app(FeatureGridRuntime::class)->prepare(['items' => [
            ['title' => 'Old card', 'image' => '/old.png', 'button_label' => 'Old action', 'button_url' => '/old'],
            ['media_type' => 'video', 'video_id' => $video->id, 'button_label' => 'Related', 'button_url' => '/related'],
        ]]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $html = view('partials.blocks.feature-grid.cards', compact('grid'))->render();
        $this->assertSame([], DB::getQueryLog());
        $this->assertStringContainsString('data-feature-video-play', $html);
        $this->assertStringContainsString('href="/related"', $html);
        $this->assertStringContainsString('src="/old.png"', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        Http::assertNothingSent();
        foreach ([HeroBlock::CONTEXT_PAGE, HeroBlock::CONTEXT_TEMPLATE] as $context) {
            $schema = app(FeatureGridBlock::class)->filamentSchema($context);
            $repeater = collect($schema)->first(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'content.items');
            $names = collect($repeater->getChildComponents())->map(fn ($field): ?string => method_exists($field, 'getName') ? $field->getName() : null);
            $this->assertContains('video_id', $names);
            $this->assertContains('video_text_mode', $names);
        }
    }

    public function test_dynamic_videos_apply_category_limit_and_all_sort_orders_without_exposing_hidden_records(): void
    {
        $category = VideoCategory::create(['name' => 'Training', 'slug' => 'training']);
        $older = $this->video('older', ['sort_order' => 1]);
        $older->forceFill(['created_at' => now()->subDays(2)])->save();
        $newer = $this->video('newer', ['sort_order' => 2]);
        $older->categories()->attach($category);
        $newer->categories()->attach($category);
        $this->video('other');
        $this->video('draft', ['status' => 'draft'])->categories()->attach($category);
        $this->video('future', ['published_at' => now()->addDay()])->categories()->attach($category);
        $runtime = app(FeatureGridRuntime::class);
        foreach (['newest' => [$newer->id, $older->id], 'oldest' => [$older->id, $newer->id], 'manual' => [$older->id, $newer->id]] as $sort => $expected) {
            $grid = $runtime->prepare([
                'content' => ['items_mode' => 'dynamic', 'dynamic_source' => 'videos', 'video_category_id' => $category->id, 'video_sort' => $sort],
                'settings' => ['video_limit' => 2],
            ]);
            $this->assertSame($expected, array_column($grid['items'], 'video_id'));
        }
        $limited = $runtime->prepare(['content' => ['items_mode' => 'dynamic', 'dynamic_source' => 'videos'], 'settings' => ['video_limit' => 1]]);
        $this->assertCount(1, $limited['items']);
        $newer->update(['published_at' => now()->subDays(3)]);
        $this->assertSame([$older->id, $newer->id], app(VideoQueryService::class)->featureGridQuery($category->id)->pluck('id')->all());
        app(SettingsService::class)->set('video_library_enabled', false);
        $unavailable = $runtime->prepare(['items_mode' => 'dynamic', 'dynamic_source' => 'videos'], preview: true);
        $this->assertSame([], $unavailable['items']);
        $this->assertNotEmpty($unavailable['notice']);
    }

    public function test_archive_uses_the_same_card_without_queries_or_iframes_and_preserves_pagination(): void
    {
        foreach (['one', 'two', 'three'] as $id) {
            $this->video($id);
        }
        $paginator = app(VideoQueryService::class)->archiveQuery()->paginate(2);
        $archive = app(VideoCollectionAdapter::class)->adapt($paginator, 'Videos', 'Description');
        $this->assertCount(2, $archive->collection->items);
        $this->assertSame(2, $archive->collection->pagination->lastPage);
        $this->assertSame($paginator->nextPageUrl(), $archive->collection->pagination->nextUrl);
        $this->assertArrayNotHasKey('video', get_object_vars($archive->collection->items[0]));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $html = view('videos.partials.archive', compact('archive'))->render();
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame(2, substr_count($html, 'data-feature-video-play'));
        $this->assertStringContainsString('shared-collection__pagination', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        Http::assertNothingSent();
    }

    public function test_shared_block_runtime_supports_all_page_contexts_and_preserves_post_project_sources(): void
    {
        $video = $this->video('portable');
        $blocks = [['type' => 'feature_grid', 'data' => ['items' => [['media_type' => 'video', 'video_id' => $video->id]]]]];
        foreach (['home', 'page', 'service_single', 'project_single'] as $type) {
            $html = view('partials.page-blocks', ['blocks' => $blocks, 'context' => ['type' => $type]])->render();
            $this->assertStringContainsString('data-feature-video-play', $html);
        }
        $video->delete();
        $preview = view('partials.page-blocks', ['blocks' => $blocks, 'context' => ['isPreview' => true]])->render();
        $this->assertStringContainsString('ویدئو در دسترس نیست', $preview);
        $this->assertStringNotContainsString('data-video-embed', $preview);

        $post = Post::create(['title' => 'Post preserved', 'slug' => 'post', 'status' => 'published']);
        $project = Project::create(['title' => 'Project preserved', 'slug' => 'project', 'status' => 'published']);
        foreach (['posts' => $post->title, 'projects' => $project->title] as $source => $title) {
            $grid = app(FeatureGridRuntime::class)->prepare(['items_mode' => 'dynamic', 'dynamic_source' => $source]);
            $this->assertSame($title, $grid['items'][0]['title']);
            $this->assertArrayNotHasKey('video', $grid['items'][0]);
        }
    }

    public function test_picker_rejects_hidden_references_on_server_and_escapes_search_labels(): void
    {
        $public = $this->video('searchable', ['title' => '<b>Search title</b>']);
        $draft = $this->video('not-allowed', ['status' => 'draft']);
        $picker = FeatureGridVideoPicker::make('video_id');
        $rules = ['video_id' => $picker->getValidationRules()];
        $this->assertTrue(Validator::make(['video_id' => $public->id], $rules)->passes());
        $this->assertTrue(Validator::make(['video_id' => $draft->id], $rules)->fails());
        $rows = app(ActionPickerService::class)->browseTargets('video', 'Search')->items();
        $this->assertSame([$public->id], array_column($rows, 'reference_id'));
        // Labels are plain text; the shared browser renders them with x-text, never HTML.
        $this->assertSame('<b>Search title</b>', $rows[0]['title']);
        $this->assertSame('video', $picker->getTargetType());
        app(SettingsService::class)->set('video_library_enabled', false);
        $this->assertSame([], app(ActionPickerService::class)->browseTargets('video')->items());
        $this->assertTrue(Validator::make(['video_id' => $public->id], $rules)->fails());
    }

    public function test_editor_save_paths_preserve_identity_and_reference_without_fetching_or_snapshotting_video(): void
    {
        $blocks = [['type' => 'feature_grid', 'data' => [
            'block_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'items' => [[
                'media_type' => 'video', 'video_id' => 123, 'video_text_mode' => 'custom',
                'title' => 'Custom title', 'description' => 'Custom description', 'embed_url' => 'https://evil.test',
            ]],
        ]]];
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ([false, true] as $useHeroV2) {
            $saved = app(BlockEditorSaveManager::class)->prepare($blocks, $useHeroV2);
            $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $saved[0]['data']['block_id']);
            $this->assertSame(1, $saved[0]['data']['schema_version']);
            $item = $saved[0]['data']['content']['items'][0];
            $this->assertSame(123, $item['video_id']);
            $this->assertSame('Custom title', $item['title']);
            $this->assertArrayNotHasKey('embed_url', $item);
        }
        $this->assertSame([], DB::getQueryLog());
        Http::assertNothingSent();
    }

    private function video(string $id, array $attributes = []): Video
    {
        return Video::create(array_merge([
            'title' => $id, 'slug' => $id, 'excerpt' => 'Excerpt', 'source_url' => 'https://aparat.com/v/'.$id,
            'status' => 'published', 'duration_seconds' => 65,
        ], $attributes));
    }
}
