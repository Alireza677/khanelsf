<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('project_started_at')->nullable()->after('project_date');
            $table->date('project_completed_at')->nullable()->after('project_started_at');
            $table->string('industry')->nullable()->after('location');
            $table->string('project_type')->nullable()->after('industry');
            $table->longText('challenge')->nullable()->after('content');
            $table->longText('solution')->nullable()->after('challenge');
            $table->longText('results_summary')->nullable()->after('solution');
            $table->longText('client_quote')->nullable()->after('results_summary');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn([
                'project_started_at',
                'project_completed_at',
                'industry',
                'project_type',
                'challenge',
                'solution',
                'results_summary',
                'client_quote',
            ]);
        });
    }
};
