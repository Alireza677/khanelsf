<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_activities', function (Blueprint $table): void {
            $table->foreignId('service_id')->nullable()->after('client_project_id')->constrained()->nullOnDelete();
            $table->string('service_name_snapshot')->nullable()->after('service_id');
            $table->string('service_unit_snapshot')->nullable()->after('service_name_snapshot');
            $table->string('service_unit_label_snapshot')->nullable()->after('service_unit_snapshot');
            $table->string('pricing_mode_snapshot')->nullable()->after('service_unit_label_snapshot');
            $table->char('currency_snapshot', 3)->nullable()->after('pricing_mode_snapshot');
            $table->decimal('unit_price_snapshot', 18, 4)->nullable()->after('currency_snapshot');
            $table->decimal('quantity', 18, 4)->nullable()->after('unit_price_snapshot');
            $table->decimal('total_amount', 18, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_activities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn([
                'service_name_snapshot',
                'service_unit_snapshot',
                'service_unit_label_snapshot',
                'pricing_mode_snapshot',
                'currency_snapshot',
                'unit_price_snapshot',
                'quantity',
                'total_amount',
            ]);
        });
    }
};
