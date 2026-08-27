<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->ulid('asset_key')->nullable()->unique()->after('uuid');
            $table->string('original_filename')->nullable()->after('file_name');
            $table->char('checksum_sha256', 64)->nullable()->index()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique(['asset_key']);
            $table->dropIndex(['checksum_sha256']);
            $table->dropColumn(['asset_key', 'original_filename', 'checksum_sha256']);
        });
    }
};
