<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backups')) {
            DB::table('backups')
                ->where('status', 'completed')
                ->whereNull('local_path')
                ->update([
                    'status' => 'failed',
                    'failure_code' => 'legacy_remote_unavailable',
                    'failure_summary' => 'فایل این نسخه قدیمی در فضای محلی سرور موجود نیست.',
                ]);

            Schema::table('backups', function (Blueprint $table): void {
                if (Schema::hasColumn('backups', 'backup_schedule_id')) {
                    $table->dropConstrainedForeignId('backup_schedule_id');
                }
                if (Schema::hasColumn('backups', 'google_drive_connection_id')) {
                    $table->dropConstrainedForeignId('google_drive_connection_id');
                }
            });

            $remoteColumns = array_values(array_filter([
                'drive_file_id', 'drive_folder_id', 'drive_md5_checksum',
            ], fn (string $column): bool => Schema::hasColumn('backups', $column)));
            if ($remoteColumns !== []) {
                Schema::table('backups', fn (Blueprint $table) => $table->dropColumn($remoteColumns));
            }
        }

        Schema::dropIfExists('backup_schedules');
        Schema::dropIfExists('google_drive_connections');
    }

    public function down(): void
    {
        // Removed remote credentials and schedules are intentionally not recreated.
    }
};
