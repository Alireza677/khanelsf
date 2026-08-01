<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('type', 32)
                ->default('custom_url')
                ->after('parent_id')
                ->index();
            $table->unsignedBigInteger('reference_id')
                ->nullable()
                ->after('type');
            $table->string('reference_type')
                ->nullable()
                ->after('reference_id');
            $table->index(
                ['reference_type', 'reference_id'],
                'menu_items_reference_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex('menu_items_reference_index');
            $table->dropIndex(['type']);
            $table->dropColumn([
                'type',
                'reference_id',
                'reference_type',
            ]);
        });
    }
};
