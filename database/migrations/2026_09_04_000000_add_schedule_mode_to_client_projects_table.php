<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table): void {
            $table->string('schedule_mode')->default('recurring')->after('status');
            $table->unsignedTinyInteger('cycle_anchor_day')->nullable()->after('monthly_hour_limit_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table): void {
            $table->dropColumn(['schedule_mode', 'cycle_anchor_day']);
        });
    }
};
