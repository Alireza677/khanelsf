<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\MediaResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ServiceResource;
use App\Filament\Support\QuickCreate;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class QuickCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_canonical_resource_urls_in_the_requested_order(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $items = app(QuickCreate::class)->items();

        $this->assertSame([
            ['برگه', PageResource::getUrl('create')],
            ['نوشته', PostResource::getUrl('create')],
            ['پروژه عمومی', ProjectResource::getUrl('create')],
            ['خدمت', ServiceResource::getUrl('create')],
            ['محصول', ProductResource::getUrl('create')],
            ['فرم', FormResource::getUrl('create')],
            ['آپلود رسانه', MediaResource::getUrl('upload')],
        ], collect($items)->map(fn (array $item): array => [$item['label'], $item['url']])->all());
    }

    public function test_module_visibility_hides_only_project_and_product_and_not_operational_services(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $settings = app(SettingsService::class);
        $settings->set('projects_enabled', false, 'projects', 'boolean');
        $settings->set('shop_enabled', false, 'shop', 'boolean');
        $settings->set('public_services_enabled', false, 'services', 'boolean');

        $labels = collect(app(QuickCreate::class)->items())->pluck('label');

        $this->assertNotContains('پروژه عمومی', $labels);
        $this->assertNotContains('محصول', $labels);
        $this->assertContains('خدمت', $labels);
    }

    public function test_create_authorization_filters_items_and_an_empty_menu_renders_nothing(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Gate::before(fn (): bool => false);

        $items = app(QuickCreate::class)->items();

        $this->assertSame([], $items);
        $this->assertSame('', trim(view('filament.quick-create', compact('items'))->render()));
    }

    public function test_topbar_contains_the_accessible_quick_create_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('aria-controls="admin-quick-create-menu"', false)
            ->assertSee('role="menu"', false)
            ->assertSee(PageResource::getUrl('create'), false)
            ->assertSee(MediaResource::getUrl('upload'), false);
    }
}
