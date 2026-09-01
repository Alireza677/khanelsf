<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\User;
use App\Services\FormSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FormLeadGenerationSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_new_form_defaults_to_not_generating_leads_and_admin_exposes_toggle(): void
    {
        $form = $this->form();
        $form->refresh();

        $this->assertFalse($form->lead_generation_enabled);
        $this->assertFalse($form->generatesLeads());

        $this->actingAs(User::factory()->admin()->create());
        $toggle = collect(Livewire::test(CreateForm::class)->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($component): bool => method_exists($component, 'getName') && $component->getName() === 'lead_generation_enabled');

        $this->assertNotNull($toggle);
        $this->assertSame('ایجاد سرنخ فروش از ورودی‌ها', $toggle->getLabel());
    }

    public function test_enabled_form_creates_submission_and_reuses_current_lead_mapping(): void
    {
        $submission = $this->submit($this->form(true), ['name' => 'خریدار', 'email' => 'buyer@example.com']);
        $lead = Lead::query()->sole();

        $this->assertTrue($submission->lead->is($lead));
        $this->assertSame('خریدار', $lead->name);
        $this->assertSame('buyer@example.com', $lead->email);
        $this->assertSame('website', $lead->source);
    }

    public function test_disabled_form_stores_submission_sender_and_attachment_without_sales_lead(): void
    {
        $form = $this->form(false);
        $file = UploadedFile::fake()->image('warranty.png');
        $submission = app(FormSubmissionService::class)->submit(
            $form,
            ['name' => 'کاربر پشتیبانی', 'email' => 'support@example.com'],
            ['source' => 'website'],
            ['document' => $file],
        );

        $this->assertDatabaseHas('form_submissions', ['id' => $submission->getKey()]);
        $this->assertSame('support@example.com', $submission->payload['email']);
        $attachment = $submission->attachments->sole();
        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_disabling_form_only_affects_future_submissions_and_preserves_historical_relation(): void
    {
        $form = $this->form(true);
        $historical = $this->submit($form, ['email' => 'first@example.com']);
        $historicalLead = $historical->lead;

        $form->update(['lead_generation_enabled' => false]);
        $newSubmission = $this->submit($form->fresh(), ['email' => 'second@example.com']);

        $this->assertDatabaseHas('leads', ['id' => $historicalLead->getKey(), 'form_submission_id' => $historical->getKey()]);
        $this->assertTrue($historical->fresh()->lead->is($historicalLead));
        $this->assertNull($newSubmission->lead);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_submission_view_without_lead_renders_without_crm_empty_state(): void
    {
        $submission = $this->submit($this->form(false), ['email' => 'support@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(FormResource::getUrl('index'))
            ->assertOk();
        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertDontSee('مشاهده سرنخ')
            ->assertDontSee('بدون سرنخ');
    }

    private function submit(Form $form, array $payload): FormSubmission
    {
        return app(FormSubmissionService::class)->submit($form, $payload);
    }

    private function form(?bool $enabled = null): Form
    {
        $attributes = [
            'name' => 'فرم تست',
            'slug' => 'lead-setting-'.str()->random(8),
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => []],
            'settings' => [],
        ];

        if ($enabled !== null) {
            $attributes['lead_generation_enabled'] = $enabled;
        }

        return Form::query()->create($attributes);
    }
}
