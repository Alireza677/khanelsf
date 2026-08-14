<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('available_for_activities')->default(false)->index()->after('icon');
            $table->string('pricing_mode')->nullable()->after('available_for_activities');
            $table->string('unit')->nullable()->after('pricing_mode');
            $table->string('custom_unit_label')->nullable()->after('unit');
            $table->decimal('default_unit_price', 18, 4)->nullable()->after('custom_unit_label');
            $table->char('currency_code', 3)->nullable()->after('default_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['available_for_activities']);
            $table->dropColumn([
                'available_for_activities',
                'pricing_mode',
                'unit',
                'custom_unit_label',
                'default_unit_price',
                'currency_code',
            ]);
        });
    }
};
