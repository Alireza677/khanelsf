<?php

namespace Tests\Feature;

use App\Media\MediaDependencyService;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Tests\TestCase;

class MediaDeletionCommitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_standard_media_cleanup_waits_for_commit_and_does_not_run_after_rollback(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $source = auth()->user()->addMedia(UploadedFile::fake()->image('commit.jpg'))->toMediaCollection('media_library', 'public');
        $path = $source->getPathRelativeToRoot();

        DB::beginTransaction();
        app(MediaDependencyService::class)->apply($source, null, [], 'delete');
        $this->assertDatabaseMissing('media', ['id' => $source->id]);
        Storage::disk('public')->assertExists($path);
        DB::rollBack();
        $this->assertDatabaseHas('media', ['id' => $source->id]);
        Storage::disk('public')->assertExists($path);

        DB::beginTransaction();
        app(MediaDependencyService::class)->apply($source->fresh(), null, [], 'delete');
        Storage::disk('public')->assertExists($path);
        DB::commit();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Media::find($source->id));
    }

    public function test_cleanup_failure_is_reported_after_commit_without_restoring_invalid_references(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $source = auth()->user()->addMedia(UploadedFile::fake()->image('disk-failure.jpg'))->toMediaCollection('media_library', 'public');
        $filesystem = \Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('removeAllFiles')->once()->andThrow(new \RuntimeException('Simulated storage failure'));
        app()->instance(Filesystem::class, $filesystem);

        app(MediaDependencyService::class)->apply($source, null, [], 'delete');
        $this->assertDatabaseMissing('media', ['id' => $source->id]);
        $this->assertSame([$source->id], app(MediaDependencyService::class)->cleanupFailures);
        Storage::disk('public')->assertExists($source->getPathRelativeToRoot());
    }

    public function test_deferred_cleanup_uses_locked_metadata_and_a_frozen_media_snapshot(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $source = auth()->user()->addMedia(UploadedFile::fake()->image('stale.jpg'))->toMediaCollection('media_library', 'public');
        $current = $source->fresh();
        $current->update(['file_name' => 'renamed.jpg']);
        $path = $current->getPathRelativeToRoot();
        Storage::disk('public')->assertExists($path);

        DB::beginTransaction();
        $source->delete();
        $source->file_name = 'unsaved-after-deletion.jpg';
        Storage::disk('public')->assertExists($path);
        DB::commit();

        Storage::disk('public')->assertMissing($path);
        $this->assertSame([], app(MediaDependencyService::class)->cleanupFailures);
        $this->assertDatabaseMissing('media', ['id' => $source->id]);
    }
}
