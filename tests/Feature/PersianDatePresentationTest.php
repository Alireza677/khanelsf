<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersianDatePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_shows_jalali_text_but_keeps_machine_date_iso(): void
    {
        $post = Post::factory()->published()->create([
            'slug' => 'jalali-date-test',
            'published_at' => '2026-08-19 10:30:00',
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('۲۸ مرداد ۱۴۰۵')
            ->assertSee('datetime="2026-08-19T10:30:00+03:30"', false)
            ->assertDontSee('Aug 19, 2026');
    }
}
