<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('group_name')->nullable();
            $table->string('key')->nullable();
            $table->string('label')->nullable();
            $table->text('value')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
