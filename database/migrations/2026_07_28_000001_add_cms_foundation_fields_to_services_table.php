<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->text('excerpt')->nullable()->after('slug');
            $table->longText('overview')->nullable()->after('excerpt');
            $table->json('benefits')->nullable()->after('overview');
            $table->json('process')->nullable()->after('benefits');
            $table->json('deliverables')->nullable()->after('process');
            $table->timestamp('published_at')->nullable()->index()->after('status');
            $table->string('seo_title')->nullable()->after('sort_order');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('icon')->nullable()->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['published_at']);
            $table->dropColumn([
                'excerpt',
                'overview',
                'benefits',
                'process',
                'deliverables',
                'published_at',
                'seo_title',
                'seo_description',
                'icon',
            ]);
        });
    }
};
