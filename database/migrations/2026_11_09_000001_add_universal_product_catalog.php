<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
        });

        Schema::create('product_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
        });

        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('product_product_tag', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_tag_id']);
            $table->index(['product_tag_id', 'product_id']);
        });

        Schema::create('product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->string('slug')->unique();
            // A discriminator, not an invitation to interpret legacy text as typed data.
            $table->string('data_type', 32)->default('choice');
            $table->string('unit', 32)->nullable();
            $table->boolean('is_multiple')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->string('slug');
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_attribute_id', 'name_key'], 'product_value_name_unique');
            $table->unique(['product_attribute_id', 'slug'], 'product_value_slug_unique');
            $table->unique(['id', 'product_attribute_id'], 'product_value_attribute_unique');
        });
        Schema::create('product_attribute_category', function (Blueprint $table): void {
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_attribute_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_category_id', 'product_attribute_id'], 'product_category_attribute_primary');
            $table->index(['product_attribute_id', 'product_category_id'], 'product_attribute_category_index');
        });
        Schema::create('product_attribute_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_attribute_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_attribute_value_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'product_attribute_value_id'], 'product_assignment_unique');
            $table->index(['product_attribute_id', 'product_attribute_value_id', 'product_id'], 'product_assignment_filter_index');
            $table->foreign(['product_attribute_value_id', 'product_attribute_id'], 'product_assignment_value_fk')
                ->references(['id', 'product_attribute_id'])->on('product_attribute_values')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_assignments');
        Schema::dropIfExists('product_attribute_category');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_product_tag');
        Schema::dropIfExists('product_tags');
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_brand_id'));
        Schema::dropIfExists('product_brands');
        Schema::table('product_categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
    }
};
