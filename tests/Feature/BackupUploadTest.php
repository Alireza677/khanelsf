<?php

namespace Tests\Feature;

use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_valid_cms_archive_is_stored_privately_and_downloadable(): void
    {
        $admin = User::factory()->admin()->create();
        $path = $this->archive('incoming/valid.zip');
        $backup = app(BackupUploadService::class)->accept($path, $admin);

        $this->assertSame(BackupSource::Uploaded, $backup->source);
        $this->assertSame(BackupStatus::Completed, $backup->status);
        $this->assertSame($admin->id, $backup->requested_by);
        $this->assertStringStartsWith('backups/files/', $backup->local_path);
        Storage::disk('local')->assertExists($backup->local_path);
        $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertOk()->assertDownload($backup->archive_name);
    }

    public function test_invalid_zip_and_missing_manifest_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Storage::disk('local')->put('incoming/invalid.zip', 'not-a-zip');
        try {
            app(BackupUploadService::class)->accept('incoming/invalid.zip', $admin);
            $this->fail('Invalid ZIP accepted.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('invalid_zip', $exception->failureCode);
        }

        $path = $this->archive('incoming/missing.zip', manifest: false);
        $this->expectException(BackupOperationException::class);
        app(BackupUploadService::class)->accept($path, $admin);
    }

    public function test_unsupported_manifest_and_traversal_entry_are_rejected_without_extraction(): void
    {
        $admin = User::factory()->admin()->create();
        $unsupported = $this->archive('incoming/unsupported.zip', version: 99);
        try {
            app(BackupUploadService::class)->accept($unsupported, $admin);
            $this->fail('Unsupported manifest accepted.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('unsupported_manifest_version', $exception->failureCode);
        }

        $traversal = $this->archive('incoming/traversal.zip', extraName: '../outside.php');
        try {
            app(BackupUploadService::class)->accept($traversal, $admin);
            $this->fail('Traversal archive accepted.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('unsafe_archive_path', $exception->failureCode);
        }
        Storage::disk('local')->assertMissing('outside.php');
    }

    public function test_oversized_and_duplicate_archives_are_rejected_without_retention(): void
    {
        $admin = User::factory()->admin()->create();
        config()->set('backup.upload_max_mb', 1);
        Storage::disk('local')->put('incoming/large.zip', random_bytes(1024 * 1024 + 1));
        try {
            app(BackupUploadService::class)->accept('incoming/large.zip', $admin);
            $this->fail('Oversized archive accepted.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('upload_too_large', $exception->failureCode);
        }

        $first = $this->archive('incoming/first.zip');
        $duplicateContents = file_get_contents(Storage::disk('local')->path($first));
        app(BackupUploadService::class)->accept($first, $admin);
        Storage::disk('local')->put('incoming/duplicate.zip', $duplicateContents);
        try {
            app(BackupUploadService::class)->accept('incoming/duplicate.zip', $admin);
            $this->fail('Duplicate archive accepted.');
        } catch (BackupOperationException $exception) {
            $this->assertSame('duplicate_backup', $exception->failureCode);
        }
        $this->assertSame(1, Backup::query()->count());
    }

    private function archive(string $path, bool $manifest = true, int $version = 1, ?string $extraName = null): string
    {
        $absolute = Storage::disk('local')->path($path);
        @mkdir(dirname($absolute), 0777, true);
        $zip = new ZipArchive;
        $zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($manifest) {
            $zip->addFromString('manifest.json', json_encode([
                'format_version' => 1, 'manifest_version' => $version, 'backup_uuid' => fake()->uuid(),
                'type' => 'full', 'created_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR));
        }
        $zip->addFromString($extraName ?? 'files/public/readme.txt', 'safe');
        $zip->close();

        return $path;
    }
}
