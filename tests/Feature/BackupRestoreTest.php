<?php

namespace Tests\Feature;

use App\Enums\BackupRestoreStatus;
use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Filament\Pages\Backups as BackupsPage;
use App\Jobs\RestoreBackupJob;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupDatabaseBinaryPreflight;
use App\Services\BackupMaintenanceMode;
use App\Services\BackupRestoreArchive;
use App\Services\BackupRestoreArtifact;
use App\Services\BackupRestoreHealthCheck;
use App\Services\BackupRestoreManager;
use App\Services\BackupRestoreMigrationService;
use App\Services\BackupRestoreService;
use App\Services\BackupRestoreStateStore;
use App\Services\DatabaseRestoreService;
use App\Services\PersistentStorageRestoreService;
use App\Services\SafetyBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    private string $outsideRoot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->storageRoot = storage_path('framework/testing/restore-public');
        $this->outsideRoot = storage_path('framework/testing/restore-outside');
        File::deleteDirectory($this->storageRoot);
        File::deleteDirectory($this->outsideRoot);
        File::ensureDirectoryExists($this->storageRoot);
        File::ensureDirectoryExists($this->outsideRoot);
        config()->set('backup.persistent_disks', [
            'public' => ['root' => $this->storageRoot, 'excludes' => ['runtime']],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);
        File::deleteDirectory($this->outsideRoot);
        File::deleteDirectory(storage_path('app/private/backups/tmp'));
        parent::tearDown();
    }

    public function test_uploaded_full_backup_has_restore_action_and_start_is_queued_once(): void
    {
        Bus::fake();
        $admin = User::factory()->admin()->create();
        $backup = $this->backup();

        Livewire::actingAs($admin)->test(BackupsPage::class)->assertSee('بازیابی');
        $restore = app(BackupRestoreManager::class)->start($backup, $admin);

        $this->assertSame(BackupRestoreStatus::Pending, $restore->status);
        Bus::assertDispatched(RestoreBackupJob::class, fn (RestoreBackupJob $job): bool => $job->restoreId === $restore->id);
        $this->expectException(BackupOperationException::class);
        app(BackupRestoreManager::class)->start($backup, $admin);
    }

    public function test_non_admin_cannot_start_restore(): void
    {
        $this->expectException(HttpException::class);
        app(BackupRestoreManager::class)->start($this->backup(), User::factory()->create());
    }

    public function test_archive_validation_accepts_legacy_contract_and_rejects_traversal_before_changes(): void
    {
        $backup = $this->backupWithArchive('legacy-valid.zip');
        $unsafe = $this->backupWithArchive('unsafe.zip', '../escape.php');
        config()->set('database.default', 'mysql');
        try {
            $artifact = app(BackupRestoreArchive::class)->stage($backup, 'valid-stage');
            $this->assertFileExists($artifact->databaseDumpPath);
            $this->assertSame(['public'], $artifact->storageScopes);
            File::deleteDirectory($artifact->stagingPath);

            try {
                app(BackupRestoreArchive::class)->stage($unsafe, 'unsafe-stage');
                $this->fail('Unsafe archive was staged.');
            } catch (BackupOperationException $exception) {
                $this->assertSame('unsafe_archive_path', $exception->failureCode);
            }
        } finally {
            config()->set('database.default', 'sqlite');
        }
        $this->assertFileDoesNotExist(storage_path('app/private/backups/tmp/escape.php'));
    }

    public function test_storage_is_replaced_not_merged_and_outside_or_excluded_files_survive(): void
    {
        file_put_contents($this->storageRoot.'/a.txt', 'old-a');
        file_put_contents($this->storageRoot.'/current-only.txt', 'remove');
        File::ensureDirectoryExists($this->storageRoot.'/runtime');
        file_put_contents($this->storageRoot.'/runtime/session.txt', 'preserve');
        file_put_contents($this->outsideRoot.'/outside.txt', 'outside');
        $stage = storage_path('app/private/backups/tmp/storage-artifact');
        File::ensureDirectoryExists($stage.'/files/public');
        file_put_contents($stage.'/database.sql', '-- test');
        file_put_contents($stage.'/files/public/a.txt', 'backup-a');
        file_put_contents($stage.'/files/public/b.txt', 'backup-b');
        $artifact = new BackupRestoreArtifact($stage, $stage.'/database.sql', ['public'], []);

        app(PersistentStorageRestoreService::class)->replace($artifact, 'storage-test');

        $this->assertSame('backup-a', file_get_contents($this->storageRoot.'/a.txt'));
        $this->assertSame('backup-b', file_get_contents($this->storageRoot.'/b.txt'));
        $this->assertFileDoesNotExist($this->storageRoot.'/current-only.txt');
        $this->assertSame('preserve', file_get_contents($this->storageRoot.'/runtime/session.txt'));
        $this->assertSame('outside', file_get_contents($this->outsideRoot.'/outside.txt'));
    }

    public function test_successful_restore_replaces_pages_settings_and_storage_after_safety_backup(): void
    {
        Page::query()->create(['title' => 'A current', 'slug' => 'a', 'status' => 'draft']);
        Page::query()->create(['title' => 'B current', 'slug' => 'b', 'status' => 'draft']);
        Page::query()->create(['title' => 'C current only', 'slug' => 'c', 'status' => 'draft']);
        Setting::query()->create(['key' => 'a', 'value' => 'current', 'group' => 'test', 'type' => 'string']);
        Setting::query()->create(['key' => 'c', 'value' => 'remove', 'group' => 'test', 'type' => 'string']);
        $mediaOwner = User::factory()->create();
        $this->insertMedia(101, $mediaOwner->id, 'current-only.jpg');
        file_put_contents($this->storageRoot.'/current-only.txt', 'remove');
        File::ensureDirectoryExists($this->storageRoot.'/101');
        file_put_contents($this->storageRoot.'/101/current-only.jpg', 'current-media');

        [$restore, $artifact, $safety] = $this->restoreFixture();
        File::ensureDirectoryExists($artifact->stagingPath.'/files/public/202');
        file_put_contents($artifact->stagingPath.'/files/public/202/restored.jpg', 'backup-media');
        $events = [];
        $database = Mockery::mock(DatabaseRestoreService::class);
        $database->shouldReceive('restore')->once()->andReturnUsing(function () use (&$events): void {
            $events[] = 'database';
            Page::query()->delete();
            Page::query()->create(['title' => 'A backup', 'slug' => 'a', 'status' => 'draft']);
            Page::query()->create(['title' => 'B backup', 'slug' => 'b', 'status' => 'draft']);
            Setting::query()->delete();
            Setting::query()->create(['key' => 'a', 'value' => 'backup', 'group' => 'test', 'type' => 'string']);
            Setting::query()->create(['key' => 'b', 'value' => 'backup', 'group' => 'test', 'type' => 'string']);
            DB::table('media')->delete();
            $ownerId = User::query()->value('id');
            $this->insertMedia(202, (int) $ownerId, 'restored.jpg');
        });
        $service = $this->service($artifact, $safety, $database, $events);
        $envBefore = is_file(base_path('.env')) ? hash_file('sha256', base_path('.env')) : null;

        $service->run($restore);

        $this->assertSame(['a', 'b'], Page::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['a', 'b'], Setting::query()->orderBy('key')->pluck('key')->all());
        $this->assertFileDoesNotExist($this->storageRoot.'/current-only.txt');
        $this->assertDatabaseMissing('media', ['id' => 101]);
        $this->assertDatabaseHas('media', ['id' => 202, 'file_name' => 'restored.jpg']);
        $this->assertFileDoesNotExist($this->storageRoot.'/101/current-only.jpg');
        $this->assertSame('backup-media', file_get_contents($this->storageRoot.'/202/restored.jpg'));
        $this->assertSame(['safety', 'maintenance-enter', 'database', 'migrate', 'health', 'maintenance-leave'], $events);
        $this->assertSame(BackupRestoreStatus::Completed, BackupRestore::query()->where('uuid', $restore->uuid)->first()->status);
        $this->assertNull(BackupRestore::query()->where('uuid', $restore->uuid)->value('active_lock'));
        $this->assertSame($envBefore, is_file(base_path('.env')) ? hash_file('sha256', base_path('.env')) : null);
    }

    public function test_safety_failure_prevents_maintenance_and_all_destructive_operations(): void
    {
        [$restore, $artifact] = $this->restoreFixture(includeSafety: false);
        $safety = Mockery::mock(SafetyBackupService::class);
        $safety->shouldReceive('create')->once()->andThrow(new BackupOperationException('pre_restore_backup_failed', 'safety failed'));
        $database = Mockery::mock(DatabaseRestoreService::class);
        $database->shouldNotReceive('restore');
        $maintenance = Mockery::mock(BackupMaintenanceMode::class);
        $maintenance->shouldNotReceive('enter');
        $events = [];
        $service = $this->service($artifact, null, $database, $events, safetyMock: $safety, maintenanceMock: $maintenance);

        try {
            $service->run($restore);
            $this->fail('Restore continued after safety backup failure.');
        } catch (BackupOperationException) {
            $failed = $restore->fresh();
            $this->assertSame(BackupRestoreStatus::Failed, $failed->status);
            $this->assertNull($failed->active_lock);
        }
    }

    public function test_binary_preflight_failure_happens_before_safety_backup_and_maintenance(): void
    {
        [$restore, $artifact] = $this->restoreFixture(includeSafety: false);
        $preflight = Mockery::mock(BackupDatabaseBinaryPreflight::class);
        $preflight->shouldReceive('assertAvailable')->once()->andThrow(
            new BackupOperationException('restore_binary_missing', 'binary missing'),
        );
        $safety = Mockery::mock(SafetyBackupService::class);
        $safety->shouldNotReceive('create');
        $database = Mockery::mock(DatabaseRestoreService::class);
        $database->shouldNotReceive('restore');
        $maintenance = Mockery::mock(BackupMaintenanceMode::class);
        $maintenance->shouldNotReceive('enter');
        $events = [];
        $service = $this->service(
            $artifact,
            null,
            $database,
            $events,
            safetyMock: $safety,
            maintenanceMock: $maintenance,
            preflightMock: $preflight,
        );

        try {
            $service->run($restore);
            $this->fail('Preflight failure was ignored.');
        } catch (BackupOperationException) {
            $failed = $restore->fresh();
            $this->assertSame(BackupRestoreStatus::Failed, $failed->status);
            $this->assertNull($failed->active_lock);
            $this->assertSame('restore_binary_missing', $failed->failure_code);
        }
    }

    public function test_database_failure_is_failed_locked_and_keeps_maintenance_for_recovery(): void
    {
        [$restore, $artifact, $safety] = $this->restoreFixture();
        $events = [];
        $database = Mockery::mock(DatabaseRestoreService::class);
        $database->shouldReceive('restore')->once()->andThrow(new BackupOperationException('restore_database_failed', 'database failed'));
        $maintenance = Mockery::mock(BackupMaintenanceMode::class);
        $maintenance->shouldReceive('enter')->once()->andReturnUsing(fn () => $events[] = 'maintenance-enter');
        $maintenance->shouldNotReceive('leave');
        $service = $this->service($artifact, $safety, $database, $events, maintenanceMock: $maintenance);

        try {
            $service->run($restore);
            $this->fail('Database failure was ignored.');
        } catch (BackupOperationException) {
            $failed = $restore->fresh();
            $this->assertSame(BackupRestoreStatus::Failed, $failed->status);
            $this->assertSame('installation', $failed->active_lock);
            $this->assertSame($safety->id, $failed->safety_backup_id);
        }
    }

    public function test_storage_failure_after_database_restore_remains_locked_in_maintenance(): void
    {
        [$restore, $artifact, $safety] = $this->restoreFixture();
        $events = [];
        $database = Mockery::mock(DatabaseRestoreService::class);
        $database->shouldReceive('restore')->once()->andReturnUsing(fn () => $events[] = 'database');
        $storage = Mockery::mock(PersistentStorageRestoreService::class);
        $storage->shouldReceive('replace')->once()->andThrow(
            new BackupOperationException('restore_storage_failed', 'storage failed'),
        );
        $maintenance = Mockery::mock(BackupMaintenanceMode::class);
        $maintenance->shouldReceive('enter')->once();
        $maintenance->shouldNotReceive('leave');
        $service = $this->service(
            $artifact,
            $safety,
            $database,
            $events,
            maintenanceMock: $maintenance,
            storageMock: $storage,
        );

        try {
            $service->run($restore);
            $this->fail('Storage failure was ignored.');
        } catch (BackupOperationException) {
            $failed = BackupRestore::query()->where('uuid', $restore->uuid)->firstOrFail();
            $this->assertSame(BackupRestoreStatus::Failed, $failed->status);
            $this->assertSame('installation', $failed->active_lock);
            $this->assertSame('restore_storage_failed', $failed->failure_code);
        }
    }

    public function test_health_failure_does_not_bring_the_site_up_or_release_lock(): void
    {
        [$restore, $artifact, $safety] = $this->restoreFixture();
        $events = [];
        $database = Mockery::mock(DatabaseRestoreService::class);
        $database->shouldReceive('restore')->once();
        $health = Mockery::mock(BackupRestoreHealthCheck::class);
        $health->shouldReceive('assertHealthy')->once()->andThrow(
            new BackupOperationException('restore_health_check_failed', 'health failed'),
        );
        $maintenance = Mockery::mock(BackupMaintenanceMode::class);
        $maintenance->shouldReceive('enter')->once();
        $maintenance->shouldNotReceive('leave');
        $service = $this->service(
            $artifact,
            $safety,
            $database,
            $events,
            maintenanceMock: $maintenance,
            healthMock: $health,
        );

        try {
            $service->run($restore);
            $this->fail('Health failure was ignored.');
        } catch (BackupOperationException) {
            $failed = BackupRestore::query()->where('uuid', $restore->uuid)->firstOrFail();
            $this->assertSame(BackupRestoreStatus::Failed, $failed->status);
            $this->assertSame('installation', $failed->active_lock);
            $this->assertSame('restore_health_check_failed', $failed->failure_code);
        }
    }

    public function test_controlled_recovery_reuses_preserved_safety_backup_without_creating_another_one(): void
    {
        [$restore, $artifact, $safety] = $this->restoreFixture();
        $restore->update([
            'backup_id' => $safety->id,
            'safety_backup_id' => $safety->id,
            'metadata' => ['recovery_of_restore_uuid' => fake()->uuid()],
        ]);
        $restore = $restore->fresh();
        $events = [];
        $safetyService = Mockery::mock(SafetyBackupService::class);
        $safetyService->shouldNotReceive('create');
        $database = Mockery::mock(DatabaseRestoreService::class);
        $database->shouldReceive('restore')->once()->andThrow(
            new BackupOperationException('restore_database_failed', 'database failed'),
        );
        $maintenance = Mockery::mock(BackupMaintenanceMode::class);
        $maintenance->shouldReceive('enter')->once();
        $maintenance->shouldNotReceive('leave');
        $service = $this->service(
            $artifact,
            $safety,
            $database,
            $events,
            safetyMock: $safetyService,
            maintenanceMock: $maintenance,
        );

        try {
            $service->run($restore);
            $this->fail('Recovery failure was ignored.');
        } catch (BackupOperationException) {
            $failed = $restore->fresh();
            $this->assertSame($safety->id, $failed->safety_backup_id);
            $this->assertSame('installation', $failed->active_lock);
        }
    }

    private function service(
        BackupRestoreArtifact $artifact,
        ?Backup $safety,
        DatabaseRestoreService $database,
        array &$events,
        ?SafetyBackupService $safetyMock = null,
        ?BackupMaintenanceMode $maintenanceMock = null,
        ?PersistentStorageRestoreService $storageMock = null,
        ?BackupRestoreHealthCheck $healthMock = null,
        ?BackupDatabaseBinaryPreflight $preflightMock = null,
    ): BackupRestoreService {
        $archive = Mockery::mock(BackupRestoreArchive::class);
        $archive->shouldReceive('stage')->once()->andReturn($artifact);
        $preflight = $preflightMock ?? Mockery::mock(BackupDatabaseBinaryPreflight::class);
        if (! $preflightMock) {
            $preflight->shouldReceive('assertAvailable')->once();
        }
        $safetyService = $safetyMock ?? Mockery::mock(SafetyBackupService::class);
        if (! $safetyMock) {
            $safetyService->shouldReceive('create')->once()->andReturnUsing(function () use ($safety, &$events): Backup {
                $events[] = 'safety';

                return $safety;
            });
        }
        $migration = Mockery::mock(BackupRestoreMigrationService::class);
        $migration->shouldReceive('migrate')->zeroOrMoreTimes()->andReturnUsing(function () use (&$events): void {
            $events[] = 'migrate';
        });
        $health = $healthMock ?? Mockery::mock(BackupRestoreHealthCheck::class);
        if (! $healthMock) {
            $health->shouldReceive('assertHealthy')->zeroOrMoreTimes()->andReturnUsing(function () use (&$events): void {
                $events[] = 'health';
            });
        }
        $maintenance = $maintenanceMock ?? Mockery::mock(BackupMaintenanceMode::class);
        if (! $maintenanceMock) {
            $maintenance->shouldReceive('enter')->once()->andReturnUsing(function () use (&$events): void {
                $events[] = 'maintenance-enter';
            });
            $maintenance->shouldReceive('leave')->once()->andReturnUsing(function () use (&$events): void {
                $events[] = 'maintenance-leave';
            });
        }

        return new BackupRestoreService(
            $archive,
            $safetyService,
            $database,
            $migration,
            $storageMock ?? app(PersistentStorageRestoreService::class),
            $health,
            $maintenance,
            app(BackupRestoreStateStore::class),
            $preflight,
        );
    }

    private function restoreFixture(bool $includeSafety = true): array
    {
        $admin = User::factory()->admin()->create();
        $target = $this->backup();
        $restore = BackupRestore::query()->create([
            'backup_id' => $target->id,
            'initiated_by' => $admin->id,
            'status' => BackupRestoreStatus::Pending,
            'current_step' => 'pending',
            'active_lock' => 'installation',
        ]);
        $stage = storage_path('app/private/backups/tmp/restore-fixture-'.uniqid());
        File::ensureDirectoryExists($stage.'/files/public');
        File::ensureDirectoryExists($stage.'/database');
        file_put_contents($stage.'/database/database.sql', '-- dump');
        $artifact = new BackupRestoreArtifact($stage, $stage.'/database/database.sql', ['public'], [
            'application' => ['laravel_version' => app()->version()],
        ]);
        if (! $includeSafety) {
            return [$restore, $artifact];
        }
        $safety = $this->backup(['uuid' => fake()->uuid(), 'source' => BackupSource::Automatic]);

        return [$restore, $artifact, $safety];
    }

    private function backup(array $overrides = []): Backup
    {
        $path = $overrides['local_path'] ?? 'backups/files/'.fake()->uuid().'.zip';
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, 'archive');
        }

        return Backup::query()->create([
            'uuid' => fake()->uuid(),
            'type' => BackupType::Full,
            'source' => BackupSource::Uploaded,
            'status' => BackupStatus::Completed,
            'idempotency_key' => fake()->uuid(),
            'archive_name' => basename($path),
            'local_disk' => 'local',
            'local_path' => $path,
            'checksum_algorithm' => 'sha256',
            'checksum' => hash('sha256', 'archive'),
            'size_bytes' => 7,
            'finished_at' => now(),
            ...$overrides,
        ]);
    }

    private function backupWithArchive(string $name, ?string $unsafeEntry = null): Backup
    {
        $path = 'backups/files/'.$name;
        $absolute = Storage::disk('local')->path($path);
        File::ensureDirectoryExists(dirname($absolute));
        $zip = new ZipArchive;
        $zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode([
            'format_version' => 1,
            'manifest_version' => 1,
            'type' => 'full',
            'database_driver' => 'mysql',
            'application' => ['name' => config('app.name'), 'laravel_version' => app()->version()],
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString('database/database.sql', 'DROP TABLE IF EXISTS test;');
        $zip->addFromString($unsafeEntry ?? 'files/public/media.txt', 'content');
        $zip->close();

        return $this->backup([
            'local_path' => $path,
            'archive_name' => $name,
            'checksum' => hash_file('sha256', $absolute),
            'size_bytes' => filesize($absolute),
        ]);
    }

    private function insertMedia(int $id, int $ownerId, string $fileName): void
    {
        DB::table('media')->insert([
            'id' => $id,
            'model_type' => User::class,
            'model_id' => $ownerId,
            'collection_name' => 'default',
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 12,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
