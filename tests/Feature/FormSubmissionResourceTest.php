<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\ListForms;
use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\FormSubmissionResource\Pages\ListFormSubmissions;
use App\Filament\Resources\LeadResource;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\User;
use App\Services\SubmissionAnswerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormSubmissionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_index_is_generic_and_can_be_filtered_by_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $firstForm = $this->form('درخواست مشاوره', 'consultation');
        $secondForm = $this->form('ثبت سفارش ویژه', 'special-order');
        $first = $this->submission($firstForm, ['full_name' => 'علی رضایی']);
        $second = $this->submission($secondForm, ['customer_name' => 'شرکت نمونه']);

        Livewire::test(ListFormSubmissions::class)
            ->assertOk()
            ->assertTableColumnExists('id')
            ->assertTableColumnExists('form.name')
            ->assertTableColumnExists('submitter_name')
            ->assertTableColumnExists('source')
            ->assertTableColumnExists('entry_status')
            ->assertTableColumnExists('submitted_at')
            ->assertTableColumnDoesNotExist('calculation_result.result')
            ->assertCanSeeTableRecords([$first, $second])
            ->filterTable('form_id', $firstForm->getKey())
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);
    }

    public function test_form_index_links_to_entries_using_the_form_filter_and_edit_has_no_submission_relation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form('درخواست مشاوره ساخت', 'construction-consultation');
        $url = FormSubmissionResource::getUrl('index', [
            'tableFilters' => ['form_id' => ['value' => $form->getKey()]],
        ]);

        Livewire::test(ListForms::class)
            ->assertOk()
            ->assertTableActionExists('entries')
            ->assertSee('ورودی‌ها')
            ->assertSeeHtml(e($url));

        $this->assertSame([], FormResource::getRelations());
        $this->assertFalse(FormSubmissionResource::shouldRegisterNavigation());

        $navigationItem = FormResource::getNavigationItems()[0];
        $childItems = $navigationItem->getChildItems();

        $this->assertNull($navigationItem->getUrl());
        $this->assertSame('فرم‌ها', $navigationItem->getLabel());
        $this->assertSame(
            ['نمایش همه فرم‌ها', 'ورودی‌ها'],
            array_map(fn ($item): string => $item->getLabel(), $childItems),
        );
        $this->assertSame(FormResource::getUrl('index'), $childItems[0]->getUrl());
        $this->assertSame(FormSubmissionResource::getUrl('index'), $childItems[1]->getUrl());

        $this->get(FormResource::getUrl('index'))
            ->assertOk()
            ->assertSee('cms-sidebar-parent-item', false)
            ->assertSee('aria-haspopup="true"', false)
            ->assertSee('نمایش همه فرم‌ها')
            ->assertSee('ورودی‌ها');
    }

    public function test_an_admin_can_delete_an_individual_submission_from_the_entries_table(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $submission = $this->submission(
            $this->form('فرم قابل حذف', 'deletable-form'),
            ['name' => 'ورودی آزمایشی'],
        );

        Livewire::test(ListFormSubmissions::class)
            ->assertTableActionExists('delete', record: $submission)
            ->callTableAction('delete', $submission);

        $this->assertDatabaseMissing('form_submissions', ['id' => $submission->getKey()]);
    }

    public function test_detail_uses_snapshot_and_hides_special_sections_when_absent(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form('فرم عمومی', 'generic-form');
        $submission = $this->submission($form, [
            'email' => 'person@example.com',
            'unknown_future_field' => ['one', 'two'],
            SubmissionAnswerSnapshot::PAYLOAD_KEY => [[
                'field_key' => 'unknown_future_field',
                'field_label' => 'فیلد آینده',
                'raw_value' => ['one', 'two'],
                'display_value' => 'گزینه یک و دو',
            ]],
        ]);
        $form->update(['schema' => ['fields' => [[
            'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            'key' => 'unknown_future_field',
            'label' => 'برچسب تغییر یافته',
            'type' => 'text',
        ]]]]);

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('خلاصه ورودی')
            ->assertSee('اطلاعات ارسال‌کننده')
            ->assertSee('person@example.com')
            ->assertSee('فیلد آینده')
            ->assertSee('گزینه یک و دو')
            ->assertDontSee('برچسب تغییر یافته')
            ->assertSee('سرنخ مرتبطی برای این ورودی وجود ندارد.')
            ->assertSee('اطلاعات فنی')
            ->assertSee('unknown_future_field')
            ->assertDontSee('نتیجه محاسبه');
    }

    public function test_submission_detail_links_to_its_existing_lead_without_creating_another_one(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form('فرم دارای سرنخ', 'form-with-lead');
        $submission = $this->submission($form, [
            'name' => 'کاربر مرتبط',
            SubmissionAnswerSnapshot::PAYLOAD_KEY => [[
                'field_key' => 'name',
                'field_label' => 'نام',
                'raw_value' => 'کاربر مرتبط',
                'display_value' => 'کاربر مرتبط',
            ]],
        ]);
        $lead = Lead::query()->create([
            'form_submission_id' => $submission->getKey(),
            'form_id' => $form->getKey(),
            'name' => 'کاربر مرتبط',
            'status' => 'new',
            'source' => 'website',
        ]);

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('سرنخ مرتبط')
            ->assertSee('مشاهده سرنخ')
            ->assertSee(LeadResource::getUrl('view', ['record' => $lead]), false)
            ->assertDontSee('سرنخی مرتبط نیست');

        $this->assertSame(1, Lead::query()->count());
    }

    public function test_calculation_section_is_only_added_when_special_data_exists(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form('فرم محاسباتی', 'calculator-form');
        $submission = $this->submission($form, ['name' => 'کاربر محاسبه'], [
            'result' => 'پیشنهاد نمونه',
            'scores' => ['option_a' => 12],
            'score_labels' => ['option_a' => 'گزینه الف'],
        ]);

        $this->get(FormSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertOk()
            ->assertSee('نتیجه محاسبه')
            ->assertSee('پیشنهاد نمونه')
            ->assertSee('گزینه الف')
            ->assertSee('12');
    }

    private function form(string $name, string $slug): Form
    {
        return Form::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => []],
            'settings' => [],
        ]);
    }

    private function submission(Form $form, array $payload, ?array $calculationResult = null): FormSubmission
    {
        return $form->submissions()->create([
            'source' => 'website',
            'payload' => $payload,
            'calculation_result' => $calculationResult,
            'submitted_at' => now(),
        ]);
    }
}
