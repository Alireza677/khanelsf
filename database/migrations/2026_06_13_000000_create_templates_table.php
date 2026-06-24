<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type');
            $table->string('status')->default('draft')->index();
            $table->json('blocks')->nullable();
            $table->integer('priority')->default(0)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'is_default', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
