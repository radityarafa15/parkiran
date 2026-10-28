<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendPagesTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(): Reservation
    {
        return app(ReservationService::class)->reserve(Slot::factory()->available()->create(), 'Rafa', 'B 1 RF');
    }

    public function test_floor_page_shows_zone_map_and_lanes(): void
    {
        config(['parkir.map.slots_per_row' => 2]);
        $floor = Floor::factory()->create();
        foreach (['P1-A001', 'P1-A002', 'P1-A003', 'P1-A004', 'P1-A005'] as $code) {
            Slot::factory()->available()->for($floor)->create(['code' => $code, 'zone' => 'A']);
        }
        Slot::factory()->occupied()->for($floor)->create(['code' => 'P1-B001', 'zone' => 'B']);

        $html = $this->get("/lantai/{$floor->id}")
            ->assertOk()
            ->assertSee('Pintu masuk')
            ->assertSee('data-zone-chip="B" data-total="1"', false)
            ->getContent();

        // Zona A: 5 slot, 2 per baris, jadi lajur 4 slot (2 baris + jalur) dan lajur 1 slot. Zona B: 1 lajur.
        $this->assertSame(3, substr_count($html, 'class="lane"'));
        $this->assertSame(1, substr_count($html, 'class="aisle"'));
        $this->assertStringContainsString('data-level="full"', $html);
    }

    public function test_reservation_page_counts_down_to_the_deadline(): void
    {
        $reservation = $this->reservation();

        $this->get("/reservasi/{$reservation->code}")
            ->assertSee('Sisa waktu bayar')
            ->assertSee('data-countdown="'.$reservation->pay_before->toIso8601String().'"', false);

        app(ReservationService::class)->pay($reservation);

        $this->get("/reservasi/{$reservation->code}")
            ->assertSee('Sisa waktu masuk')
            ->assertSee('data-countdown="'.$reservation->fresh()->expires_at->toIso8601String().'"', false);
    }

    public function test_report_sums_admin_fees_and_forfeits(): void
    {
        $service = app(ReservationService::class);
        $service->checkIn($service->pay($this->reservation()));
        $service->pay($forfeited = $this->reservation());
        $this->reservation();

        $this->get('/admin/laporan')->assertRedirect('/admin/login');

        $this->travel(3)->hours();
        $this->actingAs(User::factory()->create())
            ->get('/admin/laporan?hari=7')
            ->assertOk()
            ->assertSee('Rp20.000')
            ->assertSee('dari 2 reservasi dibayar')
            ->assertSee('1 batal karena tidak dibayar')
            ->assertSee('50% dari yang dibayar')
            ->assertSee('Sedang parkir sekarang</span><span class="big">1</span>', false)
            ->assertSee($forfeited->code);
    }

    public function test_gate_page_offers_camera_scanning(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/gerbang')
            ->assertOk()
            ->assertSee('Scan pakai kamera')
            ->assertSee('/vendor/jsqr-1.4.0.js');
        $this->assertFileExists(public_path('vendor/jsqr-1.4.0.js'));
    }
}
