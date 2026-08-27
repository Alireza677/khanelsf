<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('network_locations', function (Blueprint $table): void {
        $table->id(); $table->string('name'); $table->string('type', 40)->index(); $table->string('province_code', 8)->index(); $table->string('city');
        $table->string('contact_name')->nullable(); $table->string('position')->nullable(); $table->string('mobile', 32)->nullable(); $table->string('phone', 32)->nullable(); $table->string('email')->nullable();
        $table->text('address')->nullable(); $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 10, 7)->nullable(); $table->text('description')->nullable();
        $table->string('status', 20)->default('active')->index(); $table->unsignedInteger('sort_order')->default(0)->index(); $table->timestamps();
        $table->index(['status','type','province_code','sort_order'], 'network_locations_runtime_index');
    }); }
    public function down(): void { Schema::dropIfExists('network_locations'); }
};
