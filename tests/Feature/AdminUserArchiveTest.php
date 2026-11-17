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
    }
}
