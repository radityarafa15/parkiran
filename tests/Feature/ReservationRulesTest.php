<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\SlotReport;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReservationService
    {
        return app(ReservationService::class);
    }

    private function paid(?Slot $slot = null, string $plate = 'B 1234 XYZ'): Reservation
    {
        return $this->service()->pay($this->service()->reserve($slot ?? Slot::factory()->available()->create(), 'Rafa', $plate));
    }

    public function test_one_plate_can_hold_only_one_active_reservation(): void
    {
        $this->post('/reservasi', ['slot_id' => Slot::factory()->available()->create()->id, 'name' => 'Rafa', 'plate_number' => 'B 1234 XYZ']);
        $first = Reservation::sole();
        $other = Slot::factory()->available()->create();

        // Ejaan plat berbeda tetap dianggap plat yang sama. Browser yang membuatnya diarahkan ke tiketnya.
        $this->post('/reservasi', ['slot_id' => $other->id, 'name' => 'Rafa', 'plate_number' => 'b 1234xyz'])
            ->assertRedirect("/reservasi/{$first->code}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah punya 1 reservasi aktif'));

        // Orang lain yang mengetik plat yang sama tidak mendapat kode atau QR-nya.
        $this->flushSession();
        $this->post('/reservasi', ['slot_id' => $other->id, 'name' => 'Iseng', 'plate_number' => 'B 1234 XYZ'])
            ->assertRedirect(route('floors.show', $other->floor))
            ->assertSessionHas('error', fn ($m) => ! str_contains($m, $first->code));
        $this->postJson('/api/reservations', ['slot_id' => $other->id, 'name' => 'Rafa', 'plate_number' => 'B 1234 XYZ'])
            ->assertStatus(409)
            ->assertJsonMissingPath('active_code')
            ->assertDontSee($first->code);

        $this->assertSame(1, Reservation::count());
        $this->assertSame(Slot::AVAILABLE, $other->fresh()->status());

        // Plat lain tetap boleh, dan setelah reservasi pertama hangus plat yang sama boleh lagi.
        $this->postJson('/api/reservations', ['slot_id' => $other->id, 'name' => 'Ani', 'plate_number' => 'D 9 AB'])->assertCreated();
        $this->travel(16)->minutes();
        $this->postJson('/api/reservations', ['slot_id' => Slot::factory()->available()->create()->id, 'name' => 'Rafa', 'plate_number' => 'B 1234 XYZ'])->assertCreated();
    }

    public function test_cancelling_within_five_minutes_refunds_the_fee(): void
    {
        $reservation = $this->paid();
        $this->travel(3)->minutes();

        $this->get("/reservasi/{$reservation->code}")->assertSee('dikembalikan penuh');
        $this->post("/reservasi/{$reservation->code}/batal")
            ->assertRedirect("/reservasi/{$reservation->code}")
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Rp10.000 dikembalikan'));

        $reservation->refresh();
        $this->assertSame(Reservation::CANCELLED, $reservation->status);
        $this->assertSame(10000, $reservation->refund_amount);
        $this->assertNull($reservation->slot->reserved_until);
        $this->assertSame(Slot::AVAILABLE, $reservation->slot->status());
        $this->get("/reservasi/{$reservation->code}")->assertSee('Dibatalkan · dikembalikan')->assertSee('Rp10.000');

        // Setelah batal, plat yang sama boleh memesan lagi.
        $this->service()->reserve(Slot::factory()->available()->create(), 'Rafa', 'B 1234 XYZ');
    }

    public function test_cancelling_after_five_minutes_keeps_the_fee(): void
    {
        $reservation = $this->paid();
        $this->travel(6)->minutes();

        $this->postJson("/api/reservations/{$reservation->code}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Reservation::CANCELLED)
            ->assertJsonPath('data.refund_amount', 0)
            ->assertJsonPath('data.can_cancel', false);

        // Belum dibayar: batal tanpa refund karena memang belum ada uang masuk.
        $pending = $this->service()->reserve(Slot::factory()->available()->create(), 'Ani', 'D 9 AB');
        $this->postJson("/api/reservations/{$pending->code}/cancel")->assertOk()->assertJsonPath('data.refund_amount', 0);
    }

    public function test_a_reservation_that_entered_the_gate_cannot_be_cancelled(): void
    {
        $reservation = $this->service()->checkIn($this->paid());

        $this->postJson("/api/reservations/{$reservation->code}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('message', "Reservasi {$reservation->code} sudah masuk gerbang, jadi tidak bisa dibatalkan.");
    }

    public function test_report_revenue_subtracts_refunds(): void
    {
        $this->service()->cancel($this->paid());
        $this->paid(plate: 'D 9 AB');

        $this->actingAs(User::factory()->create())
            ->get('/admin/laporan?hari=1')
            ->assertSee('Pendapatan biaya admin</span><span class="big">Rp10.000</span>', false)
            ->assertSee('dikurangi refund Rp10.000')
            ->assertSee('1 dibatalkan pengunjung');
    }

    public function test_second_entry_scan_is_rejected_with_the_first_entry_time(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setTime(13, 10)->utc());
        $reservation = $this->service()->checkIn($this->paid());
        $gate = User::factory()->gateOfficer()->create();

        $this->actingAs($gate)
            ->post('/admin/gerbang', ['code' => $reservation->code, 'arah' => 'masuk'])
            ->assertSessionHas('error', "QR sudah dipakai: {$reservation->code} (B 1234 XYZ) masuk pukul 13:10 WIB hari ini. Jangan buka palang.");

        $this->assertSame(Reservation::PARKED, $reservation->fresh()->status);
    }

    public function test_reserved_slot_stays_held_when_its_sensor_goes_offline(): void
    {
        $reservation = $this->paid();
        $this->travel(30)->minutes(); // sensor tidak melapor lebih dari 5 menit

        $this->assertSame(Slot::RESERVED, $reservation->slot->fresh()->status());
        $this->postJson('/api/reservations', ['slot_id' => $reservation->slot_id, 'name' => 'Lain', 'plate_number' => 'D 1 A'])->assertStatus(409);
    }

    public function test_reporting_an_occupied_slot_holds_the_nearest_free_slot(): void
    {
        $floor = Floor::factory()->create();
        $other = Floor::factory()->create(['level' => 2]);
        Slot::factory()->available()->for($other)->create(['zone' => 'A']);
        $farZone = Slot::factory()->available()->for($floor)->create(['code' => 'P1-B001', 'zone' => 'B']);
        $mine = Slot::factory()->available()->for($floor)->create(['code' => 'P1-A014', 'zone' => 'A']);
        Slot::factory()->occupied()->for($floor)->create(['code' => 'P1-A015', 'zone' => 'A']);
        $near = Slot::factory()->available()->for($floor)->create(['code' => 'P1-A017', 'zone' => 'A']);

        $reservation = $this->paid($mine);
        $expires = $reservation->expires_at;

        $this->post("/reservasi/{$reservation->code}/lapor", ['type' => SlotReport::OCCUPIED, 'note' => 'Mobil putih B 9876 XY'])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'P1-A017'));

        $reservation->refresh();
        $this->assertSame($near->id, $reservation->slot_id);
        $this->assertTrue($near->fresh()->reserved_until->eq($expires));
        $this->assertNull($mine->fresh()->reserved_until);
        $this->assertSame(Slot::AVAILABLE, $farZone->fresh()->status());

        $report = SlotReport::sole();
        $this->assertSame($mine->id, $report->slot_id);
        $this->assertSame($near->id, $report->replacement_slot_id);
        $this->assertSame(SlotReport::NEW, $report->status);

        // QR tetap sama: scan masuk tetap berhasil, sekarang untuk slot pengganti.
        $this->service()->checkIn($reservation);
        $this->assertSame('P1-A017', $reservation->slot->code);
    }

    public function test_other_problems_are_reported_without_moving_the_slot(): void
    {
        $reservation = $this->paid();

        $this->postJson("/api/reservations/{$reservation->code}/reports", ['type' => SlotReport::BLOCKED])
            ->assertCreated()
            ->assertJsonPath('data.replacement_slot', null)
            ->assertJsonPath('reservation.reports.0.type_label', 'Slot terhalang barang');

        $this->assertSame($reservation->slot_id, $reservation->fresh()->slot_id);

        $pending = $this->service()->reserve(Slot::factory()->available()->create(), 'Ani', 'D 9 AB');
        $this->postJson("/api/reservations/{$pending->code}/reports", ['type' => SlotReport::OTHER])->assertStatus(409);
    }

    public function test_security_handles_and_resolves_reports(): void
    {
        $reservation = $this->paid();
        $report = $this->service()->report($reservation, SlotReport::OCCUPIED, 'Mobil putih');
        $security = User::factory()->security()->create(['name' => 'Budi']);

        $this->actingAs($security)->get('/admin/security')
            ->assertOk()
            ->assertSee('Slot ditempati mobil lain')
            ->assertSee('Mobil putih')
            ->assertSee('B 1234 XYZ')
            ->assertSee('Tangani');

        $this->post("/admin/security/{$report->id}/tangani")->assertRedirect('/admin/security');
        $this->assertSame(SlotReport::HANDLING, $report->fresh()->status);
        $this->assertSame($security->id, $report->fresh()->handled_by);

        $this->post("/admin/security/{$report->id}/selesai")->assertRedirect('/admin/security');
        $this->assertSame(SlotReport::DONE, $report->fresh()->status);

        $this->get("/reservasi/{$reservation->code}")->assertSee('Selesai');
    }

    public function test_each_role_only_sees_its_own_pages(): void
    {
        $gate = User::factory()->gateOfficer()->create(['email' => 'gerbang@mall.test', 'password' => 'rahasia-123']);
        $security = User::factory()->security()->create();

        $this->post('/admin/login', ['email' => 'gerbang@mall.test', 'password' => 'rahasia-123'])->assertRedirect('/admin/gerbang');
        $this->get('/admin/gerbang')->assertOk()->assertDontSee('Status sensor');
        $this->get('/admin')->assertRedirect('/admin/gerbang');
        $this->get('/admin/laporan')->assertRedirect('/admin/gerbang');
        $this->get('/admin/security')->assertRedirect('/admin/gerbang');

        $this->actingAs($security)->get('/admin/gerbang')->assertRedirect('/admin/security');
        $this->actingAs($security)->get('/admin/security')->assertOk();

        $admin = User::factory()->create();
        foreach (['/admin', '/admin/gerbang', '/admin/security', '/admin/laporan'] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_make_admin_command_creates_staff_accounts_with_a_role(): void
    {
        $this->artisan('parkir:make-admin', ['email' => 'satpam@mall.test', '--role' => 'security', '--generate' => true])
            ->assertSuccessful();
        $this->assertSame(User::SECURITY, User::where('email', 'satpam@mall.test')->value('role'));

        $this->artisan('parkir:make-admin', ['email' => 'x@mall.test', '--role' => 'kasir', '--generate' => true])
            ->assertFailed();
    }
}
