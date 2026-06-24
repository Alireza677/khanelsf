<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('seo_image')->nullable()->after('seo_description');
            $table->boolean('robots_index')->default(true)->after('seo_keywords');
            $table->boolean('robots_follow')->default(true)->after('robots_index');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->string('seo_image')->nullable()->after('seo_description');
            $table->boolean('robots_index')->default(true)->after('seo_keywords');
            $table->boolean('robots_follow')->default(true)->after('robots_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['seo_image', 'robots_index', 'robots_follow']);
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['seo_image', 'robots_index', 'robots_follow']);
        });
    }
};
