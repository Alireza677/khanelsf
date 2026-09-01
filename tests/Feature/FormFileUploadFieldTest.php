<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormSubmissionAttachment;
use App\Models\User;
use App\Services\FormSchema;
use App\Services\FormSubmissionPresenter;
use App\Services\SettingsService;
use App\Support\FormUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\TestCase;

class FormFileUploadFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_palette_schema_and_renderer_expose_single_file_upload_contract(): void
    {
        $form = $this->form('all', required: true);
        $field = app(FormSchema::class)->fields($form)[0];
        $html = view('forms._form', [
            'form' => $form,
            'fields' => [$field],
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertSame('آپلود فایل', data_get(FormResource::fieldPalette(), 'advanced.fields.file.label'));
        $this->assertSame('file', $field['type']);
        $this->assertSame('all', $field['settings']['file_type']);
        $this->assertSame(10, $field['settings']['max_size_mb']);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('class="sr-only form-file-input"', $html);
        $this->assertStringContainsString('data-form-file-picker', $html);
        $this->assertStringContainsString('انتخاب فایل', $html);
        $this->assertStringContainsString('فایلی انتخاب نشده است', $html);
        $this->assertStringNotContainsString('Choose File', $html);
        $this->assertStringNotContainsString('No file chosen', $html);
        $this->assertStringContainsString('name="resume"', $html);
        $this->assertStringNotContainsString('multiple', $html);
        $this->assertStringContainsString('حداکثر حجم فایل', $html);
        $this->assertStringContainsString('فرمت‌های مجاز', $html);
    }

    public function test_custom_file_picker_updates_and_resets_the_persian_status(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("input.addEventListener('change', sync)", $source);
        $this->assertStringContainsString("input.files?.[0]?.name || 'فایلی انتخاب نشده است'", $source);
        $this->assertStringContainsString("input.form?.addEventListener('reset'", $source);
        $this->assertStringContainsString('initFormFileInputs();', $source);
    }

    public function test_setting_defaults_to_ten_and_backend_enforces_changed_limit(): void
    {
        $this->assertSame(10, FormUpload::maxSizeMb());
        app(SettingsService::class)->set('form_max_upload_size_mb', 1, 'forms', 'number');
        $form = $this->form();

        $this->post(route('forms.submit', $form->slug), [
            'resume' => UploadedFile::fake()->create('resume.pdf', 1100, 'application/pdf'),
        ])->assertSessionHasErrors('resume');

        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('form_submission_attachments', 0);
    }

    public function test_admin_setting_accepts_only_one_to_one_hundred_megabytes(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSiteSettings::class)
            ->set('data.site_name', 'سایت آزمایشی')
            ->set('data.form_max_upload_size_mb', 101)
            ->call('save')
            ->assertHasFormErrors(['form_max_upload_size_mb']);

        Livewire::test(ManageSiteSettings::class)
            ->set('data.site_name', 'سایت آزمایشی')
            ->set('data.form_max_upload_size_mb', 25)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'form_max_upload_size_mb',
            'value' => '25',
            'group' => 'forms',
            'type' => 'number',
        ]);
    }

    public function test_valid_file_creates_private_queryable_attachment_with_safe_name(): void
    {
        $form = $this->form('document', required: true);
        $file = UploadedFile::fake()->create('My Resume.pdf', 120, 'application/pdf');

        $this->post(route('forms.submit', $form->slug), ['resume' => $file])
            ->assertRedirect();

        $submission = FormSubmission::query()->with('attachments')->sole();
        $attachment = $submission->attachments->sole();

        $this->assertSame($submission->getKey(), $attachment->form_submission_id);
        $this->assertSame('resume', $attachment->field_key);
        $this->assertSame('My Resume.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertStringStartsWith("form-submissions/{$submission->getKey()}/", $attachment->stored_path);
        $this->assertStringNotContainsString('My Resume', $attachment->stored_path);
        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->assertDatabaseCount('media', 0);
        $this->assertTrue($submission->relationLoaded('attachments'));
        $this->assertSame([$attachment->getKey()], $submission->attachmentsByField()->get('resume')->modelKeys());
        $this->assertSame([$attachment->getKey()], $form->submissionAttachments()->pluck('form_submission_attachments.id')->all());
    }

    public function test_image_only_accepts_a_real_allowlisted_image(): void
    {
        $form = $this->form('image', required: true);

        $this->post(route('forms.submit', $form->slug), [
            'resume' => UploadedFile::fake()->image('portfolio.jpg'),
        ])->assertRedirect();

        $attachment = FormSubmissionAttachment::query()->sole();
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertStringEndsWith('.jpg', $attachment->stored_path);
    }

    public function test_required_modes_and_executable_allowlist_are_enforced(): void
    {
        $required = $this->form('all', required: true, slug: 'required-file');
        $this->post(route('forms.submit', $required->slug), [])->assertSessionHasErrors('resume');

        $imageOnly = $this->form('image', slug: 'image-only');
        $this->post(route('forms.submit', $imageOnly->slug), [
            'resume' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('resume');

        $documentOnly = $this->form('document', slug: 'document-only');
        $this->post(route('forms.submit', $documentOnly->slug), [
            'resume' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertSessionHasErrors('resume');

        $executable = $this->form('all', slug: 'executable');
        $this->post(route('forms.submit', $executable->slug), [
            'resume' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
        ])->assertSessionHasErrors('resume');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_submission_resource_eager_loads_attachments_and_presents_field_label(): void
    {
        $form = $this->form('document');
        $this->post(route('forms.submit', $form->slug), [
            'resume' => UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf'),
        ]);

        $submission = FormSubmissionResource::getEloquentQuery()->sole();
        $answers = app(FormSubmissionPresenter::class)->answers($submission);

        $this->assertTrue($submission->relationLoaded('attachments'));
        $this->assertSame(1, $submission->attachments_count);
        $this->assertSame('رزومه', $answers[0]['label']);
        $this->assertSame('resume.pdf', $answers[0]['value']);
    }

    public function test_deleting_submission_cleans_database_and_private_file_after_commit(): void
    {
        $form = $this->form('document');
        $this->post(route('forms.submit', $form->slug), [
            'resume' => UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf'),
        ]);
        $submission = FormSubmission::query()->sole();
        $path = $submission->attachments()->value('stored_path');

        $submission->delete();

        $this->assertDatabaseMissing('form_submissions', ['id' => $submission->getKey()]);
        $this->assertDatabaseCount('form_submission_attachments', 0);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_download_is_admin_only_and_uses_original_filename(): void
    {
        $form = $this->form('document');
        $this->post(route('forms.submit', $form->slug), [
            'resume' => UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf'),
        ]);
        $attachment = FormSubmissionAttachment::query()->sole();
        $url = route('admin.form-submission-attachments.download', $attachment);

        $this->get($url)->assertStatus(404);
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $response = $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->assertStringContainsString('resume.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_image_view_streams_inline_and_submission_thumbnail_uses_secure_route(): void
    {
        $form = $this->form('image');
        $this->post(route('forms.submit', $form->slug), [
            'resume' => UploadedFile::fake()->image('نمونه, portfolio.png'),
        ]);
        $attachment = FormSubmissionAttachment::query()->sole();
        $user = User::factory()->admin()->create();
        $viewUrl = route('admin.form-submission-attachments.view', $attachment);

        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->get($viewUrl)->assertStatus(404);
        $response = $this->actingAs($user)->get($viewUrl)->assertOk();

        $this->assertSame('image/png', $response->headers->get('content-type'));
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
        $this->assertSame((string) Storage::disk('local')->size($attachment->stored_path), $response->headers->get('content-length'));
        $this->get(FormSubmissionResource::getUrl('view', ['record' => $attachment->submission]))
            ->assertOk()->assertSee($viewUrl, false);
    }

    public function test_pdf_view_is_inline_and_download_disposition_handles_unicode_and_commas(): void
    {
        $form = $this->form('document');
        $this->post(route('forms.submit', $form->slug), [
            'resume' => UploadedFile::fake()->createWithContent('رزومه, final.pdf', '%PDF-1.4\n%%EOF'),
        ]);
        $attachment = FormSubmissionAttachment::query()->sole();
        $this->actingAs(User::factory()->admin()->create());

        $view = $this->get(route('admin.form-submission-attachments.view', $attachment))->assertOk();
        $download = $this->get(route('admin.form-submission-attachments.download', $attachment))->assertOk();

        $this->assertSame('application/pdf', $view->headers->get('content-type'));
        $this->assertStringStartsWith('inline;', (string) $view->headers->get('content-disposition'));
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('content-disposition'));
        $this->assertStringContainsString('filename*=', (string) $download->headers->get('content-disposition'));
    }

    public function test_missing_attachment_file_returns_controlled_404_and_logs_context(): void
    {
        Log::spy();
        $form = $this->form('document');
        $submission = $form->submissions()->create(['source' => 'website', 'payload' => [], 'submitted_at' => now()]);
        $attachment = $submission->attachments()->create([
            'field_key' => 'resume', 'stored_path' => 'form-submissions/missing.pdf',
            'original_name' => 'missing.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.form-submission-attachments.view', $attachment))->assertNotFound();

        Log::shouldHaveReceived('warning')->once()->with('Form submission attachment file is missing.', [
            'attachment_id' => $attachment->getKey(), 'disk' => 'local',
            'stored_path' => $attachment->stored_path, 'exists' => false,
        ]);
    }

    private function form(
        string $mode = 'all',
        bool $required = false,
        string $slug = 'file-form',
    ): Form {
        return Form::query()->create([
            'name' => 'فرم فایل',
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => Form::SCHEMA_VERSION,
            'schema' => ['fields' => [[
                'field_id' => '01JFILEFIELD000000000000000',
                'key' => 'resume',
                'name' => 'resume',
                'label' => 'رزومه',
                'type' => 'file',
                'required' => $required,
                'settings' => ['file_type' => $mode],
            ]]],
            'settings' => [],
        ]);
    }
}
