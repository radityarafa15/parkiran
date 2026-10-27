<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Masuk admin');
    }

    public function test_admin_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'admin@mall.test', 'password' => 'rahasia-123']);

        $this->post('/admin/login', ['email' => 'admin@mall.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'admin@mall.test', 'password' => 'rahasia-123'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_dashboard_lists_only_offline_sensors_oldest_first(): void
    {
        config(['parkir.stale_after_seconds' => 300]);

        $p1 = Floor::factory()->create(['name' => 'Lantai P1', 'level' => 1]);
        $p2 = Floor::factory()->create(['name' => 'Lantai P2', 'level' => 2]);

        Slot::factory()->available()->for($p1)->create(['code' => 'P1-A001']);
        Slot::factory()->available()->for($p1)->create(['code' => 'P1-A002', 'last_seen_at' => now()->subMinutes(10)]);
        Slot::factory()->occupied()->for($p1)->create(['code' => 'P1-A003', 'last_seen_at' => now()->subHours(3)]);
        Slot::factory()->for($p2)->create(['code' => 'P2-A001']);

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('P1-A001')
            ->assertSeeInOrder(['P1-A003', 'P1-A002', 'P2-A001'])
            ->assertSee('belum pernah');

        $this->actingAs(User::factory()->create())
            ->get("/admin?floor={$p2->id}")
            ->assertOk()
            ->assertSee('P2-A001')
            ->assertDontSee('P1-A002');
    }

    public function test_make_admin_command_creates_a_user_that_can_log_in(): void
    {
        $this->artisan('parkir:make-admin', ['email' => 'teknisi@mall.test', '--no-interaction' => true])
            ->expectsOutputToContain('Kata sandi:')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'teknisi@mall.test']);
    }
}
