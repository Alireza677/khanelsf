<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submission_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->string('field_key')->index();
            $table->string('stored_path', 512)->unique();
            $table->string('original_name');
            $table->string('mime_type', 191);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['form_submission_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_attachments');
    }
};
