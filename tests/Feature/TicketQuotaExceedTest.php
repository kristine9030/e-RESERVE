<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TicketQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Offering more tickets than the venue count is possible, but only after a deliberate confirmation. */
class TicketQuotaExceedTest extends TestCase
{
    use RefreshDatabase;

    public function test_going_past_the_venue_needs_the_typed_total_and_the_password(): void
    {
        $admin = User::create(['email' => 'staff@g.batstate-u.edu.ph', 'name' => 'Staff', 'password' => 'secret-pass-1']);
        $admin->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()])->save();
        $this->actingAs($admin);
        $over = ['student' => 2700, 'employee' => 200, 'sponsor' => 300];   // 3,200

        // step 1: nothing saved, a warning appears
        $this->post(route('admin.settings.quotas'), ['quota' => $over])->assertSessionHas('exceed.total', 3200);
        $this->assertSame(2500, TicketQuota::limit('student'));

        // wrong number, wrong password: still nothing
        $this->post(route('admin.settings.quotas'), ['quota' => $over, 'confirm_total' => '3000', 'confirm_password' => 'secret-pass-1'])->assertSessionHas('flash.type', 'error');
        $this->post(route('admin.settings.quotas'), ['quota' => $over, 'confirm_total' => '3200', 'confirm_password' => 'nope'])->assertSessionHas('flash.type', 'error');
        $this->assertSame(3000, TicketQuota::capacity());

        // both right: saved, and the seat count rises
        $this->post(route('admin.settings.quotas'), ['quota' => $over, 'confirm_total' => '3200', 'confirm_password' => 'secret-pass-1'])->assertSessionHas('flash.type', 'success');
        $this->assertSame(3200, TicketQuota::capacity());
        $this->assertSame(2700, TicketQuota::limit('student'));
        $this->get(route('admin.settings'))->assertOk();

        // back within the venue count drops the extra allowance again
        $this->post(route('admin.settings.quotas'), ['quota' => ['student' => 2500, 'employee' => 200, 'sponsor' => 300]])->assertSessionHas('flash.type', 'success');
        $this->assertSame(3000, TicketQuota::capacity());
    }
}
