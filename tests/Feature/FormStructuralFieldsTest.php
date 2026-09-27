<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormStructuralFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_dividers_stay_on_one_page_and_render_full_width_without_navigation(): void
    {
        $form = $this->form([
            $this->field('first_name', 'نام', 6),
            $this->field('phone', 'موبایل', 6),
            $this->marker('project_section', 'اطلاعات پروژه', 'step', 6),
            $this->field('project_type', 'نوع پروژه', 6),
            $this->marker('contact_section', 'اطلاعات تماس تکمیلی', 'step', 3),
            $this->field('notes', 'توضیحات', 6),
        ]);

        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-form-step='));
        $this->assertSame(2, substr_count($html, 'class="form-section-divider"'));
        $this->assertSame(2, substr_count($html, 'class="form-section-divider__title"'));
        $this->assertDoesNotMatchRegularExpression('/<h[1-6][^>]*>\s*(?:اطلاعات پروژه|اطلاعات تماس تکمیلی)\s*<\/h[1-6]>/u', $html);
        $this->assertSame(2, substr_count($html, 'form-field--span-12'));
        $this->assertStringNotContainsString('data-multi-step-form', $html);
        $this->assertStringNotContainsString('data-step-next', $html);
        $this->assertStringNotContainsString('form-step-indicator', $html);
        $this->assertLessThan(strpos($html, 'name="project_type"'), strpos($html, 'اطلاعات پروژه'));
    }

    public function test_only_page_markers_split_pages_and_increment_the_stepper(): void
    {
        $form = $this->form([
            $this->field('name', 'نام'),
            $this->marker('section_one', 'بخش اول', 'step'),
            $this->field('project_type', 'نوع پروژه'),
            $this->marker('page_two', 'اطلاعات مکانی', 'page'),
            $this->marker('section_two', 'بخش دوم', 'step'),
            $this->field('city', 'شهر'),
        ]);

        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-form-step='));
        $this->assertSame(2, substr_count($html, 'class="form-section-divider"'));
        $this->assertStringContainsString('data-form-page', $html);
        $this->assertStringContainsString('مرحله ۱ از ۲', $html);
        $this->assertStringContainsString('data-page-next', $html);
        $this->assertStringContainsString('data-page-back', $html);
    }

    public function test_section_dividers_never_enter_submission_payload(): void
    {
        $form = $this->form([
            $this->field('name', 'نام'),
            $this->marker('project_section', 'اطلاعات پروژه', 'step'),
            $this->field('city', 'شهر'),
        ]);

        $this->post(route('forms.submit', $form->slug), [
            'name' => 'کاربر',
            'project_section' => 'مقدار ناخواسته',
            'city' => 'تهران',
        ])->assertRedirect();

        $payload = FormSubmission::query()->sole()->payload;
        $this->assertSame('کاربر', $payload['name']);
        $this->assertSame('تهران', $payload['city']);
        $this->assertArrayNotHasKey('project_section', $payload);
    }

    public function test_section_divider_css_is_full_width_and_mobile_safe(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.form-section-divider__line {', $css);
        $this->assertStringContainsString('.form-section-divider__title {', $css);
        $this->assertStringNotContainsString('.form-section-divider h3', $css);
        $this->assertStringContainsString('background: #e2e8f0;', $css);
        $this->assertStringContainsString('height: 1px;', $css);
        $this->assertStringContainsString('width: 100%;', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $css);
        $this->assertStringContainsString('.form-field[class*="form-field--span-"]', $css);
    }

    private function form(array $fields): Form
    {
        return Form::query()->create([
            'name' => 'Structural Form',
            'slug' => 'structural-form-'.Form::query()->count(),
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => $fields],
            'settings' => [],
        ]);
    }

    private function field(string $key, string $label, int $span = 12): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'required' => false, 'layout' => ['span' => $span]];
    }

    private function marker(string $key, string $label, string $type, int $span = 12): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'description' => null, 'layout' => ['span' => $span]];
    }
}
