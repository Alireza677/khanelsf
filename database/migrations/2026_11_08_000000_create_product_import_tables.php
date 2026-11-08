<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->char('sha256', 64);
            $table->string('status')->default('preview')->index();
            $table->string('mode')->default('create_only');
            $table->json('sheet_names');
            $table->json('categories');
            $table->json('options');
            $table->json('summary');
            $table->json('warnings');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sheet_row');
            $table->string('sku')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('category_slug')->nullable();
            $table->json('payload');
            $table->json('specifications');
            $table->json('source_errors');
            $table->json('source_warnings');
            $table->json('errors');
            $table->json('warnings');
            $table->json('decision');
            $table->string('action')->default('review')->index();
            $table->string('result')->nullable()->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->char('baseline_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['product_import_id', 'sheet_row']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_imports');
    }
};
