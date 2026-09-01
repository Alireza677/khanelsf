<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_restores', function (Blueprint $table): void {
            $table->char('progress_token_hash', 64)->nullable()->after('active_lock');
            $table->timestamp('progress_token_expires_at')->nullable()->after('progress_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('backup_restores', fn (Blueprint $table) => $table->dropColumn([
            'progress_token_hash', 'progress_token_expires_at',
        ]));
    }
};
