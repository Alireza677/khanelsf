<?php

namespace Tests\Feature;

use App\Enums\BackupRestoreStatus;
use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Jobs\RestoreBackupJob;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\User;
use App\Services\BackupDatabaseBinaryPreflight;
use App\Services\BackupRestoreManager;
use App\Services\BackupRestoreStateStore;
use App\Services\DatabaseBackupProducer;
use App\Services\DatabaseRestoreDumpFilter;
use App\Services\DatabaseRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestorePreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_dump_filter_preserves_runtime_tables_and_keeps_historical_migrations_and_business_data(): void
    {
        $source = storage_path('framework/testing/preflight-source.sql');
        $destination = storage_path('framework/testing/preflight-filtered.sql');
        file_put_contents($source, implode("\n", [
            '-- MySQL dump',
            '-- Table structure for table `backups`',
            'DROP TABLE IF EXISTS `backups`;',
            'CREATE TABLE `backups` (`id` bigint);',
            '-- Table structure for table `jobs`',
            'DROP TABLE IF EXISTS `jobs`;',
            'CREATE TABLE `jobs` (`id` bigint);',
            '-- Table structure for table `migrations`',
            'DROP TABLE IF EXISTS `migrations`;',
            'CREATE TABLE `migrations` (`id` int);',
            '-- Table structure for table `pages`',
            'DROP TABLE IF EXISTS `pages`;',
            'CREATE TABLE `pages` (`id` bigint);',
            '-- Dump completed',
        ]));

        try {
            $represented = app(DatabaseRestoreDumpFilter::class)->filter($source, $destination);
            $filtered = file_get_contents($destination);
            $this->assertStringNotContainsString('DROP TABLE IF EXISTS `backups`', $filtered);
            $this->assertStringNotContainsString('DROP TABLE IF EXISTS `jobs`', $filtered);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `migrations`', $filtered);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `pages`', $filtered);
            $this->assertSame(['migrations', 'pages'], $represented);
        } finally {
            @unlink($source);
            @unlink($destination);
        }
    }

    public function test_dump_without_historical_migration_ledger_is_rejected_before_database_changes(): void
    {
        $source = storage_path('framework/testing/preflight-no-migrations.sql');
        $destination = storage_path('framework/testing/preflight-no-migrations-filtered.sql');
        file_put_contents($source, implode("\n", [
            '-- Table structure for table `pages`',
            'DROP TABLE IF EXISTS `pages`;',
            'CREATE TABLE `pages` (`id` bigint);',
        ]));

        try {
            $this->expectException(BackupOperationException::class);
            app(DatabaseRestoreDumpFilter::class)->filter($source, $destination);
        } finally {
            @unlink($source);
            @unlink($destination);
        }
    }

    public function test_operational_table_policy_matches_real_runtime_migrations(): void
    {
        $tables = config('backup.operational_tables');
        $this->assertEqualsCanonicalizing([
            'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks', 'sessions',
            'password_reset_tokens', 'backups', 'backup_restores',
        ], $tables);
        $this->assertNotContains('migrations', $tables, 'Historical migration ledger must be restored before forward migrations run.');

        $method = new \ReflectionMethod(DatabaseBackupProducer::class, 'ignoredTableArguments');
        $arguments = $method->invoke(app(DatabaseBackupProducer::class), 'cms_database');
        foreach ($tables as $table) {
            $this->assertContains('--ignore-table=cms_database.'.$table, $arguments);
        }
    }

    public function test_failed_restore_can_atomically_queue_its_real_safety_backup_as_recovery(): void
    {
        Storage::fake('local');
        Bus::fake();
        $target = $this->backup('target.zip');
        $safety = $this->backup('safety.zip', BackupSource::Automatic);
        $failed = BackupRestore::query()->create([
            'backup_id' => $target->id,
            'safety_backup_id' => $safety->id,
            'initiated_by' => User::factory()->admin()->create()->id,
            'status' => BackupRestoreStatus::Failed,
            'current_step' => BackupRestoreStatus::Failed->value,
            'active_lock' => 'installation',
        ]);

        $recovery = app(BackupRestoreManager::class)->recover($failed);

        $this->assertNull($failed->fresh()->active_lock);
        $this->assertSame('installation', $recovery->active_lock);
        $this->assertSame($safety->id, $recovery->backup_id);
        $this->assertSame($safety->id, $recovery->safety_backup_id);
        $this->assertSame($failed->uuid, data_get($recovery->metadata, 'recovery_of_restore_uuid'));
        Bus::assertDispatched(RestoreBackupJob::class, fn (RestoreBackupJob $job): bool => $job->restoreId === $recovery->id);
    }

    public function test_out_of_band_state_is_merged_and_keeps_recovery_identifiers(): void
    {
        Storage::fake('local');
        $uuid = fake()->uuid();
        $store = app(BackupRestoreStateStore::class);
        $store->write($uuid, BackupRestoreStatus::Validating, [
            'target_backup_uuid' => 'target-uuid',
            'safety_backup_id' => 42,
            'active_lock' => 'installation',
        ]);
        $store->write($uuid, BackupRestoreStatus::Failed, ['failure_code' => 'restore_database_failed']);

        $state = $store->read($uuid);
        $this->assertSame($uuid, $state['restore_uuid']);
        $this->assertSame('target-uuid', $state['target_backup_uuid']);
        $this->assertSame(42, $state['safety_backup_id']);
        $this->assertSame('installation', $state['active_lock']);
        $this->assertSame(BackupRestoreStatus::Failed->value, $state['status']);
        $this->assertSame('restore_database_failed', $state['failure_code']);
    }

    public function test_each_missing_database_binary_fails_preflight_with_safe_message(): void
    {
        $executable = PHP_BINARY;
        foreach (['database_dump_binary', 'database_restore_binary'] as $missingKey) {
            config()->set('backup.database_dump_binary', $executable);
            config()->set('backup.database_restore_binary', $executable);
            config()->set('backup.'.$missingKey, base_path('missing-'.fake()->uuid().'.exe'));

            try {
                app(BackupDatabaseBinaryPreflight::class)->assertAvailable(fake()->uuid());
                $this->fail($missingKey.' was accepted.');
            } catch (BackupOperationException $exception) {
                $this->assertSame('restore_binary_missing', $exception->failureCode);
                $this->assertSame('ابزار موردنیاز برای بازیابی پایگاه داده روی سرور در دسترس نیست.', $exception->getMessage());
            }
        }
    }

    public function test_mysql_diagnostics_redact_credentials_and_are_bounded(): void
    {
        $service = app(DatabaseRestoreService::class);
        $method = new \ReflectionMethod($service, 'sanitizeDiagnostics');
        $diagnostic = $method->invoke(
            $service,
            'failed --defaults-extra-file=C:\\private\\mysql.ini password=hunter2 mysql://root:secret@localhost '.str_repeat('x', 3000),
            'C:\\private\\mysql.ini',
        );

        $this->assertStringNotContainsString('hunter2', $diagnostic);
        $this->assertStringNotContainsString('secret@', $diagnostic);
        $this->assertStringNotContainsString('C:\\private\\mysql.ini', $diagnostic);
        $this->assertLessThanOrEqual(2000, mb_strlen($diagnostic));
    }

    private function backup(string $name, BackupSource $source = BackupSource::Uploaded): Backup
    {
        $path = 'backups/files/'.$name;
        Storage::disk('local')->put($path, 'archive');

        return Backup::query()->create([
            'type' => BackupType::Full,
            'source' => $source,
            'status' => BackupStatus::Completed,
            'idempotency_key' => fake()->uuid(),
            'archive_name' => $name,
            'local_disk' => 'local',
            'local_path' => $path,
            'checksum_algorithm' => 'sha256',
            'checksum' => hash('sha256', 'archive'),
            'size_bytes' => 7,
            'finished_at' => now(),
        ]);
    }
}
