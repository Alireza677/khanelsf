<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_preview(): void
    {
        $page = Page::factory()->draft()->create();

        $this->get(route('admin.preview.pages.show', $page))
            ->assertNotFound();
    }

    public function test_authenticated_non_admin_cannot_access_preview(): void
    {
        $page = Page::factory()->draft()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.preview.pages.show', $page))
            ->assertForbidden();
    }

    public function test_authenticated_admin_can_preview_draft_page_post_and_project(): void
    {
        $admin = User::factory()->admin()->create();
        $page = Page::factory()->draft()->create(['title' => 'Draft Preview Page']);
        $post = Post::factory()->draft()->create(['title' => 'Draft Preview Post']);
        $project = Project::factory()->draft()->create(['title' => 'Draft Preview Project']);

        $this->actingAs($admin)
            ->get(route('admin.preview.pages.show', $page))
            ->assertOk()
            ->assertSee('Draft Preview Page')
            ->assertSee('content="noindex, nofollow"', false);

        $this->actingAs($admin)
            ->get(route('admin.preview.posts.show', $post))
            ->assertOk()
            ->assertSee('Draft Preview Post')
            ->assertSee('content="noindex, nofollow"', false);

        $this->actingAs($admin)
            ->get(route('admin.preview.projects.show', $project))
            ->assertOk()
            ->assertSee('Draft Preview Project')
            ->assertSee('content="noindex, nofollow"', false);
    }
}
