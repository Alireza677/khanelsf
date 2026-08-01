<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Mail\FormSubmissionAdminMail;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\User;
use App\Services\FormNotifications\Contracts\FormSubmissionNotificationChannel;
use App\Services\FormSubmissionService;
use Filament\Forms\Components\Component;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class FormSubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_form_email_notification_is_sent_after_the_existing_submission_flow(): void
    {
        Mail::fake();
        $form = $this->form([
            'notifications' => [
                'enabled' => true,
                'notify_admin' => true,
                'email' => 'forms@example.com',
            ],
        ]);

        $submission = app(FormSubmissionService::class)->submit($form, [
            'name' => 'کاربر اعلان',
            'email' => 'sender@example.com',
        ]);

        Mail::assertSent(FormSubmissionAdminMail::class, function (FormSubmissionAdminMail $mail) use ($submission): bool {
            return $mail->hasTo('forms@example.com')
                && $mail->submission->is($submission)
                && str_contains($mail->render(), 'کاربر اعلان');
        });
        $this->assertSame(1, FormSubmission::query()->count());
        $this->assertSame(1, Lead::query()->count());
        $this->assertSame($submission->getKey(), Lead::query()->sole()->form_submission_id);
    }

    public function test_disabled_or_incomplete_notifications_do_not_send_email(): void
    {
        Mail::fake();

        app(FormSubmissionService::class)->submit($this->form([
            'notifications' => [
                'enabled' => false,
                'notify_admin' => true,
                'email' => 'forms@example.com',
            ],
        ], 'disabled-notifications'), ['email' => 'first@example.com']);

        app(FormSubmissionService::class)->submit($this->form([
            'notifications' => [
                'enabled' => true,
                'notify_admin' => false,
                'email' => 'forms@example.com',
            ],
        ], 'admin-notification-disabled'), ['email' => 'second@example.com']);

        Mail::assertNothingSent();
        $this->assertSame(2, FormSubmission::query()->count());
        $this->assertSame(2, Lead::query()->count());
    }

    public function test_notification_channel_failure_does_not_roll_back_submission_or_lead(): void
    {
        Mail::fake();
        config()->set('cms.form_notifications.channels', [FailingFormSubmissionNotificationChannel::class]);
        $form = $this->form([
            'notifications' => [
                'enabled' => true,
                'notify_admin' => true,
                'email' => 'forms@example.com',
            ],
        ], 'failing-notification');

        $submission = app(FormSubmissionService::class)->submit($form, ['email' => 'safe@example.com']);

        $this->assertTrue($submission->exists);
        $this->assertDatabaseHas('form_submissions', ['id' => $submission->getKey()]);
        $this->assertDatabaseHas('leads', ['form_submission_id' => $submission->getKey()]);
    }

    public function test_form_editor_stores_notification_configuration_inside_form_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form([], 'notification-editor');
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldNames = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->filter(fn (Component $field): bool => method_exists($field, 'getName'))
            ->map(fn (Component $field): string => $field->getName())
            ->all();

        $this->assertContains('settings.notifications.enabled', $fieldNames);
        $this->assertContains('settings.notifications.notify_admin', $fieldNames);
        $this->assertContains('settings.notifications.email', $fieldNames);

        $component
            ->set('data.settings.notifications.enabled', true)
            ->set('data.settings.notifications.notify_admin', true)
            ->set('data.settings.notifications.email', 'admin@example.com')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([
            'enabled' => true,
            'notify_admin' => true,
            'email' => 'admin@example.com',
        ], data_get($form->fresh()->settings, 'notifications'));
    }

    private function form(array $settings, string $slug = 'notification-form'): Form
    {
        return Form::query()->create([
            'name' => 'فرم اعلان',
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [
                [
                    'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                    'key' => 'name',
                    'label' => 'نام',
                    'type' => 'text',
                    'required' => false,
                ],
                [
                    'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
                    'key' => 'email',
                    'label' => 'ایمیل',
                    'type' => 'email',
                    'required' => false,
                ],
            ]],
            'settings' => $settings,
        ]);
    }
}

final class FailingFormSubmissionNotificationChannel implements FormSubmissionNotificationChannel
{
    public function supports(array $settings): bool
    {
        return true;
    }

    public function send(Form $form, FormSubmission $submission, array $settings): void
    {
        throw new RuntimeException('Notification transport failed.');
    }
}
