<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('draft')->index();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('schema')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained()->restrictOnDelete();
            $table->string('source')->default('website')->index();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->text('page_url')->nullable();
            $table->string('block_id', 26)->nullable()->index();
            $table->json('payload');
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('form_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new')->index();
            $table->string('source')->default('website')->index();
            $table->text('page_url')->nullable();
            $table->string('block_id', 26)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
