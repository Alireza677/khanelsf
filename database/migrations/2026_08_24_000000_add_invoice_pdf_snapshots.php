<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            foreach (['customer_name_snapshot', 'customer_company_snapshot', 'customer_mobile_snapshot', 'customer_email_snapshot', 'issuer_name_snapshot', 'issuer_phone_snapshot', 'issuer_email_snapshot', 'issuer_website_snapshot', 'pdf_disk', 'pdf_path', 'pdf_hash', 'pdf_status', 'pdf_error'] as $c) {
                $t->string($c)->nullable();
            }
            $t->text('customer_address_snapshot')->nullable();
            $t->text('issuer_address_snapshot')->nullable();
            $t->dateTime('pdf_generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['customer_name_snapshot', 'customer_company_snapshot', 'customer_mobile_snapshot', 'customer_email_snapshot', 'customer_address_snapshot', 'issuer_name_snapshot', 'issuer_phone_snapshot', 'issuer_email_snapshot', 'issuer_address_snapshot', 'issuer_website_snapshot', 'pdf_disk', 'pdf_path', 'pdf_hash', 'pdf_status', 'pdf_error', 'pdf_generated_at']));
    }
};
