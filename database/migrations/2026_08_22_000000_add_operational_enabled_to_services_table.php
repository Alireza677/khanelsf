<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            // This deliberately does not copy the earlier opt-in flag. Existing
            // services must remain available unless an operator disables them.
            $table->boolean('operational_enabled')->default(true)->index()->after('available_for_activities');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['operational_enabled']);
            $table->dropColumn('operational_enabled');
        });
    }
};
