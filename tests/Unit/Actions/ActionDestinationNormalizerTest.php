<?php

namespace Tests\Unit\Actions;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActionDestinationNormalizerTest extends TestCase
{
    #[DataProvider('validTypes')]
    public function test_core_action_types_are_canonical(string $type): void
    {
        $this->assertSame($type, CoreActionType::fromInput($type)?->value);
    }

    public static function validTypes(): array
    {
        return array_map(
            static fn (CoreActionType $type): array => [$type->value],
            CoreActionType::cases(),
        );
    }

    #[DataProvider('invalidTypes')]
    public function test_unknown_empty_and_wrong_case_types_are_safe(mixed $type): void
    {
        $this->assertNull(CoreActionType::fromInput($type));

        $destination = $this->normalizer()->normalize(['type' => $type]);
        $this->assertFalse($destination->openInNewTab);
    }

    public static function invalidTypes(): array
    {
        return [
            'unknown' => ['popup'],
            'wrong case' => ['PAGE'],
            'empty' => [''],
            'null' => [null],
        ];
    }

    #[DataProvider('normalizationCases')]
    public function test_target_data_is_normalized_to_canonical_storage(
        array $input,
        array $expected,
    ): void {
        $destination = $this->normalizer()->normalize($input);

        $this->assertSame($expected, $destination->toArray());
        $this->assertSame(
            $destination->toArray(),
            $this->normalizer()->normalize($destination)->toArray(),
        );
        $this->assertSame(
            $destination->toArray(),
            $this->normalizer()->normalize($destination->toArray())->toArray(),
        );
    }

    public static function normalizationCases(): array
    {
        return [
            'absolute URL' => [
                ['type' => 'custom_url', 'value' => '  https://example.com/path?q=1  ', 'open_in_new_tab' => 'true'],
                ['schema_version' => 1, 'type' => 'custom_url', 'value' => 'https://example.com/path?q=1', 'open_in_new_tab' => true],
            ],
            'relative URL' => [
                ['type' => 'custom_url', 'value' => 'relative/path'],
                ['schema_version' => 1, 'type' => 'custom_url', 'value' => 'relative/path', 'open_in_new_tab' => false],
            ],
            'trimmed temporary placeholder' => [
                ['type' => 'custom_url', 'value' => " \t# \t"],
                ['schema_version' => 1, 'type' => 'custom_url', 'value' => '#', 'open_in_new_tab' => false],
            ],
            'page numeric string' => [
                ['type' => 'page', 'reference_id' => '12', 'value' => '/ignored', 'display' => 'modal', 'open_in_new_tab' => '1'],
                ['schema_version' => 1, 'type' => 'page', 'reference_id' => 12, 'open_in_new_tab' => true],
            ],
            'project integer' => [
                ['type' => 'project', 'reference_id' => 3],
                ['schema_version' => 1, 'type' => 'project', 'reference_id' => 3, 'open_in_new_tab' => false],
            ],
            'product integer' => [
                ['type' => 'product', 'reference_id' => 4],
                ['schema_version' => 1, 'type' => 'product', 'reference_id' => 4, 'open_in_new_tab' => false],
            ],
            'service integer' => [
                ['type' => 'service', 'reference_id' => 5],
                ['schema_version' => 1, 'type' => 'service', 'reference_id' => 5, 'open_in_new_tab' => false],
            ],
            'form explicit modal' => [
                ['type' => 'form', 'reference_id' => '4', 'display' => 'modal', 'open_in_new_tab' => true],
                ['schema_version' => 1, 'type' => 'form', 'reference_id' => 4, 'display' => 'modal', 'open_in_new_tab' => false],
            ],
            'form legacy display fallback' => [
                ['type' => 'form', 'reference_id' => 4, 'display' => null],
                ['schema_version' => 1, 'type' => 'form', 'reference_id' => 4, 'open_in_new_tab' => false],
            ],
            'anchor prefix' => [
                ['type' => 'anchor', 'value' => '  #contact  ', 'open_in_new_tab' => 'yes'],
                ['schema_version' => 1, 'type' => 'anchor', 'value' => 'contact', 'open_in_new_tab' => false],
            ],
            'email prefix preserves address case' => [
                ['type' => 'email', 'value' => ' MAILTO:Info@Example.com '],
                ['schema_version' => 1, 'type' => 'email', 'value' => 'Info@Example.com', 'open_in_new_tab' => false],
            ],
            'phone prefix and display separators' => [
                ['type' => 'phone', 'value' => ' tel:+98 (912) 123-4567 '],
                ['schema_version' => 1, 'type' => 'phone', 'value' => '+989121234567', 'open_in_new_tab' => false],
            ],
            'unknown version remains detectable' => [
                ['schema_version' => 9, 'type' => 'page', 'reference_id' => 1],
                ['schema_version' => 9, 'type' => 'page', 'reference_id' => 1, 'open_in_new_tab' => false],
            ],
        ];
    }

    public function test_internal_and_storage_shapes_are_explicitly_distinct(): void
    {
        $destination = new ActionDestination(
            type: 'page',
            referenceId: 12,
        );

        $this->assertSame([
            'schema_version' => 1,
            'type' => 'page',
            'reference_id' => 12,
            'value' => null,
            'display' => null,
            'open_in_new_tab' => false,
        ], $destination->toInternalArray());
        $this->assertSame([
            'schema_version' => 1,
            'type' => 'page',
            'reference_id' => 12,
            'open_in_new_tab' => false,
        ], $destination->toArray());
    }

    public function test_value_object_has_a_safe_array_factory_and_round_trip(): void
    {
        $destination = ActionDestination::fromArray([
            'type' => 'form',
            'reference_id' => '7',
            'display' => 'page',
        ]);

        $this->assertSame([
            'schema_version' => 1,
            'type' => 'form',
            'reference_id' => 7,
            'display' => 'page',
            'open_in_new_tab' => false,
        ], $destination->toArray());
        $this->assertSame(
            $destination->toArray(),
            ActionDestination::fromArray($destination->toArray())->toArray(),
        );
    }

    private function normalizer(): ActionDestinationNormalizer
    {
        return new ActionDestinationNormalizer;
    }
}
