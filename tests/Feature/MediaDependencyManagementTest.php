<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\TemplateResource;
use App\Media\MediaDependencyService;
use App\Media\MediaInUseException;
use App\Models\Media;
use App\Models\MediaUsage;
use App\Models\Page;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MediaDependencyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_report_uses_real_routes_and_hides_draft_public_links(): void
    {
        $source = $this->asset('source.jpg');
        $published = Project::factory()->published()->create(['title' => 'Published consumer']);
        $draft = Project::factory()->create(['title' => 'Draft consumer', 'status' => 'draft']);
        ProjectResource::syncMediaLibraryCollection($published, 'gallery', [$source->id]);
        ProjectResource::syncFeaturedImage($draft, $source->id);
        $report = collect($this->service()->report($source))->keyBy('title');

        $this->assertCount(2, $report);
        $this->assertSame($published->resolveNavigationUrl(), $report['Published consumer']['public_url']);
        $this->assertSame(ProjectResource::getUrl('edit', ['record' => $published]), $report['Published consumer']['edit_url']);
        $this->assertNull($report['Draft consumer']['public_url']);
        $this->assertTrue($report['Draft consumer']['replaceable']);
    }

    public function test_selective_replacement_preserves_gallery_order_other_images_and_source_file(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $other = $this->asset('other.jpg');
        $first = Project::factory()->create(['title' => 'First']);
        $second = Project::factory()->create(['title' => 'Second']);
        ProjectResource::syncMediaLibraryCollection($first, 'gallery', [$other->id, $source->id]);
        ProjectResource::syncMediaLibraryCollection($second, 'gallery', [$source->id]);
        $before = Storage::disk('public')->allFiles();
        $key = collect($this->service()->report($source))->firstWhere('title', 'First')['key'];
        $this->service()->apply($source, $new->id, [$key], 'replace');

        $this->assertSame([$other->id, $new->id], $first->fresh()->galleryImages()->pluck('id')->all());
        $this->assertSame([$source->id], $second->fresh()->galleryImages()->pluck('id')->all());
        $this->assertCount(1, $this->service()->report($source));
        $this->assertSame($before, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 3);
        $this->expectException(MediaInUseException::class);
        $this->service()->apply($source, null, [], 'delete');
    }

    public function test_replace_and_delete_removes_only_the_old_master_after_all_references_are_replaced(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        foreach (range(1, 2) as $index) {
            ProjectResource::syncFeaturedImage(Project::factory()->create(), $source->id);
        }
        $keys = array_column($this->service()->report($source), 'key');
        $this->service()->apply($source, $new->id, $keys, 'replace_delete');

        $this->assertDatabaseMissing('media', ['id' => $source->id]);
        $this->assertSame(2, MediaUsage::query()->where('media_id', $new->id)->count());
        Storage::disk('public')->assertExists($new->getPathRelativeToRoot());
    }

    public function test_registered_block_contracts_replace_only_the_selected_fields_and_preserve_unrelated_data(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $blocks = [
            ['type' => 'text_image', 'data' => ['block_id' => 'text-one', 'content' => ['media_id' => $source->id, 'title' => 'Keep title', 'description' => 'Keep description']]],
            ['type' => 'hero', 'data' => ['schema_version' => 2, 'block_id' => 'hero-one', 'content' => ['media' => ['source_id' => $source->id, 'url' => $source->getUrl(), 'alt' => 'Keep alt']], 'settings' => ['alignment' => 'right']]],
            ['type' => 'template_shop_complete', 'data' => ['background_media_id' => $source->id, 'title' => 'Keep shop']],
        ];
        $page = Page::factory()->create(['blocks' => $blocks, 'content' => '<p>Unrelated</p>']);
        $report = $this->service()->report($source);
        $this->assertCount(3, $report);
        $this->assertTrue(collect($report)->every(fn ($row) => $row['replaceable']));
        $this->service()->apply($source, $new->id, array_column($report, 'key'), 'replace');
        data_set($blocks, '0.data.content.media_id', $new->id);
        data_set($blocks, '1.data.content.media.source_id', $new->id);
        data_set($blocks, '1.data.content.media.url', $new->getUrl());
        data_set($blocks, '2.data.background_media_id', $new->id);
        $this->assertSame($blocks, $page->fresh()->blocks);
        $this->assertSame('<p>Unrelated</p>', $page->fresh()->content);
        $this->assertSame([], $this->service()->report($source));
    }

    public function test_html_footer_and_unrecognized_usages_are_reported_as_manual_and_block_deletion(): void
    {
        $source = $this->asset('manual.jpg');
        $page = Page::factory()->create(['content' => '<img src="'.$source->getUrl().'">', 'blocks' => [
            ['type' => 'site_footer', 'data' => ['content' => ['badges' => [['media_id' => $source->id]]]]],
        ]]);
        $report = $this->service()->report($source);
        $this->assertCount(2, $report);
        $this->assertTrue(collect($report)->every(fn ($row) => ! $row['replaceable']));
        try {
            $this->service()->apply($source, null, [], 'delete');
            $this->fail('Manual references must block deletion.');
        } catch (MediaInUseException) {
            $this->assertSame($page->content, $page->fresh()->content);
            $this->assertDatabaseHas('media', ['id' => $source->id]);
        }
    }

    public function test_gallery_collision_rolls_back_without_merging_or_reordering(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $project = Project::factory()->create();
        ProjectResource::syncMediaLibraryCollection($project, 'gallery', [$source->id, $new->id]);
        try {
            $this->service()->apply($source, $new->id, array_column($this->service()->report($source), 'key'), 'replace');
            $this->fail('Duplicate replacement must fail.');
        } catch (ValidationException) {
            $this->assertSame([$source->id, $new->id], $project->fresh()->galleryImages()->pluck('id')->all());
        }
    }

    public function test_stale_snapshot_is_rejected(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $project = Project::factory()->create();
        ProjectResource::syncMediaLibraryCollection($project, 'gallery', [$source->id]);
        $keys = array_column($this->service()->report($source), 'key');
        $project->mediaUsages()->update(['order_column' => 7]);
        $this->expectException(ValidationException::class);
        $this->service()->apply($source, $new->id, $keys, 'replace');
    }

    public function test_reference_added_during_replacement_prevents_delete_and_rolls_back_the_operation(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $project = Project::factory()->create();
        $late = Project::factory()->create();
        ProjectResource::syncFeaturedImage($project, $source->id);
        MediaUsage::updated(function (MediaUsage $usage) use ($source, $new, $late): void {
            if ((int) $usage->media_id === (int) $new->id) {
                $late->mediaUsages()->create(['media_id' => $source->id, 'collection_name' => 'featured_image', 'order_column' => 0]);
            }
        });
        try {
            $this->service()->apply($source, $new->id, array_column($this->service()->report($source), 'key'), 'replace_delete');
            $this->fail('Late dependency must prevent delete.');
        } catch (MediaInUseException) {
            $this->assertSame($source->id, $project->fresh()->featuredImage()->id);
            $this->assertSame(0, $late->mediaUsages()->count());
            $this->assertDatabaseHas('media', ['id' => $source->id]);
            Storage::disk('public')->assertExists($source->getPathRelativeToRoot());
        }
    }

    public function test_permissions_redact_content_and_reject_all_selected_changes_atomically(): void
    {
        Gate::policy(Project::class, MediaConsumerTestPolicy::class);
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $allowed = Project::factory()->published()->create(['title' => 'Allowed']);
        $denied = Project::factory()->published()->create(['title' => 'Secret']);
        ProjectResource::syncFeaturedImage($allowed, $source->id);
        ProjectResource::syncFeaturedImage($denied, $source->id);
        $report = $this->service()->report($source);
        $hidden = collect($report)->firstWhere('replaceable', false);
        $this->assertStringNotContainsString('Secret', json_encode($report));
        $this->assertNull($hidden['edit_url']);
        $this->assertNull($hidden['public_url']);
        try {
            $this->service()->apply($source, $new->id, array_column($report, 'key'), 'replace');
            $this->fail('Unauthorized consumer must reject entire selection.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame($source->id, $allowed->fresh()->featuredImage()->id);
            $this->assertSame($source->id, $denied->fresh()->featuredImage()->id);
        }
    }

    public function test_invalid_usage_is_not_silently_removed_and_foreign_key_still_protects_the_master(): void
    {
        $source = $this->asset('old.jpg');
        MediaUsage::forceCreate(['media_id' => $source->id, 'usable_type' => Project::class, 'usable_id' => 999999, 'collection_name' => 'gallery', 'order_column' => 0]);
        $this->assertFalse($this->service()->report($source)[0]['replaceable']);
        $this->assertSame('unresolved', $this->service()->report($source)[0]['status']);
        $this->assertNotNull($this->service()->report($source)[0]['diagnostic']);
        try {
            $source->delete();
            $this->fail('Invalid row must remain a controlled blocker.');
        } catch (MediaInUseException) {
            $this->assertDatabaseCount('media_usages', 1);
        }
        $this->expectException(QueryException::class);
        DB::table('media')->where('id', $source->id)->delete();
    }

    public function test_livewire_modal_requires_confirmation_and_reuses_the_shared_picker(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $project = Project::factory()->create(['title' => 'Modal consumer']);
        ProjectResource::syncFeaturedImage($project, $source->id);
        $keys = array_column($this->service()->report($source), 'key');
        $component = Livewire::test(ListMedia::class)->mountTableAction('delete', $source)
            ->assertSee('Modal consumer')->assertSee('انتخاب تصویر جایگزین');
        $component->setTableActionData(['operation' => 'replace', 'replacement_media_id' => $new->id, 'dependencies' => $keys, 'confirmed' => false])->callMountedTableAction()
            ->assertHasTableActionErrors(['confirmed']);
        $this->assertSame($source->id, $project->fresh()->featuredImage()->id);
        $component->setTableActionData(['operation' => 'replace', 'replacement_media_id' => $new->id, 'dependencies' => $keys, 'confirmed' => true])->callMountedTableAction()
            ->assertHasNoTableActionErrors();
        $this->assertSame($new->id, $project->fresh()->featuredImage()->id);
    }

    public function test_stale_editor_cannot_add_a_new_id_reference_after_media_deletion(): void
    {
        $source = $this->asset('gone.jpg');
        $source->delete();
        $this->expectException(ValidationException::class);
        Page::factory()->create(['blocks' => [['type' => 'text_image', 'data' => ['content' => ['media_id' => $source->id]]]]]);
    }

    public function test_replacement_preview_and_submission_enforce_media_view_permission(): void
    {
        Gate::policy(Media::class, MediaReplacementViewTestPolicy::class);
        $source = $this->asset('old.jpg');
        $new = $this->asset('secret-replacement.jpg');
        $project = Project::factory()->create();
        ProjectResource::syncFeaturedImage($project, $source->id);
        $keys = array_column($this->service()->report($source), 'key');
        $component = Livewire::test(ListMedia::class)->mountTableAction('delete', $source)
            ->setTableActionData(['operation' => 'replace', 'replacement_media_id' => $new->id, 'dependencies' => $keys, 'confirmed' => true]);
        $summary = $component->instance()->getMountedTableActionForm()
            ->getComponent(fn ($field) => $field instanceof Placeholder && $field->getName() === 'change_summary')
            ->getContent()->render();
        $this->assertStringNotContainsString($new->getUrl(), $summary);
        $this->assertStringContainsString('هنوز انتخاب نشده است.', $summary);
        try {
            $this->service()->apply($source, $new->id, $keys, 'replace');
            $this->fail('Unauthorized replacement must be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame($source->id, $project->fresh()->featuredImage()->id);
        }
    }

    public function test_page_and_template_consumers_use_existing_links_and_support_selective_replacement(): void
    {
        $source = $this->asset('page.jpg');
        $new = $this->asset('page-new.jpg');
        $page = Page::factory()->create(['title' => 'Page consumer', 'status' => 'published']);
        $page->mediaUsages()->create(['media_id' => $source->id, 'collection_name' => 'featured_image', 'order_column' => 0]);
        $template = Template::query()->create(['title' => 'Template consumer', 'slug' => 'media-template-consumer', 'type' => 'page', 'status' => 'draft', 'blocks' => [['type' => 'text_image', 'data' => ['content' => ['media_id' => $source->id]]]]]);
        $report = collect($this->service()->report($source))->keyBy('title');
        $this->assertSame($page->resolveNavigationUrl(), $report['Page consumer']['public_url']);
        $this->assertSame(TemplateResource::getUrl('edit', ['record' => $template]), $report['Template consumer']['edit_url']);
        $this->assertNull($report['Template consumer']['public_url']);
        $this->service()->apply($source, $new->id, $report->pluck('key')->all(), 'replace');
        $this->assertSame($new->id, $page->fresh()->featuredImage()->id);
        $this->assertSame($new->id, data_get($template->fresh()->blocks, '0.data.content.media_id'));
    }

    public function test_unexpected_failure_during_replacement_restores_all_previous_references(): void
    {
        $source = $this->asset('old.jpg');
        $new = $this->asset('new.jpg');
        $project = Project::factory()->create();
        ProjectResource::syncFeaturedImage($project, $source->id);
        MediaUsage::updated(function (MediaUsage $usage) use ($new): void {
            if ((int) $usage->media_id === (int) $new->id) {
                throw new \RuntimeException('Simulated replacement failure');
            }
        });
        try {
            $this->service()->apply($source, $new->id, array_column($this->service()->report($source), 'key'), 'replace_delete');
            $this->fail('Failure must roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated replacement failure', $exception->getMessage());
            $this->assertSame($source->id, $project->fresh()->featuredImage()->id);
            Storage::disk('public')->assertExists($source->getPathRelativeToRoot());
            $this->assertDatabaseCount('media', 2);
        }
    }

    public function test_non_admin_and_inactive_admin_cannot_inspect_or_mutate_media(): void
    {
        $source = $this->asset('private.jpg');
        foreach ([['is_admin' => false, 'status' => 'active'], ['is_admin' => true, 'status' => 'inactive']] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            try {
                $this->service()->apply($source, null, [], 'delete');
                $this->fail('Actor must be denied.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                $this->assertDatabaseHas('media', ['id' => $source->id]);
            }
        }
    }

    public function test_bulk_delete_is_all_or_nothing_when_one_asset_is_used(): void
    {
        $source = $this->asset('used.jpg');
        $unused = $this->asset('unused.jpg');
        ProjectResource::syncFeaturedImage(Project::factory()->create(), $source->id);
        try {
            $this->service()->deleteMany([$unused->id, $source->id]);
            $this->fail('Used asset must block the whole bulk operation.');
        } catch (MediaInUseException) {
            $this->assertDatabaseCount('media', 2);
            Storage::disk('public')->assertExists($source->getPathRelativeToRoot());
            Storage::disk('public')->assertExists($unused->getPathRelativeToRoot());
        }
    }

    public function test_unused_media_can_be_deleted_from_the_confirmed_table_action(): void
    {
        $source = $this->asset('unused-ui.jpg');
        Livewire::test(ListMedia::class)->callTableAction('delete', $source, data: ['operation' => 'delete', 'confirmed' => true])
            ->assertHasNoTableActionErrors();
        $this->assertDatabaseMissing('media', ['id' => $source->id]);
    }

    public function test_consumer_permissions_are_revalidated_after_mutation_before_commit(): void
    {
        Gate::policy(Project::class, MediaConsumerTestPolicy::class);
        $source = $this->asset('old-permission.jpg');
        $new = $this->asset('new-permission.jpg');
        $project = Project::factory()->create(['title' => 'Allowed']);
        ProjectResource::syncFeaturedImage($project, $source->id);
        MediaUsage::updated(function (): void {
            MediaConsumerTestPolicy::$allowUpdates = false;
        });
        try {
            $this->service()->apply($source, $new->id, array_column($this->service()->report($source), 'key'), 'replace');
            $this->fail('Revoked permission must roll back.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame($source->id, $project->fresh()->featuredImage()->id);
        } finally {
            MediaConsumerTestPolicy::$allowUpdates = true;
        }
    }

    private function service(): MediaDependencyService
    {
        return app(MediaDependencyService::class);
    }

    private function asset(string $name): Media
    {
        return auth()->user()->addMedia(UploadedFile::fake()->image($name))->toMediaCollection('media_library', 'public');
    }
}

class MediaConsumerTestPolicy
{
    public static bool $allowUpdates = true;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->title !== 'Secret';
    }

    public function update(User $user, Project $project): bool
    {
        return self::$allowUpdates && $this->view($user, $project);
    }
}

class MediaReplacementViewTestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        return $media->file_name !== 'secret-replacement.jpg';
    }

    public function delete(User $user, Media $media): bool
    {
        return true;
    }
}
