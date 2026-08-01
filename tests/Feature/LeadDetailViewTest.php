<?php

namespace Tests\Feature;

use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\LeadResource;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Page;
use App\Models\User;
use App\Services\FormSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDetailViewTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_form_lead_detail_shows_context_and_human_readable_answers(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $page = Page::factory()->published()->create(['title' => 'صفحه مشاوره']);
        $form = $this->normalForm();
        $submission = app(FormSubmissionService::class)->submit($form, [
            'name' => 'مشتری تست',
            'contact_method' => 'phone_option',
        ], [
            'source' => 'website',
            'page_id' => $page->getKey(),
            'page_url' => '/consultation',
            'block_id' => self::BLOCK_ID,
        ]);

        $this->get(LeadResource::getUrl('view', ['record' => $submission->lead]))
            ->assertOk()
            ->assertSee('اطلاعات فرم')
            ->assertSee('مشاهده ورودی فرم')
            ->assertSee(FormSubmissionResource::getUrl('view', ['record' => $submission]), false)
            ->assertSee('پاسخ‌های کاربر')
            ->assertSee('فرم مشاوره')
            ->assertSee('زمان ارسال')
            ->assertSee('وب‌سایت')
            ->assertSee('صفحه مشاوره')
            ->assertSee('/consultation')
            ->assertSee(self::BLOCK_ID)
            ->assertSee('روش تماس')
            ->assertSee('تماس تلفنی')
            ->assertDontSee('contact_method')
            ->assertDontSee('phone_option')
            ->assertDontSee('نتیجه محاسبه');
    }

    public function test_calculator_lead_detail_uses_stored_result_and_answer_labels(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->calculatorForm();
        $submission = app(FormSubmissionService::class)->submit($form, [
            'building_type' => 'villa_internal',
            'name' => 'مشتری محاسبه‌گر',
        ]);

        $this->get(LeadResource::getUrl('view', ['record' => $submission->lead]))
            ->assertOk()
            ->assertSee('پاسخ‌های کاربر')
            ->assertSee('نوع ساختمان')
            ->assertSee('ویلا')
            ->assertSee('نتیجه محاسبه')
            ->assertSee('پیشنهاد نهایی')
            ->assertSee('پیش ساخته')
            ->assertSee('مونتاژ در سایت')
            ->assertSee('امتیاز نتایج')
            ->assertDontSee('building_type')
            ->assertDontSee('villa_internal')
            ->assertDontSee('prefabricated');
    }

    public function test_manual_lead_without_submission_renders_safely(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $lead = Lead::query()->create([
            'name' => 'سرنخ دستی',
            'status' => 'new',
            'source' => 'manual',
        ]);

        $this->get(LeadResource::getUrl('view', ['record' => $lead]))
            ->assertOk()
            ->assertSee('سرنخ دستی')
            ->assertDontSee('مشاهده ورودی فرم')
            ->assertDontSee('اطلاعات فرم')
            ->assertDontSee('پاسخ‌های کاربر')
            ->assertDontSee('نتیجه محاسبه');
    }

    private function normalForm(): Form
    {
        return Form::query()->create([
            'name' => 'فرم مشاوره',
            'slug' => 'lead-detail-normal',
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [
                ['name' => 'name', 'label' => 'نام', 'type' => 'text'],
                [
                    'name' => 'contact_method',
                    'label' => 'روش تماس',
                    'type' => 'select',
                    'options' => ['phone_option' => 'تماس تلفنی'],
                ],
            ]],
        ]);
    }

    private function calculatorForm(): Form
    {
        return Form::query()->create([
            'name' => 'محاسبه سازه',
            'slug' => 'lead-detail-calculator',
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'calculator',
            'calculator_identifier' => 'lead_detail_v1',
            'schema_version' => 2,
            'schema' => [
                'fields' => [
                    [
                        'name' => 'building_type',
                        'label' => 'نوع ساختمان',
                        'type' => 'radio_card',
                        'required' => true,
                        'options' => [[
                            'value' => 'villa_internal',
                            'label' => 'ویلا',
                            'scores' => ['prefabricated' => 3, 'site_assembly' => 1],
                        ]],
                    ],
                    ['name' => 'name', 'label' => 'نام', 'type' => 'text'],
                ],
                'calculator' => ['recommendations' => [
                    'prefabricated' => 'پیش ساخته',
                    'site_assembly' => 'مونتاژ در سایت',
                ]],
            ],
        ]);
    }
}
