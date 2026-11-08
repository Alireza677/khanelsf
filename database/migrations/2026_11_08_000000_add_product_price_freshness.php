<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // MySQL DDL is not rolled back when a later CREATE TABLE fails.
            if (! Schema::hasColumn('products', 'price_changed_at')) {
                $table->timestamp('price_changed_at')->nullable();
            }
            if (! Schema::hasColumn('products', 'price_verified_at')) {
                $table->timestamp('price_verified_at')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'price_validity_mode')) {
                $table->string('price_validity_mode', 16)->default('inherit');
            }
            if (! Schema::hasColumn('products', 'price_validity_days')) {
                $table->unsignedInteger('price_validity_days')->nullable();
            }
        });
        Schema::table('product_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_categories', 'price_validity_days')) {
                $table->unsignedInteger('price_validity_days')->nullable();
            }
        });
        if (Schema::hasTable('product_price_histories')) {
            return;
        }
        Schema::create('product_price_histories', function (Blueprint $table): void {
            $table->id();
            // Intentionally retain audit identity even after a product/user is deleted.
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->decimal('old_sale_price', 12, 2)->nullable();
            $table->decimal('new_sale_price', 12, 2)->nullable();
            $table->string('event', 32);
            $table->string('source', 32);
            $table->timestamp('price_verified_at')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex(['price_verified_at']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'price_changed_at', 'price_verified_at', 'price_validity_mode', 'price_validity_days',
        ]));
        Schema::table('product_categories', fn (Blueprint $table) => $table->dropColumn('price_validity_days'));
    }
};
