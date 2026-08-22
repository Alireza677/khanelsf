<?php

namespace Tests\Feature;

use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Services\LocalBackupRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRetentionAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_fourth_completed_backup_prunes_oldest_file_and_record(): void
    {
        Storage::fake('local');
        $backups = collect(range(1, 4))->map(fn (int $number) => $this->available($number, BackupSource::Manual));
        app(LocalBackupRetentionService::class)->prune();

        $this->assertDatabaseMissing('backups', ['id' => $backups[0]->id]);
        $backups->slice(1)->each(fn (Backup $backup) => $this->assertDatabaseHas('backups', ['id' => $backup->id]));
        Storage::disk('local')->assertMissing($backups[0]->local_path);
        $this->assertSame(3, Backup::query()->where('status', BackupStatus::Completed->value)->count());
    }

    public function test_failed_fourth_does_not_prune_three_recovery_points(): void
    {
        Storage::fake('local');
        collect(range(1, 3))->each(fn (int $number) => $this->available($number, BackupSource::Manual));
        $failed = Backup::query()->create(['type' => BackupType::Full, 'source' => BackupSource::Manual, 'status' => BackupStatus::Failed, 'idempotency_key' => fake()->uuid()]);
        app(LocalBackupRetentionService::class)->prune();
        $this->assertSame(3, Backup::query()->where('status', BackupStatus::Completed->value)->count());
        $this->assertDatabaseHas('backups', ['id' => $failed->id, 'status' => BackupStatus::Failed->value]);
    }

    public function test_mixed_generated_and_uploaded_backups_share_oldest_first_retention(): void
    {
        Storage::fake('local');
        $a = $this->available(1, BackupSource::Manual);
        $b = $this->available(2, BackupSource::Uploaded);
        $c = $this->available(3, BackupSource::Manual);
        $d = $this->available(4, BackupSource::Uploaded);
        app(LocalBackupRetentionService::class)->prune();
        $this->assertDatabaseMissing('backups', ['id' => $a->id]);
        foreach ([$b, $c, $d] as $kept) {
            $this->assertDatabaseHas('backups', ['id' => $kept->id]);
        }
    }

    public function test_deletion_never_touches_a_path_outside_backup_root(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('protected.txt', 'keep');
        $unsafe = $this->available(1, BackupSource::Manual, 'protected.txt');
        foreach (range(2, 4) as $number) {
            $this->available($number, BackupSource::Manual);
        }
        app(LocalBackupRetentionService::class)->prune();
        Storage::disk('local')->assertExists('protected.txt');
        $this->assertDatabaseHas('backups', ['id' => $unsafe->id, 'status' => BackupStatus::DeleteFailed->value]);
    }

    private function available(int $number, BackupSource $source, ?string $path = null): Backup
    {
        $path ??= "backups/files/{$number}.zip";
        Storage::disk('local')->put($path, "backup-{$number}");

        return Backup::query()->create([
            'type' => BackupType::Full, 'source' => $source, 'status' => BackupStatus::Completed,
            'idempotency_key' => fake()->uuid(), 'archive_name' => "{$number}.zip", 'local_disk' => 'local',
            'local_path' => $path, 'size_bytes' => 8, 'checksum_algorithm' => 'sha256',
            'checksum' => hash('sha256', "backup-{$number}"), 'finished_at' => now()->addSeconds($number),
        ]);
    }
}
