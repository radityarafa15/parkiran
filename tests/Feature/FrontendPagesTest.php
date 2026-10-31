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

    public function test_floor_page_lays_each_zone_out_like_the_real_floor_plan(): void
    {
        $floor = Floor::factory()->create();
        // Zona A penuh satu blok (60 slot); zona B blok terakhir yang tidak penuh (4 slot).
        foreach (range(1, 60) as $n) {
            Slot::factory()->available()->for($floor)->create(['code' => sprintf('P1-A%03d', $n), 'zone' => 'A']);
        }
        foreach (range(1, 4) as $n) {
            Slot::factory()->occupied()->for($floor)->create(['code' => sprintf('P1-B%03d', $n), 'zone' => 'B']);
        }

        $html = $this->get("/lantai/{$floor->id}")
            ->assertOk()
            ->assertSee('data-zone-chip="B" data-total="4"', false)
            ->getContent();

        // Tiap blok: 20 bay (7 + 6 + 7), satu CORE, dua jalur.
        $this->assertSame(2, substr_count($html, 'class="plan"'));
        $this->assertSame(40, substr_count($html, 'class="bay"'));
        $this->assertSame(2, substr_count($html, '>CORE<'));
        $this->assertSame(4, substr_count($html, 'class="plan-aisle"'));
        // Blok B hanya punya 4 slot; sisa 56 tempat digambar kosong.
        $this->assertSame(56, substr_count($html, 'class="slot s-none"'));
        $this->assertStringContainsString('data-level="full"', $html);

        // Baris atas zona A berakhir di slot 021, pulau tengah mulai di slot 022.
        $this->assertMatchesRegularExpression('/title="P1-A021">021<\/a>\s*<\/div>\s*<div aria-hidden="true"><\/div>\s*<div class="plan-aisle"/', $html);
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
        $service->checkOut($service->checkIn($service->pay($this->reservation())));
        $service->pay($forfeited = $this->reservation());
        $this->reservation();

        $this->get('/admin/laporan')->assertRedirect('/admin/login');

        $this->travel(3)->hours();
        $this->actingAs(User::factory()->create())
            ->get('/admin/laporan?hari=7')
            ->assertOk()
            ->assertSee('Rp30.000')
            ->assertSee('dari 3 reservasi dibayar')
            ->assertSee('1 batal karena tidak dibayar')
            ->assertSee('33% dari yang dibayar')
            ->assertSee('Pendapatan biaya parkir</span><span class="big">Rp5.000</span>', false)
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
