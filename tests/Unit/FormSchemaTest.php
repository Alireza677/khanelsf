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

        $this->assertSame(['name', 'email', 'name_2', 'field'], array_column($fields, 'name'));
        $this->assertSame('Your name', $fields[0]['label']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('Email', $fields[1]['label']);
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
            ]],
        ]);

        $fields = app(FormSchema::class)->fields($form);

        $this->assertSame([12, 3, 4, 12, 12], array_column(array_column($fields, 'layout'), 'span'));
        $this->assertSame($fields, app(FormSchema::class)->fields(new Form(['schema' => ['fields' => $fields]])));
    }
}
