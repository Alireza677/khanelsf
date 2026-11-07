<?php

use App\Support\PublicOrderCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('public_order_code', 20)->nullable()->unique();
        });

        // No Eloquent events, financial fields or historical timestamps are changed.
        DB::table('orders')->select('id')->orderBy('id')->chunkById(500, function ($orders): void {
            foreach ($orders as $order) {
                do {
                    $code = PublicOrderCode::generate();
                } while (DB::table('orders')->where('public_order_code', $code)->exists());

                DB::table('orders')->where('id', $order->id)->whereNull('public_order_code')->update(['public_order_code' => $code]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['public_order_code']);
            $table->dropColumn('public_order_code');
        });
    }
};
