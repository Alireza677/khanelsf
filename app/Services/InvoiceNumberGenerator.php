<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

final class InvoiceNumberGenerator
{
    public function next(CarbonInterface $date): string
    {
        $period = Jalalian::fromDateTime($date)->format('Y');
        DB::table('invoice_number_sequences')->insertOrIgnore(['period_key' => $period, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('invoice_number_sequences')->where('period_key', $period)->lockForUpdate()->first();
        $next = ((int) $row->last_number) + 1;
        DB::table('invoice_number_sequences')->where('period_key', $period)->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('INV-%s-%06d', $period, $next);
    }
}
