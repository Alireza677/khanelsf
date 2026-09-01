<?php

namespace Tests\Feature;

use App\Enums\BackupRestoreStatus;
use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Filament\Pages\Backups as BackupsPage;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\User;
use App\Services\BackupRestoreManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BackupRestoreProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Artisan::call('up');
        parent::tearDown();
    }

    public function test_filament_restore_action_redirects_immediately_to_tokenized_progress_page(): void
    {
        Storage::fake('local');
        Bus::fake();
        config()->set('backup.restore_dispatch_delay_seconds', 3);
        $admin = User::factory()->admin()->create();
        $backup = $this->backup();

        $component = Livewire::actingAs($admin)->test(BackupsPage::class)
            ->callTableAction('restore', $backup);

        $restore = BackupRestore::query()->firstOrFail();
        $component->assertRedirectContains('/backup-restore-progress/'.$restore->uuid.'?token=');
        $this->assertNotNull($restore->progress_token_hash);
        $this->assertStringNotContainsString((string) $restore->progress_token_hash, (string) $component->effects['redirect'] ?? '');
    }

    public function test_progress_is_session_independent_and_only_exact_routes_bypass_maintenance(): void
    {
        [$restore, $token] = $this->restoreWithToken();
        Artisan::call('down');

        $this->get(route('backup-restore.progress', ['restore' => $restore->uuid, 'token' => $token]))
            ->assertOk()->assertSee('در حال بازیابی نسخه پشتیبان');
        $this->get(route('backup-restore.status', ['restore' => $restore->uuid, 'token' => $token]))
            ->assertOk()->assertJsonPath('step', 'pending');
        $this->get('/')->assertStatus(503);
        $this->get('/admin')->assertStatus(503);
        $this->post('/livewire/update')->assertStatus(503);
    }

    public function test_wrong_missing_and_expired_tokens_are_denied(): void
    {
        [$restore, $token] = $this->restoreWithToken();
        $this->get(route('backup-restore.progress', $restore->uuid))->assertForbidden();
        $this->get(route('backup-restore.progress', ['restore' => $restore->uuid, 'token' => str_repeat('a', 64)]))->assertForbidden();

        $restore->update(['progress_token_expires_at' => now()->subSecond()]);
        $this->get(route('backup-restore.progress', ['restore' => $restore->uuid, 'token' => $token]))->assertForbidden();
    }

    public function test_completed_and_failed_status_are_safe_and_frontend_redirects_only_on_success(): void
    {
        [$restore, $token] = $this->restoreWithToken();
        $restore->update(['status' => BackupRestoreStatus::Completed, 'current_step' => 'completed']);
        $this->get(route('backup-restore.status', ['restore' => $restore->uuid, 'token' => $token]))
            ->assertJson(['completed' => true, 'failed' => false]);
        $page = $this->get(route('backup-restore.progress', ['restore' => $restore->uuid, 'token' => $token]));
        $page->assertSee("window.location.replace('/')", false);

        $restore->update([
            'status' => BackupRestoreStatus::Failed,
            'current_step' => 'failed',
            'failure_code' => 'restore_database_failed',
            'error_message' => 'secret-password C:\\private\\credentials',
        ]);
        $response = $this->get(route('backup-restore.status', ['restore' => $restore->uuid, 'token' => $token]));
        $response->assertJson(['completed' => false, 'failed' => true, 'failure_message' => 'بازیابی پایگاه داده ناموفق بود.']);
        $response->assertDontSee('secret-password')->assertDontSee('credentials');
    }

    private function restoreWithToken(): array
    {
        Storage::fake('local');
        Bus::fake();
        $started = app(BackupRestoreManager::class)->startWithProgress(
            $this->backup(),
            User::factory()->admin()->create(),
        );

        return [$started->restore, $started->progressToken];
    }

    private function backup(): Backup
    {
        $path = 'backups/files/'.fake()->uuid().'.zip';
        Storage::disk('local')->put($path, 'archive');

        return Backup::query()->create([
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
        ]);
    }
}
