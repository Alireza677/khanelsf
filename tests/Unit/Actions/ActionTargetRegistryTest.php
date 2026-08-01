<?php

namespace Tests\Unit\Actions;

use App\CMS\Actions\Contracts\ActionTargetResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Exceptions\DuplicateActionTarget;
use App\CMS\Actions\Exceptions\InvalidActionTargetDefinition;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class ActionTargetRegistryTest extends TestCase
{
    public function test_it_registers_and_exposes_immutable_definitions_in_registration_order(): void
    {
        $registry = new ActionTargetRegistry;
        $page = new ActionTargetDefinition('page', RegistryTargetResolver::class);
        $plugin = new ActionTargetDefinition(
            'plugin_target',
            RegistryTargetResolver::class,
            referenceBased: false,
        );

        $registry->register($page);
        $registry->register($plugin);
        $exposed = $registry->all();
        unset($exposed['page']);

        $this->assertTrue($registry->has('page'));
        $this->assertSame($page, $registry->get('page'));
        $this->assertSame(['page', 'plugin_target'], $registry->keys());
        $this->assertSame(['page', 'plugin_target'], array_keys($registry->all()));
        $this->assertTrue((new ReflectionClass(ActionTargetDefinition::class))->isReadOnly());
        $this->assertTrue($registry->has('page'));
        $this->assertFalse($plugin->referenceBased);
    }

    public function test_duplicate_registration_fails_fast_even_for_same_definition(): void
    {
        $registry = new ActionTargetRegistry;
        $definition = new ActionTargetDefinition('page', RegistryTargetResolver::class);
        $registry->register($definition);

        $this->expectException(DuplicateActionTarget::class);
        $this->expectExceptionMessage('Action target [page] is already registered.');

        $registry->register($definition);
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_or_non_canonical_keys_are_rejected(string $key): void
    {
        $this->expectException(InvalidActionTargetDefinition::class);

        new ActionTargetDefinition($key, RegistryTargetResolver::class);
    }

    public static function invalidKeys(): array
    {
        return [
            'empty' => [''],
            'whitespace' => [' page'],
            'uppercase' => ['Page'],
            'dash' => ['custom-url'],
            'dot' => ['custom.url'],
            'leading number' => ['1page'],
        ];
    }

    public function test_definition_rejects_a_class_that_is_not_a_target_resolver(): void
    {
        $this->expectException(InvalidActionTargetDefinition::class);
        $this->expectExceptionMessage('must use an ActionTargetResolver class');

        new ActionTargetDefinition('page', stdClass::class);
    }
}

final class RegistryTargetResolver implements ActionTargetResolver
{
    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        return ResolvedAction::resolved((string) $destination->type, '/registry-test');
    }
}
