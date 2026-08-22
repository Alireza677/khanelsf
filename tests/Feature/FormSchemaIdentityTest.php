<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\TestCase;

class FormSchemaIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const FIELD_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const OPTION_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    public function test_opening_legacy_form_hydrates_hidden_identity_without_writing_record(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->legacyForm();
        $before = $form->schema;

        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertDontSee('کلید فیلد')
            ->assertDontSee('مقدار ذخیره‌شده');
        $field = collect($component->get('data')['schema']['fields'])->first();

        $this->assertSame('contact_method', $field['key']);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $field['field_id']);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', collect($field['options'])->first()['option_id']);
        $this->assertSame($before, $form->fresh()->schema);
    }

    public function test_explicit_save_preserves_legacy_key_after_label_change_and_canonicalizes_identity(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->legacyForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);

        $component
            ->set("data.schema.fields.{$fieldKey}.label", 'A completely different label')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $form->fresh()->schema['fields'][0];

        $this->assertSame('contact_method', $saved['key']);
        $this->assertArrayNotHasKey('name', $saved);
        $this->assertSame('email', $saved['options'][0]['value']);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $saved['field_id']);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $saved['options'][0]['option_id']);
    }

    public function test_native_clone_gets_new_identity_at_save_and_reorder_preserves_it(): void
    {
        $this->actingAs(User::factory()->create());
        $form = Form::query()->create([
            'name' => 'Clone identity',
            'slug' => 'clone-identity',
            'status' => 'draft',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [[
                'field_id' => self::FIELD_ID,
                'key' => 'method',
                'label' => 'Method',
                'type' => 'select',
                'required' => true,
                'layout' => ['span' => 6],
                'options' => [[
                    'option_id' => self::OPTION_ID,
                    'value' => 'email',
                    'label' => 'Email',
                ]],
            ]]],
        ]);
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $itemKey = array_key_first($component->get('data')['schema']['fields']);

        $component
            ->callFormComponentAction('schema.fields', 'clone', arguments: ['item' => $itemKey])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $form->fresh()->schema['fields'];
        $fieldIds = array_column($saved, 'field_id');
        $optionIds = array_column(array_column($saved, 'options'), 0);

        $this->assertSame(['method', 'method_2'], array_column($saved, 'key'));
        $this->assertCount(2, array_unique($fieldIds));
        $this->assertCount(2, array_unique(array_column($optionIds, 'option_id')));
        $this->assertSame(['email', 'email'], array_column($optionIds, 'value'));
        $this->assertSame([6, 6], array_column(array_column($saved, 'layout'), 'span'));

        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $reversedKeys = array_reverse(array_keys($component->get('data')['schema']['fields']));
        $component
            ->callFormComponentAction('schema.fields', 'reorder', arguments: ['items' => $reversedKeys])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(array_reverse($fieldIds), array_column($form->fresh()->schema['fields'], 'field_id'));
        $this->assertSame([6, 6], array_column(array_column($form->fresh()->schema['fields'], 'layout'), 'span'));
    }

    public function test_two_render_instances_of_same_form_have_unique_dom_ids_and_compatible_names(): void
    {
        $form = $this->legacyForm(['status' => 'published']);
        $viewData = ['form' => $form, 'errors' => new ViewErrorBag];
        $html = view('forms._form', $viewData)->render()
            .view('forms._form', $viewData)->render();

        preg_match_all('/\sid="([^"]+)"/', $html, $matches);
        $ids = $matches[1];
        preg_match_all('/\sfor="([^"]+)"/', $html, $labelMatches);

        $this->assertNotEmpty($ids);
        $this->assertCount(count($ids), array_unique($ids));
        $this->assertEmpty(array_diff($labelMatches[1], $ids));
        $this->assertSame(4, substr_count($html, 'name="contact_method"'));
        $this->assertSame(2, substr_count($html, 'value="email"'));
    }

    private function legacyForm(array $overrides = []): Form
    {
        return Form::query()->create(array_merge([
            'name' => 'Legacy identity',
            'slug' => 'legacy-identity',
            'status' => 'draft',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 1,
            'schema' => ['fields' => [[
                'name' => 'contact_method',
                'label' => 'Contact method',
                'type' => 'radio_card',
                'required' => true,
                'options' => [
                    'email' => 'Email',
                    'phone' => 'Phone',
                ],
            ]]],
        ], $overrides));
    }
}
