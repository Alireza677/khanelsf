<?php

namespace Tests\Feature;

use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Jobs\CreateBackupJob;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\User;
use App\Services\BackupArchiveBuilder;
use App\Services\BackupManager;
use App\Services\GoogleDriveBackupStorage;
use App\Services\LocalBackupRetentionService;
use App\Services\LocalBackupStorage;
use App\Services\PersistentStorageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class BackupFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/archive-root'));
        File::deleteDirectory(storage_path('framework/testing/backup-registry'));
        File::delete(storage_path('framework/testing/outside.txt'));
        parent::tearDown();
    }

    public function test_backup_page_is_admin_only_and_has_local_product_contract(): void
    {
        $this->get('/admin/backups')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/admin/backups')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/backups')
            ->assertOk()->assertSee('فقط سه نسخه آخر روی سرور نگهداری می‌شود')->assertDontSee('Google Drive')->assertDontSee('بکاپ خودکار');
    }

    public function test_manual_request_is_queued_without_remote_connection(): void
    {
        Bus::fake();
        $admin = User::factory()->admin()->create();
        $backup = app(BackupManager::class)->request(BackupType::Full, $admin);
        $this->assertSame(BackupStatus::Queued, $backup->status);
        $this->assertSame(BackupSource::Manual, $backup->source);
        Bus::assertDispatched(CreateBackupJob::class, fn (CreateBackupJob $job) => $job->backupId === $backup->id && $job->queue === 'backups');
        $this->actingAs($admin)->get('/admin/backups')
            ->assertOk()->assertSee('در صف ایجاد')->assertDontSee('هنوز نسخه پشتیبانی ایجاد نشده است.');
    }

    public function test_pending_backup_is_polled_and_has_no_download_action(): void
    {
        $admin = User::factory()->admin()->create();
        $backup = $this->backup(['status' => BackupStatus::Creating]);

        $this->actingAs($admin)->get('/admin/backups')
            ->assertOk()
            ->assertSee('در حال آماده‌سازی')
            ->assertSee('wire:poll.5s', false)
            ->assertDontSee(route('admin.backups.download', $backup), false)
            ->assertDontSee('تلاش مجدد')
            ->assertDontSee('هنوز نسخه پشتیبانی ایجاد نشده است.');
    }

    public function test_completed_transition_changes_status_and_enables_download(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $backup = $this->backup(['status' => BackupStatus::Creating]);
        $this->actingAs($admin)->get('/admin/backups')->assertSee('در حال آماده‌سازی');

        Storage::disk('local')->put('backups/files/transition.zip', 'archive');
        $backup->update([
            'status' => BackupStatus::Completed,
            'archive_name' => 'transition.zip',
            'local_disk' => 'local',
            'local_path' => 'backups/files/transition.zip',
            'size_bytes' => 7,
            'finished_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/backups')
            ->assertOk()->assertSee('آماده دانلود')
            ->assertDontSee('تلاش مجدد')
            ->assertSee(route('admin.backups.download', $backup), false);
    }

    public function test_failed_backup_remains_visible_with_safe_error_and_retry_only(): void
    {
        $admin = User::factory()->admin()->create();
        $backup = $this->backup([
            'status' => BackupStatus::Failed,
            'failure_code' => 'dump_binary_missing',
            'failure_summary' => 'password=secret raw exception C:\\private\\path',
            'finished_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/backups')
            ->assertOk()
            ->assertSee('ناموفق')
            ->assertSee('ایجاد نسخه پشتیبان ناموفق بود.')
            ->assertSee('ابزار تهیه نسخه پایگاه داده در دسترس نیست.')
            ->assertSee('bg-danger-600', false)
            ->assertDontSee(route('admin.backups.download', $backup), false)
            ->assertSee('تلاش مجدد')
            ->assertDontSee('password=secret')
            ->assertDontSee('C:\\private\\path')
            ->assertDontSee('هنوز نسخه پشتیبانی ایجاد نشده است.');

        $statusHtml = view('filament.tables.columns.backup-status', [
            'getRecord' => fn (): Backup => $backup,
        ])->render();
        $this->assertStringContainsString('bg-danger-600', $statusHtml);
        $this->assertStringNotContainsString('animate-spin', $statusHtml);
    }

    public function test_creating_to_failed_transition_is_visible_through_polled_query(): void
    {
        $admin = User::factory()->admin()->create();
        $backup = $this->backup(['status' => BackupStatus::Creating]);
        $this->actingAs($admin)->get('/admin/backups')
            ->assertSee('در حال آماده‌سازی')->assertSee('wire:poll.5s', false);

        $backup->update(['status' => BackupStatus::Failed, 'failure_code' => 'archive_failed', 'finished_at' => now()]);

        $this->actingAs($admin)->get('/admin/backups')
            ->assertSee('ناموفق')->assertSee('ساخت فایل نسخه پشتیبان ناموفق بود.')
            ->assertDontSee('در حال آماده‌سازی');
    }

    public function test_retry_requeues_same_record_without_duplicate(): void
    {
        Bus::fake();
        $backup = $this->backup(['status' => BackupStatus::Failed, 'attempt' => 1]);
        $count = Backup::query()->count();

        app(BackupManager::class)->retry($backup);

        $this->assertSame($count, Backup::query()->count());
        $this->assertSame(BackupStatus::Queued, $backup->fresh()->status);
        Bus::assertDispatched(CreateBackupJob::class, fn (CreateBackupJob $job) => $job->backupId === $backup->id);
    }

    public function test_non_admin_cannot_access_failed_backup_retry_ui(): void
    {
        $this->backup(['status' => BackupStatus::Failed, 'finished_at' => now()]);
        $this->actingAs(User::factory()->create())->get('/admin/backups')->assertForbidden();
    }

    public function test_empty_state_only_appears_without_pending_or_completed_backups(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/backups')
            ->assertOk()->assertSee('هنوز نسخه پشتیبانی ایجاد نشده است.');
    }

    public function test_pending_is_shown_in_addition_to_three_latest_completed_backups(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $completed = collect(range(1, 4))->map(function (int $number): Backup {
            $path = "backups/files/recent-{$number}.zip";
            Storage::disk('local')->put($path, "archive-{$number}");

            return $this->backup([
                'status' => BackupStatus::Completed,
                'archive_name' => "recent-{$number}.zip",
                'local_disk' => 'local',
                'local_path' => $path,
                'size_bytes' => 9,
                'finished_at' => now()->addSeconds($number),
                'created_at' => now()->addSeconds($number),
            ]);
        });
        $this->backup(['status' => BackupStatus::Queued, 'created_at' => now()->addMinute()]);

        $response = $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee('در صف ایجاد');
        $response->assertDontSee(route('admin.backups.download', $completed[0]), false);
        foreach ($completed->slice(1) as $backup) {
            $response->assertSee(route('admin.backups.download', $backup), false);
        }
    }

    public function test_google_and_automatic_backup_runtime_are_absent(): void
    {
        $this->assertFalse(Route::has('admin.backups.google.authorize'));
        $this->assertFalse(Route::has('admin.backups.google.callback'));
        $this->assertFalse(class_exists(GoogleDriveBackupStorage::class));
        $this->assertFalse(class_exists(BackupSchedule::class));
    }

    public function test_persistent_registry_excludes_transient_content_and_symlink_escape(): void
    {
        $root = storage_path('framework/testing/backup-registry');
        @mkdir($root.'/livewire-tmp', 0777, true);
        file_put_contents($root.'/keep.txt', 'keep');
        file_put_contents($root.'/livewire-tmp/secret.txt', 'skip');
        $outside = storage_path('framework/testing/outside.txt');
        file_put_contents($outside, 'outside');
        @symlink($outside, $root.'/escape.txt');
        config()->set('backup.persistent_disks', ['test' => ['root' => $root, 'excludes' => ['livewire-tmp']]]);

        $this->assertSame(['files/test/keep.txt'], collect(app(PersistentStorageRegistry::class)->files())->pluck('archive_path')->all());
        @unlink($root.'/escape.txt');
    }

    public function test_files_archive_contains_manifest_checksum_and_no_environment_file(): void
    {
        $root = storage_path('framework/testing/archive-root');
        @mkdir($root, 0777, true);
        file_put_contents($root.'/asset.txt', 'asset');
        file_put_contents($root.'/.env', 'SECRET=value');
        config()->set('backup.persistent_disks', ['test' => ['root' => $root, 'excludes' => ['.env']]]);
        $artifact = app(BackupArchiveBuilder::class)->build($this->backup());
        $zip = new ZipArchive;
        $zip->open($artifact['path']);
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('files/test/asset.txt'));
        $this->assertFalse($zip->locateName('files/test/.env'));
        $this->assertSame(hash_file('sha256', $artifact['path']), $artifact['checksum']);
        $zip->close();
    }

    public function test_job_moves_archive_to_final_private_storage_and_completes(): void
    {
        Storage::fake('local');
        $backup = $this->backup();
        $temporary = storage_path('framework/testing/generated.zip');
        File::ensureDirectoryExists(dirname($temporary));
        file_put_contents($temporary, 'zip-content');
        $builder = Mockery::mock(BackupArchiveBuilder::class);
        $builder->shouldReceive('build')->once()->andReturn([
            'path' => $temporary, 'size' => 11, 'checksum' => hash('sha256', 'zip-content'), 'metadata' => ['manifest_version' => 1],
        ]);

        (new CreateBackupJob($backup->id))->handle($builder, app(LocalBackupStorage::class), app(LocalBackupRetentionService::class));

        $backup->refresh();
        $this->assertSame(BackupStatus::Completed, $backup->status);
        $this->assertSame(1, $backup->attempt);
        $this->assertSame('local', $backup->local_disk);
        $this->assertStringStartsWith('backups/files/', $backup->local_path);
        Storage::disk('local')->assertExists($backup->local_path);
        $this->assertFileDoesNotExist($temporary);
    }

    public function test_local_download_is_admin_only_and_missing_file_is_reported(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        Storage::disk('local')->put('backups/files/example.zip', 'archive');
        $available = $this->backup(['status' => BackupStatus::Completed, 'archive_name' => 'example.zip', 'local_disk' => 'local', 'local_path' => 'backups/files/example.zip']);
        $missing = $this->backup(['status' => BackupStatus::Completed, 'archive_name' => 'missing.zip', 'local_disk' => 'local', 'local_path' => 'backups/files/missing.zip']);

        $this->get(route('admin.backups.download', $available))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.backups.download', $available))->assertOk()->assertDownload('example.zip');
        $this->actingAs($admin)->get(route('admin.backups.download', $missing))->assertServerError();
    }

    private function backup(array $attributes = []): Backup
    {
        return Backup::query()->create(array_merge([
            'type' => BackupType::Files, 'source' => BackupSource::Manual, 'status' => BackupStatus::Queued, 'idempotency_key' => fake()->uuid(),
        ], $attributes));
    }
}
