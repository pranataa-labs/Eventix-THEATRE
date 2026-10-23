<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendLogicAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;
    private Event $event;
    private TicketType $ticketType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->create();

        $category = Category::create([
            'name' => 'Theatre & Arts',
            'slug' => 'theatre-arts',
        ]);

        $this->event = Event::create([
            'user_id' => $this->organizer->id,
            'category_id' => $category->id,
            'name' => 'Romeo & Juliet Live Musical',
            'description' => 'A modern theatrical take on the romantic tragedy.',
            'event_date' => now()->addDays(7),
            'location' => 'Metropolitan Opera House',
            'capacity' => 150,
        ]);

        $this->ticketType = TicketType::create([
            'event_id' => $this->event->id,
            'name' => 'Gold Seating',
            'price' => 200000,
            'quota' => 10,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 1. Admin Security & Route Obfuscation Tests                                */
    /* -------------------------------------------------------------------------- */

    public function test_legacy_admin_path_returns_404_not_found(): void
    {
        $this->get('/admin')->assertStatus(404);
        $this->get('/admin/login')->assertStatus(404);
    }

    public function test_obfuscated_admin_panel_is_accessible(): void
    {
        $adminPrefix = env('ADMIN_PREFIX', 'eventix-portal-mgmt');
        $response = $this->get('/' . $adminPrefix . '/login');
        $response->assertStatus(200);
    }

    public function test_standard_user_cannot_access_filament_panel(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->canAccessPanel(new \Filament\Panel()));
    }

    /* -------------------------------------------------------------------------- */
    /* 2. Ticket Registration & Quota Business Logic Tests                         */
    /* -------------------------------------------------------------------------- */

    public function test_ticket_registration_requires_mandatory_fields(): void
    {
        $response = $this->post(route('event.register', $this->event->id), []);
        $response->assertSessionHasErrors(['name', 'email', 'ticket_type_id', 'quantity']);
    }

    public function test_ticket_registration_rejects_quantity_exceeding_quota(): void
    {
        $response = $this->post(route('event.register', $this->event->id), [
            'name' => 'Jane Attendee',
            'email' => 'jane@example.com',
            'ticket_type_id' => $this->ticketType->id,
            'quantity' => 10, // quota is 10, let's pretend 5 sold
        ]);

        // Consume 5 tickets first
        for ($i = 0; $i < 5; $i++) {
            Registration::create([
                'user_id' => $this->organizer->id,
                'event_id' => $this->event->id,
                'ticket_type_id' => $this->ticketType->id,
                'ticket_code' => 'TIX-QUOTA-' . $i,
                'status' => 'confirmed',
            ]);
        }

        // Now remaining quota is 5. Requesting 8 should fail:
        $exceedResponse = $this->from(route('event.show', $this->event->id))
            ->post(route('event.register', $this->event->id), [
                'name' => 'Jane Attendee',
                'email' => 'jane@example.com',
                'ticket_type_id' => $this->ticketType->id,
                'quantity' => 8,
            ]);

        $exceedResponse->assertRedirect(route('event.show', $this->event->id));
        $exceedResponse->assertSessionHas('error', 'Maaf, sisa tiket jenis ini tidak mencukupi untuk pesanan Anda.');
    }

    public function test_successful_registration_creates_unique_tickets_and_logs_in_user(): void
    {
        // Mock Midtrans Snap token generation
        // To avoid real network call to Midtrans in tests:
        putenv('MIDTRANS_SERVER_KEY=SB-Mid-server-TEST-KEY');

        $response = $this->post(route('event.register', $this->event->id), [
            'name' => 'Sarah Connor',
            'email' => 'sarah@resistance.com',
            'ticket_type_id' => $this->ticketType->id,
            'quantity' => 2,
        ]);

        // Verify registrations created for 2 tickets
        $registrations = Registration::where('event_id', $this->event->id)->get();
        $this->assertCount(2, $registrations);

        foreach ($registrations as $reg) {
            $this->assertStringStartsWith('TIX-', $reg->ticket_code);
            $this->assertStringStartsWith('ORD-', $reg->order_number);
            $this->assertEquals('pending', $reg->status);
        }

        // User was automatically created and logged in
        $this->assertAuthenticated();
        $this->assertEquals('sarah@resistance.com', auth()->user()->email);
    }

    /* -------------------------------------------------------------------------- */
    /* 3. Ticket Scanner & Check-in Logic Tests                                    */
    /* -------------------------------------------------------------------------- */

    public function test_ticket_scanner_successfully_checks_in_attendee(): void
    {
        $attendee = User::factory()->create(['name' => 'John Doe']);

        $registration = Registration::create([
            'user_id' => $attendee->id,
            'event_id' => $this->event->id,
            'ticket_type_id' => $this->ticketType->id,
            'ticket_code' => 'TIX-SCAN-VALID',
            'status' => 'confirmed',
            'is_checked_in' => false,
        ]);

        $response = $this->postJson(route('scanner.process'), [
            'ticket_code' => 'TIX-SCAN-VALID',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($registration->fresh()->is_checked_in);
        $this->assertNotNull($registration->fresh()->checked_in_at);
    }

    public function test_ticket_scanner_rejects_already_checked_in_ticket(): void
    {
        $attendee = User::factory()->create();

        Registration::create([
            'user_id' => $attendee->id,
            'event_id' => $this->event->id,
            'ticket_type_id' => $this->ticketType->id,
            'ticket_code' => 'TIX-ALREADY-USED',
            'status' => 'confirmed',
            'is_checked_in' => true,
            'checked_in_at' => now()->subMinutes(10),
        ]);

        $response = $this->postJson(route('scanner.process'), [
            'ticket_code' => 'TIX-ALREADY-USED',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_ticket_scanner_returns_404_on_invalid_ticket(): void
    {
        $response = $this->postJson(route('scanner.process'), [
            'ticket_code' => 'TIX-DOES-NOT-EXIST',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Ticket not found!',
            ]);
    }

    /* -------------------------------------------------------------------------- */
    /* 4. User Tickets Dashboard Protected Route Tests                            */
    /* -------------------------------------------------------------------------- */

    public function test_unauthenticated_user_cannot_access_my_tickets(): void
    {
        $response = $this->get(route('user.tickets'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_my_tickets(): void
    {
        $user = User::factory()->create();

        Registration::create([
            'user_id' => $user->id,
            'event_id' => $this->event->id,
            'ticket_type_id' => $this->ticketType->id,
            'order_number' => 'ORD-MY-TIX-01',
            'ticket_code' => 'TIX-MY-01',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->get(route('user.tickets'));
        $response->assertStatus(200);
        $response->assertSee('ORD-MY-TIX-01');
    }
}
