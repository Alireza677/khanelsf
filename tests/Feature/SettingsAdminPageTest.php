<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_settings_page_redirects_to_site_settings_page(): void
    {
        $response = $this
            ->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings');

        $response->assertRedirect('/admin/manage-site-settings');
    }

    public function test_raw_setting_mutation_routes_are_not_registered(): void
    {
        $this
            ->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings/create')
            ->assertNotFound();

        $this
            ->get('/admin/settings/1/edit')
            ->assertNotFound();
    }
}
