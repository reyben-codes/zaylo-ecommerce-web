<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_archive_restore_and_permanently_delete_an_archived_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $buyer))->assertStatus(422);
        $this->patch(route('admin.users.archive', $buyer))->assertRedirect(route('admin.archive'));
        $this->assertFalse($buyer->fresh()->isActive());
        $this->get(route('admin.users'))->assertOk()->assertDontSee($buyer->email);
        $this->get(route('admin.archive'))->assertOk()->assertSee($buyer->email)->assertSee('Delete permanently');
        $this->patch(route('admin.users.status', $buyer), ['status' => 'suspended'])->assertStatus(422);
        $this->patch(route('admin.users.restore', $buyer))->assertRedirect(route('admin.archive'));
        $this->assertTrue($buyer->fresh()->isActive());
        $this->patch(route('admin.users.archive', $buyer))->assertRedirect();
        $this->delete(route('admin.users.destroy', $buyer))->assertRedirect(route('admin.archive'));
        $this->assertDatabaseMissing('users', ['id' => $buyer->id]);
        $log = \App\Models\ActivityLog::where('event', 'admin.users.destroy')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($buyer->id, $log->subject_id);
        $this->assertEmpty($log->properties);
    }

    public function test_only_admins_can_manage_archive_and_they_cannot_archive_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('admin.archive'))->assertForbidden();
        $this->patch(route('admin.users.archive', $admin))->assertForbidden();
        $this->patch(route('admin.users.restore', $admin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.users.archive', $admin))->assertStatus(422);
        $this->delete(route('admin.users.destroy', $admin))->assertStatus(422);
    }

    public function test_archived_accounts_with_protected_orders_remain_archived(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        \Illuminate\Support\Facades\DB::table('orders')->insert([
            'buyer_id' => $buyer->id, 'reference' => 'ARCHIVE-HISTORY', 'total_minor' => 100,
            'payment_method' => 'cod', 'shipping_address' => '{}',
        ]);
        $this->actingAs($admin)->patch(route('admin.users.archive', $buyer))->assertRedirect();
        $this->from(route('admin.archive'))->delete(route('admin.users.destroy', $buyer))
            ->assertRedirect(route('admin.archive'))->assertSessionHasErrors('archive');
        $this->assertNotNull($buyer->fresh()->archived_at);
        $this->assertDatabaseHas('orders', ['reference' => 'ARCHIVE-HISTORY']);
        $this->assertDatabaseMissing('activity_logs', ['event' => 'admin.users.destroy']);
    }

    public function test_permanent_deletion_validates_and_records_the_optional_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.users.archive', $buyer))->assertRedirect();
        $this->get(route('admin.archive'))->assertOk()->assertSee('data-confirm-reason="reason"', false)
            ->assertSee('Yes, delete permanently');
        $this->delete(route('admin.users.destroy', $buyer), ['reason' => str_repeat('a', 501)])
            ->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('users', ['id' => $buyer->id]);
        $this->assertDatabaseMissing('activity_logs', ['event' => 'admin.users.destroy']);

        $this->delete(route('admin.users.destroy', $buyer), ['reason' => 'Duplicate account at the owner\'s request.'])
            ->assertRedirect(route('admin.archive'));
        $log = \App\Models\ActivityLog::where('event', 'admin.users.destroy')->sole();
        $this->assertSame('Duplicate account at the owner\'s request.', $log->properties['reason']);
        $this->assertSame($buyer->name, $log->subject_label);
        $this->assertSame($admin->id, $log->user_id);
    }
}
