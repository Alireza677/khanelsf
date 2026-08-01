<?php

namespace Tests\Feature;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Contracts\RegistersActionTargets;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\Actions\Resolution\AnchorActionResolver;
use App\CMS\Actions\Resolution\CustomUrlActionResolver;
use App\CMS\Actions\Resolution\EmailActionResolver;
use App\CMS\Actions\Resolution\FormActionResolver;
use App\CMS\Actions\Resolution\PageActionResolver;
use App\CMS\Actions\Resolution\PhoneActionResolver;
use App\CMS\Actions\Resolution\ProductActionResolver;
use App\CMS\Actions\Resolution\ProjectActionResolver;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\CMS\Actions\Resolution\ServiceActionResolver;
use App\CMS\Navigation\NavigationSourceRegistry;
use App\Providers\ActionServiceProvider;
use App\Providers\FormServiceProvider;
use App\Providers\PageServiceProvider;
use App\Providers\ProjectServiceProvider;
use App\Providers\ServiceServiceProvider;
use App\Providers\ShopServiceProvider;
use App\Services\SettingsService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Tests\TestCase;

class ActionTargetProviderRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_providers_register_each_target_exactly_once(): void
    {
        $registry = app(ActionTargetRegistry::class);

        $this->assertSame(
            ['custom_url', 'anchor', 'email', 'phone', 'page', 'project', 'product', 'service', 'form'],
            $registry->keys(),
        );
        $this->assertCount(9, $registry->all());
        $this->assertSame(CustomUrlActionResolver::class, $registry->get('custom_url')?->resolver);
        $this->assertSame(AnchorActionResolver::class, $registry->get('anchor')?->resolver);
        $this->assertSame(EmailActionResolver::class, $registry->get('email')?->resolver);
        $this->assertSame(PhoneActionResolver::class, $registry->get('phone')?->resolver);
        $this->assertSame(PageActionResolver::class, $registry->get('page')?->resolver);
        $this->assertSame(ProjectActionResolver::class, $registry->get('project')?->resolver);
        $this->assertSame(ProductActionResolver::class, $registry->get('product')?->resolver);
        $this->assertSame(ServiceActionResolver::class, $registry->get('service')?->resolver);
        $this->assertSame(FormActionResolver::class, $registry->get('form')?->resolver);
    }

    #[DataProvider('providerOwners')]
    public function test_each_owner_provider_uses_the_registration_contract(string $provider): void
    {
        $this->assertTrue(is_subclass_of($provider, RegistersActionTargets::class));
    }

    public static function providerOwners(): array
    {
        return [
            'core values' => [ActionServiceProvider::class],
            'page' => [PageServiceProvider::class],
            'project' => [ProjectServiceProvider::class],
            'product' => [ShopServiceProvider::class],
            'service' => [ServiceServiceProvider::class],
            'form' => [FormServiceProvider::class],
        ];
    }

    public function test_service_action_and_navigation_registrations_remain_independent(): void
    {
        $this->assertTrue(app(ActionTargetRegistry::class)->has('service'));
        $this->assertTrue(app(NavigationSourceRegistry::class)->exists('services.archive'));
    }

    public function test_disabled_modules_stay_registered_and_resolve_as_module_disabled(): void
    {
        app(SettingsService::class)->set('projects_enabled', false, 'projects', 'boolean');
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        $registry = app(ActionTargetRegistry::class);
        $resolver = app(ActionResolver::class);

        $this->assertTrue($registry->has('project'));
        $this->assertTrue($registry->has('product'));
        $this->assertSame('module_disabled', $resolver->resolve(
            new ActionDestination('project', 1),
            new ResolutionContext,
        )->reason);
        $this->assertSame('module_disabled', $resolver->resolve(
            new ActionDestination('product', 1),
            new ResolutionContext,
        )->reason);
    }

    public function test_runtime_constructor_depends_only_on_registry_container_and_logger(): void
    {
        $constructor = (new ReflectionClass(RuntimeActionResolver::class))->getConstructor();
        $dependencies = collect($constructor?->getParameters() ?? [])
            ->map(fn ($parameter): ?string => $parameter->getType()?->getName())
            ->all();

        $this->assertSame([
            ActionTargetRegistry::class,
            Container::class,
            LoggerInterface::class,
        ], $dependencies);
        $this->assertNotContains(PageActionResolver::class, $dependencies);
        $this->assertNotContains(ProjectActionResolver::class, $dependencies);
        $this->assertNotContains(ProductActionResolver::class, $dependencies);
        $this->assertNotContains(ServiceActionResolver::class, $dependencies);
    }
}
