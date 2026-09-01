<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Services\FormSchema;
use Illuminate\Validation\Rules\In;
use Tests\TestCase;

class FormSchemaTest extends TestCase
{
    public function test_it_normalizes_supported_fields_and_repairs_legacy_keys(): void
    {
        $form = new Form([
            'schema' => ['fields' => [
                ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email'],
                ['name' => 'name', 'label' => 'Duplicate', 'type' => 'text'],
                ['name' => 'bad-name', 'type' => 'text'],
                ['name' => 'amount', 'type' => 'number'],
                'invalid',
            ]],
        ]);

        $fields = app(FormSchema::class)->fields($form);

        $this->assertSame(['name', 'email', 'name_2', 'field', 'amount'], array_column($fields, 'name'));
        $this->assertSame('Your name', $fields[0]['label']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('Email', $fields[1]['label']);
        $this->assertSame([
            'thousands_separator' => false,
            'allow_decimals' => false,
            'decimal_places' => 2,
        ], $fields[4]['settings']);
    }

    public function test_it_builds_validation_rules_from_the_normalized_schema(): void
    {
        $form = new Form([
            'schema' => ['fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'message', 'type' => 'textarea', 'required' => false],
            ]],
        ]);

        $rules = app(FormSchema::class)->validationRules($form);

        $this->assertSame(['required', 'string', 'max:255', 'email:rfc'], $rules['email']);
        $this->assertSame(['nullable', 'string', 'max:5000'], $rules['message']);
    }

    public function test_it_normalizes_steps_and_rich_choice_options_from_the_form_schema(): void
    {
        $form = new Form([
            'type' => 'calculator',
            'schema' => ['fields' => [
                ['name' => 'project', 'label' => 'Project', 'type' => 'page'],
                [
                    'name' => 'building_type',
                    'label' => 'Building type',
                    'type' => 'image_choice',
                    'required' => true,
                    'options' => [[
                        'value' => 'villa',
                        'label' => 'Villa',
                        'image' => 'form-options/villa.jpg',
                        'scores' => ['prefab' => '3', 'invalid-key' => 9],
                    ]],
                ],
                ['name' => 'phone', 'type' => 'tel'],
            ]],
        ]);

        $fields = app(FormSchema::class)->fields($form);

        $this->assertSame(['project', 'building_type', 'phone'], array_column($fields, 'name'));
        $this->assertSame('page', $fields[0]['type']);
        $this->assertSame('villa', $fields[1]['options'][0]['value']);
        $this->assertSame('form-options/villa.jpg', $fields[1]['options'][0]['image']);
        $this->assertSame(['prefab' => 3], $fields[1]['options'][0]['scores']);
        $this->assertSame(
            ['required', 'string', 'max:255', In::class],
            array_map(fn (mixed $rule): string => is_object($rule) ? $rule::class : $rule, app(FormSchema::class)->validationRules($form)['building_type']),
        );
    }

    public function test_it_canonicalizes_allowed_legacy_invalid_and_structural_column_spans(): void
    {
        $form = new Form([
            'schema' => ['fields' => [
                ['key' => 'legacy', 'type' => 'text'],
                ['key' => 'quarter', 'type' => 'text', 'layout' => ['span' => 3]],
                ['key' => 'third', 'type' => 'text', 'layout' => ['span' => '4']],
                ['key' => 'invalid', 'type' => 'text', 'layout' => ['span' => 5]],
                ['key' => 'step', 'type' => 'page', 'layout' => ['span' => 3]],
                ['key' => 'section', 'type' => 'step', 'layout' => ['span' => 6]],
            ]],
        ]);

        $fields = app(FormSchema::class)->fields($form);

        $this->assertSame([12, 3, 4, 12, 12, 12], array_column(array_column($fields, 'layout'), 'span'));
        $this->assertSame($fields, app(FormSchema::class)->fields(new Form(['schema' => ['fields' => $fields]])));
    }

    public function test_structural_fields_never_create_validation_rules(): void
    {
        $form = new Form(['schema' => ['fields' => [
            ['key' => 'section', 'label' => 'Section', 'type' => 'step', 'required' => true],
            ['key' => 'page_two', 'label' => 'Page two', 'type' => 'page', 'required' => true],
            ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
        ]]]);

        $this->assertSame(['name'], array_keys(app(FormSchema::class)->validationRules($form)));
    }

    public function test_date_fields_normalize_fixed_boundaries_and_build_canonical_validation_rules(): void
    {
        $form = new Form(['schema' => ['fields' => [[
            'key' => 'visit_date',
            'label' => 'Visit date',
            'type' => 'date',
            'required' => true,
            'min_date' => '2026-08-20',
            'max_date' => '2026-08-30',
        ]]]]);

        $field = app(FormSchema::class)->fields($form)[0];
        $this->assertSame('2026-08-20', $field['min_date']);
        $this->assertSame('2026-08-30', $field['max_date']);
        $this->assertSame([
            'required',
            'string',
            'max:255',
            'date_format:Y-m-d',
            'after_or_equal:1971-03-21',
            'after_or_equal:2026-08-20',
            'before_or_equal:2026-08-30',
        ], app(FormSchema::class)->validationRules($form)['visit_date']);
    }

    public function test_date_range_rules_are_inactive_when_the_toggle_is_off(): void
    {
        $form = new Form(['schema' => ['fields' => [[
            'key' => 'visit_date',
            'type' => 'date',
            'date_range_enabled' => false,
            'min_date' => '2026-08-20',
            'max_date' => '2026-08-30',
        ]]]]);

        $field = app(FormSchema::class)->fields($form)[0];
        $this->assertFalse($field['date_range_enabled']);
        $this->assertSame([
            'nullable', 'string', 'max:255', 'date_format:Y-m-d', 'after_or_equal:1971-03-21',
        ], app(FormSchema::class)->validationRules($form)['visit_date']);
    }

    public function test_legacy_date_boundaries_enable_the_range_during_normalization(): void
    {
        $form = new Form(['schema' => ['fields' => [[
            'key' => 'legacy_date',
            'type' => 'date',
            'min_date' => '2020-01-01',
        ]]]]);

        $this->assertTrue(app(FormSchema::class)->fields($form)[0]['date_range_enabled']);
    }

    public function test_enabled_date_range_supports_only_one_boundary(): void
    {
        $minimumOnly = new Form(['schema' => ['fields' => [[
            'key' => 'minimum_only', 'type' => 'date', 'date_range_enabled' => true, 'min_date' => '2026-08-20',
        ]]]]);
        $maximumOnly = new Form(['schema' => ['fields' => [[
            'key' => 'maximum_only', 'type' => 'date', 'date_range_enabled' => true, 'max_date' => '2026-08-30',
        ]]]]);

        $minimumRules = app(FormSchema::class)->validationRules($minimumOnly)['minimum_only'];
        $maximumRules = app(FormSchema::class)->validationRules($maximumOnly)['maximum_only'];

        $this->assertContains('after_or_equal:2026-08-20', $minimumRules);
        $this->assertNotContains('before_or_equal:2026-08-30', $minimumRules);
        $this->assertContains('before_or_equal:2026-08-30', $maximumRules);
        $this->assertNotContains('after_or_equal:2026-08-20', $maximumRules);
    }
}
