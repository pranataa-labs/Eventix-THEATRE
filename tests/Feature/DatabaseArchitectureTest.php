<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test core system tables exist in schema.
     */
    public function test_all_expected_database_tables_exist(): void
    {
        $tables = [
            'users',
            'events',
            'ticket_types',
            'registrations',
            'categories',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table '{$table}' does not exist in schema.");
        }
    }

    /**
     * Test events table schema and columns.
     */
    public function test_events_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'user_id', 'category_id', 'name', 'description',
            'event_date', 'location', 'capacity', 'created_at', 'updated_at'
        ];

        $this->assertTrue(Schema::hasColumns('events', $columns));
    }

    /**
     * Test ticket_types table schema and columns.
     */
    public function test_ticket_types_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'event_id', 'name', 'price', 'quota', 'created_at', 'updated_at'
        ];

        $this->assertTrue(Schema::hasColumns('ticket_types', $columns));
    }

    /**
     * Test registrations table schema and columns.
     */
    public function test_registrations_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'user_id', 'event_id', 'ticket_type_id', 'order_number',
            'ticket_code', 'status', 'is_checked_in', 'checked_in_at',
            'payment_method', 'payment_status', 'payment_proof', 'quantity',
            'created_at', 'updated_at'
        ];

        $this->assertTrue(Schema::hasColumns('registrations', $columns));
    }

    /**
     * Test event deletion cascades to ticket_types and registrations.
     */
    public function test_event_deletion_cascades_to_tickets_and_registrations(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();

        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Hamlet Live Theatre',
            'description' => 'Classical Shakespeare drama',
            'event_date' => now()->addDays(10),
            'location' => 'Grand Theatre Hall',
            'capacity' => 100,
        ]);

        $ticket = TicketType::create([
            'event_id' => $event->id,
            'name' => 'VIP Balcony',
            'price' => 350000,
            'quota' => 20,
        ]);

        $registration = Registration::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'order_number' => 'ORD-TEST-001',
            'ticket_code' => 'TIX-TEST-001',
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseHas('ticket_types', ['id' => $ticket->id]);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);

        // Delete event
        $event->delete();

        // Check cascade
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('ticket_types', ['id' => $ticket->id]);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
    }

    /**
     * Test unique constraint on ticket_code.
     */
    public function test_unique_ticket_code_constraint(): void
    {
        $organizer = User::factory()->create();
        $attendee = User::factory()->create();

        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Musical Night',
            'event_date' => now()->addDays(5),
            'location' => 'Auditorium',
            'capacity' => 50,
        ]);

        $ticket = TicketType::create([
            'event_id' => $event->id,
            'name' => 'General',
            'price' => 100000,
            'quota' => 50,
        ]);

        Registration::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'order_number' => 'ORD-1',
            'ticket_code' => 'TIX-UNIQUE-123',
            'status' => 'confirmed',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Registration::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'order_number' => 'ORD-2',
            'ticket_code' => 'TIX-UNIQUE-123', // duplicate ticket code
            'status' => 'confirmed',
        ]);
    }

    /**
     * Test financial decimal precision on ticket prices.
     */
    public function test_financial_decimal_precision(): void
    {
        $organizer = User::factory()->create();

        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Orchestra Gala',
            'event_date' => now()->addDays(15),
            'location' => 'Concert Hall',
            'capacity' => 200,
        ]);

        $ticket = TicketType::create([
            'event_id' => $event->id,
            'name' => 'VVIP Patron',
            'price' => 1250000.50,
            'quota' => 10,
        ]);

        $this->assertEquals(1250000.50, (float) $ticket->fresh()->price);
    }
}
