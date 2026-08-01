<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->string('type')->default('normal')->index()->after('display_mode');
            $table->string('calculator_identifier')->nullable()->after('type');
        });

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->json('calculation_result')->nullable()->after('payload');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->json('calculation_result')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('calculation_result');
        });

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropColumn('calculation_result');
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'calculator_identifier']);
        });
    }
};
