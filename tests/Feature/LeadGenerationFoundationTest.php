<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LeadResource\Pages\CreateLead;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\Page;
use App\Models\User;
use App\Services\SubmissionAnswerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadGenerationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_published_form_renders_its_supported_schema(): void
    {
        $form = $this->form();

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertSee($form->name)
            ->assertSee('name="name"', false)
            ->assertSee('type="email"', false)
            ->assertSee('name="message"', false)
            ->assertSee('درخواست مشاوره');
    }

    public function test_valid_submission_stores_payload_lead_relationship_and_attribution(): void
    {
        $form = $this->form();
        $page = Page::factory()->published()->create(['slug' => 'consultation-page']);

        $this->from(route('forms.show', $form->slug))
            ->post(route('forms.submit', $form->slug), [
                'name' => 'Jane Client',
                'phone' => '+98 912 000 0000',
                'email' => 'jane@example.com',
                'message' => 'Please contact me about a consultation.',
                '_context_page_id' => $page->getKey(),
                '_context_page_url' => '/consultation-page',
                '_context_block_id' => strtolower(self::BLOCK_ID),
            ])
            ->assertRedirect(route('forms.show', $form->slug))
            ->assertSessionHas('form_success', 'درخواست مشاوره دریافت شد.');

        $submission = FormSubmission::query()->sole();
        $lead = Lead::query()->sole();

        $this->assertSame($form->getKey(), $submission->form_id);
        $this->assertSame('website', $submission->source);
        $this->assertSame($page->getKey(), $submission->page_id);
        $this->assertSame('/consultation-page', $submission->page_url);
        $this->assertSame(self::BLOCK_ID, $submission->block_id);
        $this->assertSame('jane@example.com', $submission->payload['email']);
        $this->assertSame([
            'field_key' => 'name',
            'field_label' => 'Name',
            'raw_value' => 'Jane Client',
            'display_value' => 'Jane Client',
        ], $submission->payload[SubmissionAnswerSnapshot::PAYLOAD_KEY][0]);
        $this->assertSame($submission->getKey(), $lead->form_submission_id);
        $this->assertSame($form->getKey(), $lead->form_id);
        $this->assertSame('new', $lead->status);
        $this->assertSame('website', $lead->source);
        $this->assertSame(self::BLOCK_ID, $lead->block_id);
        $this->assertSame('Please contact me about a consultation.', $lead->notes);
        $this->assertTrue($submission->lead->is($lead));
        $this->assertTrue($lead->submission->is($submission));
    }

    public function test_submission_validation_rejects_invalid_or_unknown_payload_without_partial_records(): void
    {
        $form = $this->form();

        $this->post(route('forms.submit', $form->slug), [
            'name' => '',
            'email' => 'invalid',
            'unexpected' => 'must not be stored',
        ])->assertSessionHasErrors(['name', 'email']);

        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_expired_session_attribution_is_discarded(): void
    {
        $form = $this->form();
        $page = Page::factory()->published()->create();

        $this->post(route('forms.context', $form->slug), [
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/expired-source',
            '_context_block_id' => self::BLOCK_ID,
        ])->assertRedirect(route('forms.show', $form->slug));

        $this->travel(31)->minutes();

        $this->post(route('forms.submit', $form->slug), [
            'name' => 'Expired Context',
            'email' => 'expired@example.com',
        ])->assertRedirect();

        $submission = FormSubmission::query()->sole();
        $this->assertNull($submission->page_id);
        $this->assertNull($submission->page_url);
        $this->assertNull($submission->block_id);
    }

    public function test_legacy_context_query_is_captured_then_redirected_to_the_clean_form_url(): void
    {
        $form = $this->form();
        $page = Page::factory()->published()->create();

        $this->get(route('forms.show', [
            'slug' => $form->slug,
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/legacy-source',
            '_context_block_id' => self::BLOCK_ID,
        ]))->assertRedirect(route('forms.show', $form->slug));

        $this->assertSame(
            self::BLOCK_ID,
            session("forms.attribution.{$form->getKey()}.block_id"),
        );
    }

    public function test_draft_forms_cannot_be_rendered_or_submitted(): void
    {
        $form = $this->form(['status' => 'draft']);

        $this->get(route('forms.show', $form->slug))->assertNotFound();
        $this->post(route('forms.submit', $form->slug), ['name' => 'Jane'])->assertNotFound();
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_manual_lead_can_exist_without_a_form_submission(): void
    {
        $lead = Lead::query()->create([
            'name' => 'Manual Customer',
            'phone' => '+98 21 0000',
            'status' => 'new',
            'source' => 'manual',
        ]);

        $this->assertNull($lead->submission);
        $this->assertSame('manual', $lead->source);
    }

    public function test_crm_resources_create_forms_and_manual_leads(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateForm::class)
            ->fillForm([
                'name' => 'Callback',
                'slug' => 'callback',
                'status' => 'published',
                'schema_version' => 1,
                'schema' => ['fields' => [
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true],
                ]],
                'settings' => ['submit_label' => 'Call me'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateLead::class)
            ->fillForm([
                'name' => 'Manual Lead',
                'phone' => '+98 21 1111',
                'status' => 'new',
                'source' => 'manual',
                'notes' => 'Created by an administrator.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('forms', ['slug' => 'callback', 'status' => 'published']);
        $this->assertDatabaseHas('leads', ['name' => 'Manual Lead', 'source' => 'manual']);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_rendering_a_cta_does_not_create_a_submission_or_lead(): void
    {
        $html = view('partials.blocks.cta', ['data' => [
            'schema_version' => 2,
            'template' => 'classic',
            'content' => [
                'title' => 'Consultation',
                'primary_cta' => ['label' => 'Start', 'url' => '/forms/consultation'],
            ],
        ]])->render();

        $this->assertStringContainsString('/forms/consultation', $html);
        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_crm_placeholders_are_replaced_by_real_resources(): void
    {
        $this->assertSame('crm/forms', FormResource::getSlug());
        $this->assertSame('crm/customers', LeadResource::getSlug());
        $this->assertSame('CRM', FormResource::getNavigationGroup());
        $this->assertSame('CRM', LeadResource::getNavigationGroup());
    }

    private function form(array $overrides = []): Form
    {
        return Form::query()->create(array_merge([
            'name' => 'Consultation Form',
            'slug' => 'consultation',
            'status' => 'published',
            'display_mode' => 'page',
            'schema_version' => 1,
            'schema' => ['fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => false],
            ]],
            'settings' => [
                'submit_label' => 'درخواست مشاوره',
                'success_message' => 'درخواست مشاوره دریافت شد.',
            ],
        ], $overrides));
    }
}
