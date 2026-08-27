<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerMembershipManager;
use App\Services\InvoiceLifecycle;
use App\Services\InvoicePdfGenerator;
use App\Services\MonthlyInvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_pdf_is_private_snapshot_artifact_and_securely_downloadable(): void
    {
        Storage::fake('local');
        $customer = Customer::factory()->create(['display_name' => 'مشتری تاریخی']);
        $project = ClientProject::factory()->for($customer)->create();
        ClientProjectActivity::factory()->for($project, 'project')->create(['activity_date' => '2026-08-10', 'duration_minutes' => 60, 'currency_snapshot' => 'IRT', 'unit_price_snapshot' => '100', 'total_amount' => '100', 'pricing_mode_snapshot' => 'fixed', 'service_unit_snapshot' => 'fixed', 'service_name_snapshot' => 'خدمت تاریخی']);
        $invoice = app(MonthlyInvoiceGenerator::class)->generate($customer, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'));
        $issued = app(InvoiceLifecycle::class)->issue($invoice);
        app(InvoicePdfGenerator::class)->generate($issued);
        $issued->refresh();
        Storage::disk('local')->assertExists($issued->pdf_path);
        File::ensureDirectoryExists(base_path('output/pdf'));
        File::put(base_path('output/pdf/invoice-sample.pdf'), Storage::disk('local')->get($issued->pdf_path));
        $this->assertSame(hash('sha256', Storage::disk('local')->get($issued->pdf_path)), $issued->pdf_hash);
        $this->assertSame('مشتری تاریخی', $issued->customer_name_snapshot);
        $customer->update(['display_name' => 'نام جدید']);
        app(InvoicePdfGenerator::class)->generate($issued);
        $this->assertSame('مشتری تاریخی', $issued->fresh()->customer_name_snapshot);
        $user = User::factory()->client()->create();
        app(CustomerMembershipManager::class)->assign($customer, $user, 'owner');
        $this->actingAs($user, 'client')->get(route('account.invoices.download', $issued))->assertOk()->assertHeader('content-type', 'application/pdf');
        $stranger = User::factory()->client()->create();
        $this->actingAs($stranger, 'client')->get(route('account.invoices.download', $issued))->assertNotFound();
    }
}
