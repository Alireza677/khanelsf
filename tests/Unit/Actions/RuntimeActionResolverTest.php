<?php

namespace Tests\Unit\Actions;

use App\CMS\Actions\Contracts\ActionTargetResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionStatus;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class RuntimeActionResolverTest extends TestCase
{
    public function test_it_dispatches_through_registry_and_propagates_context_and_new_tab(): void
    {
        $target = new RuntimePageTarget;
        $resolver = $this->runtime(['page' => $target]);

        $result = $resolver->resolve(
            new ActionDestination('page', 10, openInNewTab: true),
            new ResolutionContext(ResolutionMode::Preview),
        );

        $this->assertSame(ActionResolutionStatus::Resolved, $result->status);
        $this->assertSame(ResolutionMode::Preview, $target->mode);
        $this->assertSame('/pages/10', $result->url);
        $this->assertSame('_blank', $result->target);
        $this->assertSame('noopener noreferrer', $result->rel);
    }

    public function test_it_distinguishes_malformed_unregistered_and_invalid_references(): void
    {
        $resolver = $this->runtime(['page' => new RuntimePageTarget]);
        $context = new ResolutionContext;

        $malformed = $resolver->resolve(new ActionDestination('Page', 1), $context);
        $unregistered = $resolver->resolve(
            new ActionDestination('custom_url', value: '/about'),
            $context,
        );
        $missing = $resolver->resolve(new ActionDestination('page'), $context);
        $invalid = $resolver->resolve(new ActionDestination('page', -4), $context);
        $futureSchema = $resolver->resolve(
            new ActionDestination('page', 3, schemaVersion: 99),
            $context,
        );

        $this->assertSame('invalid_destination', $malformed->reason);
        $this->assertSame('unsupported_action_type', $unregistered->reason);
        $this->assertSame('missing_reference_id', $missing->reason);
        $this->assertSame('invalid_reference_id', $invalid->reason);
        $this->assertSame('unsupported_schema_version', $futureSchema->reason);
    }

    public function test_registry_can_dispatch_a_valid_non_core_non_reference_target(): void
    {
        $resolver = $this->runtime(
            ['plugin_target' => new RuntimePluginTarget],
            referenceBased: false,
        );

        $result = $resolver->resolve(
            new ActionDestination('plugin_target'),
            new ResolutionContext,
        );

        $this->assertSame(ActionResolutionStatus::Resolved, $result->status);
        $this->assertSame('plugin_target', $result->actionType);
        $this->assertSame('/plugin-target', $result->url);
    }

    public function test_unexpected_or_unsafe_target_output_fails_closed(): void
    {
        $failing = $this->runtime(['service' => new RuntimeFailingTarget]);
        $unsafe = $this->runtime(['page' => new RuntimeUnsafeTarget]);

        $exceptionResult = $failing->resolve(
            new ActionDestination('service', 9),
            new ResolutionContext,
        );
        $unsafeResult = $unsafe->resolve(
            new ActionDestination('page', 10),
            new ResolutionContext,
        );

        foreach ([$exceptionResult, $unsafeResult] as $result) {
            $this->assertSame(ActionResolutionStatus::Unavailable, $result->status);
            $this->assertSame('url_resolution_failed', $result->reason);
            $this->assertNull($result->url);
        }
    }

    public function test_batch_resolution_preserves_order_and_isolates_failures(): void
    {
        $resolver = $this->runtime([
            'page' => new RuntimePageTarget,
            'service' => new RuntimeFailingTarget,
        ]);

        $results = $resolver->resolveMany([
            new ActionDestination('page', 1),
            new ActionDestination('service', 2),
            'malformed',
            new ActionDestination('page', 3),
        ], new ResolutionContext);

        $this->assertCount(4, $results);
        $this->assertSame('/pages/1', $results[0]->url);
        $this->assertSame('url_resolution_failed', $results[1]->reason);
        $this->assertSame('invalid_destination', $results[2]->reason);
        $this->assertSame('/pages/3', $results[3]->url);
    }

    /**
     * @param  array<string, ActionTargetResolver>  $targets
     */
    private function runtime(
        array $targets,
        bool $referenceBased = true,
    ): RuntimeActionResolver {
        $registry = new ActionTargetRegistry;
        $container = new Container;

        foreach ($targets as $key => $target) {
            $registry->register(new ActionTargetDefinition(
                key: $key,
                resolver: $target::class,
                referenceBased: $referenceBased,
            ));
            $container->instance($target::class, $target);
        }

        return new RuntimeActionResolver($registry, $container, new NullLogger);
    }
}

final class RuntimePageTarget implements ActionTargetResolver
{
    public ?ResolutionMode $mode = null;

    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        $this->mode = $context->mode;

        return ResolvedAction::resolved(
            'page',
            '/pages/'.$destination->referenceId,
            $destination->openInNewTab,
        );
    }
}

final class RuntimePluginTarget implements ActionTargetResolver
{
    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        return ResolvedAction::resolved('plugin_target', '/plugin-target');
    }
}

final class RuntimeFailingTarget implements ActionTargetResolver
{
    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        throw new RuntimeException('SQLSTATE secret table data');
    }
}

final class RuntimeUnsafeTarget implements ActionTargetResolver
{
    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        return ResolvedAction::resolved('page', 'javascript:alert(1)');
    }
}
