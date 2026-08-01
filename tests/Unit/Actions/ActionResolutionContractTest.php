<?php

namespace Tests\Unit\Actions;

use App\CMS\Actions\Contracts\LegacyActionAdapter;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionStatus;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Normalizers\ActionDestinationNormalizer;
use PHPUnit\Framework\TestCase;

class ActionResolutionContractTest extends TestCase
{
    public function test_resolution_context_supports_explicit_modes_without_request_state(): void
    {
        $this->assertSame(
            ['mode' => 'production'],
            ResolutionContext::fromArray([])->toArray(),
        );
        $this->assertSame(
            ['mode' => 'preview'],
            ResolutionContext::fromArray(['mode' => 'preview'])->toArray(),
        );
        $this->assertSame(
            ResolutionMode::Production,
            ResolutionContext::fromArray(['mode' => 'unknown'])->mode,
        );
    }

    public function test_resolved_result_is_complete_and_applies_secure_new_tab_policy(): void
    {
        $result = ResolvedAction::resolved(
            actionType: 'page',
            url: '/about',
            openInNewTab: true,
            metadata: ['reference_id' => 12],
        );

        $this->assertTrue($result->shouldRender());
        $this->assertSame(ActionResolutionStatus::Resolved, $result->status);
        $this->assertSame('_blank', $result->target);
        $this->assertSame('noopener noreferrer', $result->rel);
        $this->assertSame(12, $result->metadata['reference_id']);
    }

    public function test_same_tab_resolved_result_does_not_emit_rel(): void
    {
        $result = ResolvedAction::resolved('custom_url', '/contact');

        $this->assertNull($result->target);
        $this->assertNull($result->rel);
    }

    public function test_failure_states_never_render_or_expose_raw_exception_messages(): void
    {
        $results = [
            ResolvedAction::unresolved('page', 'reference_not_found'),
            ResolvedAction::invalid(null, 'SQLSTATE[secret details]'),
            ResolvedAction::unavailable('service', 'target_unpublished'),
        ];

        $this->assertSame([
            ActionResolutionStatus::Unresolved,
            ActionResolutionStatus::Invalid,
            ActionResolutionStatus::Unavailable,
        ], array_map(fn (ResolvedAction $result) => $result->status, $results));

        foreach ($results as $result) {
            $this->assertFalse($result->shouldRender());
            $this->assertNull($result->url);
            $this->assertNull($result->target);
            $this->assertNull($result->rel);
        }

        $this->assertSame('resolution_failed', $results[1]->reason);
    }

    public function test_legacy_adapter_contract_converts_destination_only(): void
    {
        $adapter = new class implements LegacyActionAdapter
        {
            public function adapt(array $legacy): ActionDestination
            {
                if (array_key_exists('type', $legacy)) {
                    return (new ActionDestinationNormalizer)->normalize($legacy);
                }

                return (new ActionDestinationNormalizer)->normalize([
                    'type' => 'custom_url',
                    'value' => $legacy['button_url'] ?? null,
                ]);
            }
        };

        $destination = $adapter->adapt([
            'button_label' => 'Presentation remains outside',
            'button_url' => '/contact',
            'button_style' => 'primary',
        ]);

        $this->assertSame([
            'schema_version' => 1,
            'type' => 'custom_url',
            'value' => '/contact',
            'open_in_new_tab' => false,
        ], $destination->toArray());
        $this->assertArrayNotHasKey('button_label', $destination->toArray());
        $this->assertSame(
            $destination->toArray(),
            $adapter->adapt($destination->toArray())->toArray(),
        );
    }

    public function test_storage_round_trip_is_deterministic(): void
    {
        $normalizer = new ActionDestinationNormalizer;
        $first = $normalizer->normalize([
            'open_in_new_tab' => 'on',
            'reference_id' => '42',
            'type' => 'page',
            'unused' => 'discarded',
        ]);
        $second = $normalizer->normalize($first->toArray());

        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame(
            ['schema_version', 'type', 'reference_id', 'open_in_new_tab'],
            array_keys($second->toArray()),
        );
    }
}
