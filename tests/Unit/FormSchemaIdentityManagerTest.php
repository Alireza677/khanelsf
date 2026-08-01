<?php

namespace Tests\Unit;

use App\Services\FormSchemaIdentityManager;
use Tests\TestCase;

class FormSchemaIdentityManagerTest extends TestCase
{
    private const FIRST = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const SECOND = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    private const THIRD = '01ARZ3NDEKTSV4RRFFQ69G5FAX';

    private const FOURTH = '01ARZ3NDEKTSV4RRFFQ69G5FAY';

    private const FIFTH = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';

    public function test_it_generates_collision_safe_per_form_keys_and_field_ids(): void
    {
        $result = app(FormSchemaIdentityManager::class)->canonicalize([
            ['label' => 'Project Name', 'type' => 'text'],
            ['label' => 'Project Name', 'type' => 'text'],
            ['label' => '✨', 'type' => 'text'],
            ['label' => '✨', 'type' => 'text'],
            ['name' => 'legacy_key', 'label' => 'Changed label', 'type' => 'text'],
        ]);

        $this->assertSame(
            ['project_name', 'project_name_2', 'field', 'field_2', 'legacy_key'],
            array_column($result, 'key'),
        );
        $this->assertCount(5, array_unique(array_column($result, 'field_id')));
        $this->assertContainsOnly('string', array_column($result, 'field_id'));
        $this->assertArrayNotHasKey('name', $result[4]);
    }

    public function test_label_changes_and_reorder_preserve_persisted_keys_and_ids(): void
    {
        $canonical = app(FormSchemaIdentityManager::class)->canonicalize([
            ['label' => 'First field', 'type' => 'text'],
            ['label' => 'Second field', 'type' => 'text'],
        ]);
        $canonical[0]['label'] = 'Completely renamed';

        $renamed = app(FormSchemaIdentityManager::class)->canonicalize($canonical);
        $reordered = app(FormSchemaIdentityManager::class)->canonicalize(array_reverse($renamed));

        $this->assertSame('first_field', $renamed[0]['key']);
        $this->assertSame($canonical[0]['field_id'], $renamed[0]['field_id']);
        $this->assertSame(array_reverse(array_column($renamed, 'field_id')), array_column($reordered, 'field_id'));
        $this->assertSame(array_reverse(array_column($renamed, 'key')), array_column($reordered, 'key'));
    }

    public function test_field_keys_are_scoped_to_each_form_canonicalization(): void
    {
        $firstForm = app(FormSchemaIdentityManager::class)->canonicalize([
            ['label' => 'Project name', 'type' => 'text'],
        ]);
        $secondForm = app(FormSchemaIdentityManager::class)->canonicalize([
            ['label' => 'Project name', 'type' => 'text'],
        ]);

        $this->assertSame('project_name', $firstForm[0]['key']);
        $this->assertSame('project_name', $secondForm[0]['key']);
    }

    public function test_missing_invalid_and_duplicate_field_and_option_ids_are_repaired(): void
    {
        $manager = $this->manager([self::SECOND, self::THIRD, self::FOURTH, self::FIFTH]);
        $result = $manager->canonicalize([
            [
                'field_id' => self::FIRST,
                'key' => 'choice',
                'label' => 'Choice',
                'type' => 'select',
                'options' => [
                    ['option_id' => self::SECOND, 'value' => 'one', 'label' => 'One'],
                    ['option_id' => self::SECOND, 'value' => 'two', 'label' => 'Two'],
                ],
            ],
            ['field_id' => self::FIRST, 'key' => 'choice', 'label' => 'Clone', 'type' => 'text'],
        ]);

        $this->assertSame(self::FIRST, $result[0]['field_id']);
        $this->assertSame(self::FOURTH, $result[1]['field_id']);
        $this->assertSame(['choice', 'choice_2'], array_column($result, 'key'));
        $this->assertSame(self::SECOND, $result[0]['options'][0]['option_id']);
        $this->assertSame(self::THIRD, $result[0]['options'][1]['option_id']);
    }

    public function test_option_values_are_generated_once_and_duplicate_values_are_repaired(): void
    {
        $canonical = app(FormSchemaIdentityManager::class)->canonicalize([[
            'key' => 'choice',
            'label' => 'Choice',
            'type' => 'select',
            'options' => [
                ['label' => 'First option'],
                ['label' => 'First option'],
                ['value' => 'legacy-value', 'label' => 'Legacy'],
                ['value' => 'legacy-value', 'label' => 'Replacement'],
            ],
        ]]);

        $this->assertSame(
            ['first_option', 'first_option_2', 'legacy-value', 'replacement'],
            array_column($canonical[0]['options'], 'value'),
        );
        $this->assertCount(4, array_unique(array_column($canonical[0]['options'], 'option_id')));

        foreach ($canonical[0]['options'] as &$option) {
            $option['label'] = 'Renamed';
        }
        unset($option);

        $renamed = app(FormSchemaIdentityManager::class)->canonicalize($canonical);

        $this->assertSame(
            ['first_option', 'first_option_2', 'legacy-value', 'replacement'],
            array_column($renamed[0]['options'], 'value'),
        );
    }

    public function test_cloned_field_repairs_field_and_option_identity_without_changing_safe_values(): void
    {
        $field = [
            'field_id' => self::FIRST,
            'key' => 'building_type',
            'label' => 'Building type',
            'type' => 'radio_card',
            'options' => [[
                'option_id' => self::SECOND,
                'value' => 'villa',
                'label' => 'Villa',
            ]],
        ];
        $result = $this->manager([self::THIRD, self::FOURTH])->canonicalize([$field, $field]);

        $this->assertSame(['building_type', 'building_type_2'], array_column($result, 'key'));
        $this->assertSame([self::FIRST, self::THIRD], array_column($result, 'field_id'));
        $this->assertSame(self::SECOND, $result[0]['options'][0]['option_id']);
        $this->assertSame(self::FOURTH, $result[1]['options'][0]['option_id']);
        $this->assertSame('villa', $result[0]['options'][0]['value']);
        $this->assertSame('villa', $result[1]['options'][0]['value']);
    }

    public function test_legacy_associative_options_are_canonicalized_without_losing_values_or_labels(): void
    {
        $result = app(FormSchemaIdentityManager::class)->canonicalize([[
            'name' => 'legacy_select',
            'label' => 'Legacy select',
            'type' => 'select',
            'options' => ['old_value' => 'Old label'],
        ]]);

        $this->assertSame('legacy_select', $result[0]['key']);
        $this->assertSame('old_value', $result[0]['options'][0]['value']);
        $this->assertSame('Old label', $result[0]['options'][0]['label']);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $result[0]['field_id']);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $result[0]['options'][0]['option_id']);
    }

    private function manager(array $ids): FormSchemaIdentityManager
    {
        return new FormSchemaIdentityManager(function () use (&$ids): string {
            return array_shift($ids) ?? throw new \RuntimeException('Identity test factory exhausted.');
        });
    }
}
