<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function reserve(Slot $slot): Reservation
    {
        $this->post('/reservasi', ['slot_id' => $slot->id, 'name' => 'Rafa', 'plate_number' => 'b 1234 xyz'])
            ->assertRedirect();

        return Reservation::latest('id')->firstOrFail();
    }

    public function test_visitor_reserves_an_available_slot_and_it_is_held(): void
    {
        $floor = Floor::factory()->create();
        $slot = Slot::factory()->available()->for($floor)->create(['code' => 'P1-A001']);
        Slot::factory()->available()->for($floor)->create();

        $this->get("/reservasi/baru?slot={$slot->id}")->assertOk()->assertSee('Rp10.000');

        $reservation = $this->reserve($slot);

        $this->assertSame(Reservation::PENDING, $reservation->status);
        $this->assertSame('B 1234 XYZ', $reservation->plate_number);
        $this->assertSame(10000, $reservation->fee);
        $this->get("/reservasi/{$reservation->code}")->assertOk()->assertSee('Bayar Rp10.000');

        $this->getJson("/api/floors/{$floor->id}/slots")->assertJsonFragment(['code' => 'P1-A001', 'status' => 'reserved']);
        $this->getJson('/api/floors')
            ->assertJsonPath('data.0.available', 1)
            ->assertJsonPath('data.0.reserved', 1)
            ->assertJsonPath('data.0.unknown', 0);

        $this->get('/reservasi')->assertSee('P1-A001');
    }

    public function test_a_held_or_occupied_slot_cannot_be_reserved(): void
    {
        $slot = Slot::factory()->available()->create();
        $occupied = Slot::factory()->occupied()->create();
        $this->reserve($slot);

        $this->post('/reservasi', ['slot_id' => $slot->id, 'name' => 'Lain', 'plate_number' => 'D 1 A'])
            ->assertSessionHas('error');
        $this->postJson('/api/reservations', ['slot_id' => $occupied->id, 'name' => 'Lain', 'plate_number' => 'D 1 A'])
            ->assertStatus(409);

        $this->assertSame(1, Reservation::count());
    }

    public function test_paying_shows_the_qr_valid_for_two_hours(): void
    {
        $reservation = $this->reserve(Slot::factory()->available()->create());

        $this->post("/reservasi/{$reservation->code}/bayar")->assertRedirect("/reservasi/{$reservation->code}");

        $reservation->refresh();
        $this->assertSame(Reservation::PAID, $reservation->status);
        $this->assertEqualsWithDelta(120, $reservation->paid_at->diffInMinutes($reservation->expires_at), 0.1);
        $this->assertTrue($reservation->slot->reserved_until->eq($reservation->expires_at));

        $this->get("/reservasi/{$reservation->code}")->assertSee('qr.svg')->assertSee('Menunggu masuk gerbang');
        $this->get("/reservasi/{$reservation->code}/qr.svg")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_unpaid_reservation_lapses_and_releases_the_slot(): void
    {
        $slot = Slot::factory()->available()->create();
        $reservation = $this->reserve($slot);

        $this->travel(16)->minutes();
        // Sensor tetap melapor kosong.
        $slot->update(['last_seen_at' => now()]);

        $this->post("/reservasi/{$reservation->code}/bayar")->assertSessionHas('error');
        $this->artisan('parkir:expire-reservations')->assertSuccessful();

        $this->assertSame(Reservation::EXPIRED, $reservation->fresh()->status);
        $this->assertNull($slot->fresh()->reserved_until);
        $this->assertSame(Slot::AVAILABLE, $slot->fresh()->status());
    }

    public function test_paid_reservation_expires_after_two_hours_without_entering(): void
    {
        $this->actingAs(User::factory()->create());
        $reservation = $this->reserve(Slot::factory()->available()->create());
        $this->post("/reservasi/{$reservation->code}/bayar");

        $this->travel(121)->minutes();

        $this->assertSame('Hangus', $reservation->fresh()->statusLabel());
        $this->post('/admin/gerbang', ['code' => $reservation->code, 'arah' => 'masuk'])
            ->assertSessionHas('error', "Reservasi {$reservation->code} sudah hangus.");
        $this->assertNull($reservation->fresh()->checked_in_at);
    }

    public function test_gate_scan_starts_and_stops_the_parking_clock(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $reservation = $this->reserve(Slot::factory()->available()->create());
        $this->post("/reservasi/{$reservation->code}/bayar");

        $this->get('/admin/gerbang')->assertRedirect('/admin/login');

        $this->actingAs($user);
        $this->travel(90)->minutes();
        $this->get('/admin/gerbang')->assertOk()->assertSee($reservation->code);

        // Scanner USB mengetik kode; huruf kecil dan spasi tetap diterima.
        $this->post('/admin/gerbang', ['code' => ' '.strtolower($reservation->code), 'arah' => 'masuk'])
            ->assertRedirect('/admin/gerbang?arah=masuk')
            ->assertSessionHas('status');
        $this->assertSame(Reservation::PARKED, $reservation->fresh()->status);
        $this->assertTrue($reservation->fresh()->checked_in_at->eq(now()));

        // Scan dua kali di gerbang masuk tidak mengulang hitungan.
        $this->post('/admin/gerbang', ['code' => $reservation->code, 'arah' => 'masuk'])->assertSessionHas('error');

        $this->travel(150)->minutes();
        $this->post('/admin/gerbang', ['code' => $reservation->code, 'arah' => 'keluar'])
            ->assertSessionHas('status', fn ($message) => str_contains($message, '2 jam 30 menit (3 jam dihitung)'));

        $reservation->refresh();
        $this->assertSame(Reservation::DONE, $reservation->status);
        $this->assertSame(150, $reservation->parkedMinutes());
        $this->assertNull($reservation->slot->reserved_until);
    }

    public function test_api_flow_for_the_mobile_app(): void
    {
        $slot = Slot::factory()->available()->create();

        $code = $this->postJson('/api/reservations', ['slot_id' => $slot->id, 'name' => 'Rafa', 'plate_number' => 'B1234XYZ'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.fee', 10000)
            ->json('data.code');

        $this->postJson("/api/reservations/{$code}/pay")->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/reservations/{$code}/pay")->assertStatus(409);
        $this->getJson("/api/reservations/{$code}")
            ->assertOk()
            ->assertJsonPath('data.slot.id', $slot->id)
            ->assertJsonPath('data.qr_svg_url', url("/reservasi/{$code}/qr.svg"));
        $this->getJson('/api/reservations/RSVTIDAKADA')->assertNotFound();
    }

    public function test_a_car_detected_in_a_reserved_slot_shows_as_occupied(): void
    {
        $slot = Slot::factory()->available()->create();
        $this->reserve($slot);

        $slot->refresh()->update(['is_occupied' => true, 'last_seen_at' => now()]);

        $this->assertSame(Slot::OCCUPIED, $slot->status());
        $this->getJson('/api/floors')->assertJsonPath('data.0.occupied', 1)->assertJsonPath('data.0.reserved', 0);
    }
}
