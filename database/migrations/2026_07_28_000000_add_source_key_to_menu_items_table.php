<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('source_key', 128)
                ->nullable()
                ->after('type')
                ->index();
        });

        DB::table('menu_items')
            ->whereNull('source_key')
            ->whereIn('url', ['/shop', '/shop/'])
            ->update([
                'source_key' => 'shop.index',
                'type' => 'source',
                'url' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('source_key', 'shop.index')
            ->whereNull('url')
            ->update([
                'type' => 'custom_url',
                'url' => '/shop',
            ]);

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex(['source_key']);
            $table->dropColumn('source_key');
        });
    }
};
