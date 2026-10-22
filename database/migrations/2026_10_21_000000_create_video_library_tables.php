<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('description')->nullable();
            $table->string('provider', 32)->default('aparat');
            $table->string('source_url', 2048);
            $table->string('external_id', 100);
            $table->string('thumbnail_url', 2048)->nullable();
            // Overrides use King's existing media_usages / featured_image contract.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('related_url', 2048)->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->index(['status', 'published_at', 'sort_order']);
        });

        Schema::create('video_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('video_video_category', function (Blueprint $table): void {
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['video_id', 'video_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_video_category');
        Schema::dropIfExists('video_categories');
        Schema::dropIfExists('videos');
    }
};
