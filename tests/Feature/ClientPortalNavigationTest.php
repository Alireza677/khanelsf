<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectActivityResource;
use App\Filament\Resources\ClientProjectCycleResource;
use App\Filament\Resources\ClientProjectResource;
use App\Filament\Resources\ClientProjectResource\Pages\ListClientProjects;
use App\Filament\Resources\ClientProjectResource\Pages\ViewClientProject;
use App\Filament\Resources\ClientProjectWorkItemResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Models\ClientProject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_has_one_group_with_only_the_four_primary_entries(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $groups = collect(Filament::getNavigation())->filter(fn ($group) => str_starts_with($group->getLabel(), 'پرتال مشتری'));

        $this->assertCount(1, $groups);
        $this->assertSame('پرتال مشتریان', $groups->first()->getLabel());
        $this->assertSame(['پروژه‌های مشتریان', 'کارهای اجرایی', 'مشتریان پرتال', 'فاکتورها'], collect($groups->first()->getItems())->map->getLabel()->values()->all());
        $this->assertSame('پرتال مشتریان', ClientProjectActivityResource::getNavigationGroup());
        $this->assertSame('پرتال مشتریان', ClientProjectCycleResource::getNavigationGroup());
        $this->assertFalse(ClientProjectActivityResource::shouldRegisterNavigation());
        $this->assertFalse(ClientProjectCycleResource::shouldRegisterNavigation());
    }

    public function test_existing_resource_indexes_and_secondary_links_remain_accessible(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach ([
            ClientProjectResource::class => '/admin/client-portal/projects',
            ClientProjectWorkItemResource::class => '/admin/client-project-work-items',
            CustomerResource::class => '/admin/client-portal/customers',
            InvoiceResource::class => '/admin/client-portal/invoices',
            ClientProjectActivityResource::class => '/admin/client-portal/activities',
            ClientProjectCycleResource::class => '/admin/client-project-cycles',
        ] as $resource => $path) {
            $url = $resource::getUrl('index');
            $this->assertSame($path, parse_url($url, PHP_URL_PATH));
            $this->get($url)->assertOk();
        }

        $page = Livewire::test(ListClientProjects::class)
            ->assertActionVisible('allActivities')->assertActionVisible('allCycles');
        $this->assertSame(ClientProjectActivityResource::getUrl('index'), $page->instance()->getAction('allActivities')->getUrl());
        $this->assertSame(ClientProjectCycleResource::getUrl('index'), $page->instance()->getAction('allCycles')->getUrl());
    }

    public function test_project_registration_requires_update_permission_and_admin_access(): void
    {
        $project = ClientProject::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        Gate::before(fn ($user, $ability) => $ability === 'update' ? false : null);
        Livewire::test(ViewClientProject::class, ['record' => $project->getRouteKey()])
            ->assertOk()->assertActionHidden('quickCreateActivity');

        $this->actingAs(User::factory()->client()->create());
        $this->get(ClientProjectResource::getUrl('view', ['record' => $project]))->assertForbidden();
        $this->get(ClientProjectActivityResource::getUrl('index'))->assertForbidden();
        $this->get(ClientProjectCycleResource::getUrl('index'))->assertForbidden();
    }
}
