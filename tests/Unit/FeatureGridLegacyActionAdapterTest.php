<?php

namespace Tests\Unit;

use App\CMS\Blocks\FeatureGrid\FeatureGridLegacyActionAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeatureGridLegacyActionAdapterTest extends TestCase
{
    #[DataProvider('validDestinations')]
    public function test_it_adapts_legacy_and_canonical_destinations(
        array $input,
        array $expected,
    ): void {
        $adapter = app(FeatureGridLegacyActionAdapter::class);
        $destination = $adapter->adapt($input);

        $this->assertSame($expected, $destination->toArray());
        $this->assertSame(
            $expected,
            $adapter->adapt($destination->toArray())->toArray(),
        );
    }

    public static function validDestinations(): array
    {
        return [
            'relative button URL' => [
                ['button_url' => '/services/example'],
                [
                    'schema_version' => 1,
                    'type' => 'custom_url',
                    'value' => '/services/example',
                    'open_in_new_tab' => false,
                ],
            ],
            'external URL alias' => [
                ['url' => 'https://example.com', 'open_in_new_tab' => true],
                [
                    'schema_version' => 1,
                    'type' => 'custom_url',
                    'value' => 'https://example.com',
                    'open_in_new_tab' => false,
                ],
            ],
            'canonical action' => [
                ['action' => [
                    'type' => 'service',
                    'reference_id' => 9,
                    'open_in_new_tab' => true,
                ]],
                [
                    'schema_version' => 1,
                    'type' => 'service',
                    'reference_id' => 9,
                    'open_in_new_tab' => true,
                ],
            ],
        ];
    }

    #[DataProvider('invalidDestinations')]
    public function test_it_fails_closed_for_empty_unsafe_and_malformed_destinations(
        array $input,
    ): void {
        $this->assertNull(app(FeatureGridLegacyActionAdapter::class)->adapt($input)->type);
    }

    public static function invalidDestinations(): array
    {
        return [
            'empty button URL' => [['button_url' => '']],
            'unsafe URL' => [['button_url' => 'javascript:alert(1)']],
            'malformed action' => [['action' => ['type' => 'page']]],
            'unsupported action' => [['action' => ['type' => 'popup']]],
            'empty item' => [[]],
        ];
    }
}
