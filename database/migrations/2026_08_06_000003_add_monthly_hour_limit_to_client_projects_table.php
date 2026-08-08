<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table): void {
            $table->unsignedInteger('monthly_hour_limit_minutes')->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table): void {
            $table->dropColumn('monthly_hour_limit_minutes');
        });
    }
};
