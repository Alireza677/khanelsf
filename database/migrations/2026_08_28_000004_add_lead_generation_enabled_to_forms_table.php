<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->boolean('lead_generation_enabled')->default(false)->after('display_mode');
        });

        DB::table('forms')->update(['lead_generation_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropColumn('lead_generation_enabled');
        });
    }
};
