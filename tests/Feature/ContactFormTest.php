<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_contact_message_stores_successfully(): void
    {
        Page::factory()->published()->create(['slug' => 'contact']);

        $this->from(route('contact.create'))
            ->post(route('contact.store'), [
                'name' => 'Jane Client',
                'email' => 'jane@example.com',
                'phone' => '+1 555 000 0000',
                'subject' => 'Project request',
                'message' => 'I would like to discuss a new website project.',
            ])
            ->assertRedirect(route('contact.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'jane@example.com',
            'status' => 'new',
        ]);

        $this->assertSame(1, ContactMessage::query()->count());
    }

    public function test_invalid_contact_message_fails_validation(): void
    {
        $this->from(route('contact.create'))
            ->post(route('contact.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'message' => 'short',
            ])
            ->assertRedirect(route('contact.create'))
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }
}
