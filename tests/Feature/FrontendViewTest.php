<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendViewTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;
    private Event $event;
    private TicketType $ticketType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->create([
            'name' => 'Royal Shakespeare Company',
        ]);

        $category = Category::create([
            'name' => 'Classical Theatre',
            'slug' => 'classical-theatre',
        ]);

        $this->event = Event::create([
            'user_id' => $this->organizer->id,
            'category_id' => $category->id,
            'name' => 'Macbeth: Tragedy in Five Acts',
            'description' => 'A brave Scottish general receives a prophecy from a trio of witches.',
            'event_date' => now()->addDays(14),
            'location' => 'Globe Theatre Amphitheatre',
            'capacity' => 200,
        ]);

        $this->ticketType = TicketType::create([
            'event_id' => $this->event->id,
            'name' => 'Premiere Stalls',
            'price' => 250000,
            'quota' => 25,
        ]);
    }

    /**
     * Test Home Page / Welcome view.
     */
    public function test_home_page_renders_hero_and_events(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Eventix THEATRE');
        $response->assertSee('Sorotan Utama');
        $response->assertSee('Macbeth: Tragedy in Five Acts');
    }

    /**
     * Test Event Detail page view.
     */
    public function test_event_detail_page_renders_details_and_ticket_types(): void
    {
        $response = $this->get("/event/{$this->event->id}");

        $response->assertStatus(200);
        $response->assertSee('Macbeth: Tragedy in Five Acts');
        $response->assertSee('Globe Theatre Amphitheatre');
        $response->assertSee('Deskripsi Acara');
        $response->assertSee('Royal Shakespeare Company');
        $response->assertSee('Premiere Stalls');
        $response->assertSee('250.000');
    }

    /**
     * Test Login view renders proper form controls.
     */
    public function test_login_view_renders_properly(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Email');
        $response->assertSee('Sandi');
        $response->assertSee('MASUK');
    }

    /**
     * Test Register view renders proper form controls.
     */
    public function test_register_view_renders_properly(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Nama Lengkap');
        $response->assertSee('Email');
        $response->assertSee('Sandi');
        $response->assertSee('DAFTAR');
    }

    /**
     * Test Forgot Password view renders.
     */
    public function test_forgot_password_view_renders(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertSee('Kirim OTP');
    }

    /**
     * Test Scanner view renders ticket scanner interface.
     */
    public function test_scanner_view_renders(): void
    {
        $response = $this->get('/scanner');

        $response->assertStatus(200);
        $response->assertSee('Ticket Scanner');
    }
}
