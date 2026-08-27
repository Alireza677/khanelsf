<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->morphs('usable');
            $table->string('collection_name');
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();

            $table->unique(
                ['media_id', 'usable_type', 'usable_id', 'collection_name'],
                'media_usages_asset_owner_collection_unique'
            );
            $table->index(
                ['usable_type', 'usable_id', 'collection_name', 'order_column'],
                'media_usages_owner_collection_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
    }
};
