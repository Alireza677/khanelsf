<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSelectPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_uses_accessible_custom_listbox_while_preserving_native_submission_control(): void
    {
        $form = $this->form();

        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();

        $this->assertStringContainsString('data-form-select', $html);
        $this->assertStringContainsString('class="form-select__native"', $html);
        $this->assertStringContainsString('name="city"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('role="option"', $html);
        $this->assertStringContainsString('انتخاب کنید', $html);
        $this->assertStringContainsString('<html lang="fa" dir="rtl">', $html);
    }

    public function test_selected_value_submission_old_input_and_validation_remain_canonical(): void
    {
        $form = $this->form(twoSelects: true);
        $url = route('forms.show', $form->slug);

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'city' => 'tehran',
            'service' => '',
        ])->assertRedirect($url);

        $invalid = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('data-value="tehran" aria-selected="true"', $invalid);
        $this->assertStringContainsString('aria-invalid="true"', $invalid);
        $this->assertSame(1, substr_count($invalid, 'لطفا فرم را بررسی کنید'));

        $this->from($url)->post(route('forms.submit', $form->slug), [
            'city' => 'tehran',
            'service' => 'design',
        ])->assertRedirect($url);

        $payload = FormSubmission::query()->sole()->payload;
        $this->assertSame('tehran', $payload['city']);
        $this->assertSame('design', $payload['service']);
    }

    public function test_multiple_instances_and_modal_render_unique_independent_selects(): void
    {
        $form = $this->form();
        $page = Page::factory()->published()->create([
            'slug' => 'multiple-custom-selects',
            'blocks' => [
                $this->block($form, '01ARZ3NDEKTSV4RRFFQ69G5FAV'),
                $this->block($form, '01ARZ3NDEKTSV4RRFFQ69G5FAX'),
            ],
        ]);

        $embedded = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();
        $modal = $this->post(route('forms.modal', $form->slug))->assertOk()->getContent();

        $this->assertSame(2, substr_count($embedded, 'data-form-select dir="auto"'));
        preg_match_all('/id="([^"]+-listbox)"/', $embedded, $matches);
        $this->assertCount(2, array_unique($matches[1]));
        $this->assertStringContainsString('data-form-select', $modal);
    }

    public function test_javascript_contract_covers_keyboard_outside_close_and_dynamic_modal_initialization(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        foreach (['ArrowDown', 'ArrowUp', 'Enter', 'Escape', 'Tab', "event.key === ' '"] as $contract) {
            $this->assertStringContainsString($contract, $source);
        }

        $this->assertStringContainsString('if (! root.contains(event.target)) close()', $source);
        $this->assertStringContainsString('form-select:opening', $source);
        $this->assertStringContainsString("document.addEventListener('forms:rendered'", $source);
        $this->assertStringContainsString('root.dataset.formSelectReady', $source);
    }

    public function test_custom_select_css_defines_closed_open_selected_focus_and_invalid_states(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        foreach ([
            '.form-select__trigger',
            '.form-select.is-open .form-select__trigger',
            '.form-select__panel',
            '.form-select__option.is-active',
            '.form-select__option[aria-selected="true"]',
            '.form-select__trigger[aria-invalid="true"]',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertStringContainsString('max-height: min(16rem, calc(100dvh - 8rem));', $css);
        $this->assertStringContainsString('overflow-y: auto;', $css);
        $this->assertStringContainsString('overscroll-behavior-y: contain;', $css);
        $this->assertStringContainsString('touch-action: pan-y;', $css);
        $this->assertStringContainsString('-webkit-overflow-scrolling: touch;', $css);

        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('const positionPanel = () =>', $script);
        $this->assertStringContainsString('window.visualViewport?.height', $script);
        $this->assertStringContainsString("root.classList.toggle('is-open-up'", $script);
        $this->assertStringContainsString("scrollIntoView({ block: 'nearest' })", $script);
    }

    private function form(bool $twoSelects = false): Form
    {
        $fields = [$this->selectField('city', 'شهر', ['tehran' => 'تهران', 'shiraz' => 'شیراز'])];

        if ($twoSelects) {
            $fields[] = $this->selectField('service', 'خدمت', ['design' => 'طراحی', 'build' => 'اجرا']);
        }

        return Form::query()->create([
            'name' => 'فرم انتخاب',
            'slug' => 'select-form-'.Form::query()->count(),
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => $fields],
            'settings' => [],
        ]);
    }

    private function selectField(string $key, string $label, array $options): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'required' => true,
            'options' => collect($options)->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])->values()->all(),
        ];
    }

    private function block(Form $form, string $blockId): array
    {
        return [
            'type' => 'form',
            'data' => [
                'block_id' => $blockId,
                'schema_version' => 1,
                'template' => 'default',
                'content' => ['form_id' => $form->getKey()],
            ],
        ];
    }
}
