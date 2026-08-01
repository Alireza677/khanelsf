<?php

namespace Tests\Unit;

use App\CMS\Blocks\CTA\CTALegacyActionAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CTALegacyActionAdapterTest extends TestCase
{
    #[DataProvider('legacyDestinations')]
    public function test_it_adapts_legacy_and_canonical_destinations(
        array $input,
        array $expected,
    ): void {
        $adapter = app(CTALegacyActionAdapter::class);
        $destination = $adapter->adapt($input);

        $this->assertSame($expected, $destination->toArray());
        $this->assertSame(
            $expected,
            $adapter->adapt($destination->toArray())->toArray(),
        );
    }

    public static function legacyDestinations(): array
    {
        return [
            'legacy URL' => [
                ['type' => 'url', 'url' => '/contact'],
                [
                    'schema_version' => 1,
                    'type' => 'custom_url',
                    'value' => '/contact',
                    'open_in_new_tab' => false,
                ],
            ],
            'legacy form' => [
                ['type' => 'form', 'form_id' => '4', 'display' => 'modal'],
                [
                    'schema_version' => 1,
                    'type' => 'form',
                    'reference_id' => 4,
                    'display' => 'modal',
                    'open_in_new_tab' => false,
                ],
            ],
            'canonical entity' => [
                ['schema_version' => 1, 'type' => 'service', 'reference_id' => 9],
                [
                    'schema_version' => 1,
                    'type' => 'service',
                    'reference_id' => 9,
                    'open_in_new_tab' => false,
                ],
            ],
        ];
    }

    #[DataProvider('emptyOrMalformed')]
    public function test_empty_and_malformed_destinations_fail_closed(array $input): void
    {
        $this->assertNull(app(CTALegacyActionAdapter::class)->adapt($input)->type);
    }

    public static function emptyOrMalformed(): array
    {
        return [
            'empty URL' => [['type' => 'url', 'url' => '']],
            'unsafe URL' => [['type' => 'url', 'url' => 'javascript:alert(1)']],
            'missing form' => [['type' => 'form', 'form_id' => null]],
            'unknown type' => [['type' => 'popup', 'url' => '/popup']],
            'empty' => [[]],
        ];
    }
}
