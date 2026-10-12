<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_offering_versions', function (Blueprint $table): void {
            $table->json('features')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_offering_versions', fn (Blueprint $table) => $table->dropColumn('features'));
    }
};
