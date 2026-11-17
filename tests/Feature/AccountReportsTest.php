<?php

namespace Tests\Feature;

use App\Models\AccountReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class AccountReportsTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    private function data(): array
    {
        return ['reported_name' => 'Concerned shop', 'reported_role' => 'seller', 'category' => 'misconduct', 'details' => 'The seller sent abusive messages after I asked about my order.'];
    }

    public function test_all_roles_can_file_reports_and_only_see_their_own_reports(): void
    {
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        AccountReport::create($this->data() + ['reporter_id' => $other->id, 'status' => 'open']);
        foreach (['buyer', 'seller', 'rider', 'courier', 'logistics', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get(route('account-reports.index'))->assertOk()->assertDontSee($this->data()['details']);
            $this->post(route('account-reports.store'), $this->data() + ['status' => 'resolved', 'reviewed_by' => $user->id])
                ->assertRedirect(route('account-reports.index'));
            $this->assertDatabaseHas('account_reports', ['reporter_id' => $user->id, 'status' => 'open', 'reviewed_by' => null]);
            $this->get(route('account-reports.index'))->assertOk()->assertSee($this->data()['details']);
        }
    }

    public function test_buyer_reports_shop_owner_and_admin_records_a_private_outcome(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $shop = $seller->sellerShop();
        $this->actingAs($buyer)->get(route('account-reports.index', ['shop_id' => $shop->id]))->assertOk()->assertSee('Reporting seller:');
        $this->post(route('account-reports.store'), ['shop_id' => $shop->id, 'category' => 'harassment', 'details' => $this->data()['details']])->assertRedirect();
        $report = AccountReport::sole();
        $this->assertSame($seller->id, $report->reported_user_id);
        $this->get(route('admin.account-reports'))->assertForbidden();
        $this->actingAs($seller)->get(route('account-reports.index'))->assertOk()->assertDontSee($report->details);
        $this->get(route('admin.account-reports.show', $report))->assertForbidden();
        $this->patch(route('admin.account-reports.review', $report), ['status' => 'resolved', 'resolution' => 'Not authorized'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.account-reports'))->assertOk()->assertSee($seller->name);
        $this->get(route('admin.account-reports.show', $report))->assertOk()->assertSee($report->details);
        $this->patch(route('admin.account-reports.review', $report), ['status' => 'resolved'])->assertSessionHasErrors('resolution');
        $this->patch(route('admin.account-reports.review', $report), ['status' => 'resolved', 'resolution' => 'The account has been reviewed and the seller was contacted.'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame($admin->id, $report->fresh()->reviewed_by);
        $this->actingAs($buyer)->get(route('account-reports.index'))->assertSee('The account has been reviewed');
        $this->actingAs($seller)->get(route('account-reports.index'))->assertDontSee('The account has been reviewed');
    }

    public function test_mobile_buyers_and_riders_can_report_without_exposing_another_reporters_details(): void
    {
        foreach (['buyer', 'rider'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->signInMobile($user)->getJson('/api/mobile/account-reports')->assertOk()->assertJsonCount(0, 'reports.data');
            $this->postJson('/api/mobile/account-reports', $this->data())->assertCreated()->assertJsonPath('report.status', 'open');
            $this->getJson('/api/mobile/account-reports')->assertOk()->assertJsonCount(1, 'reports.data')->assertJsonPath('reports.data.0.reporter_id', $user->id);
        }
    }

    public function test_invalid_self_reports_and_inactive_accounts_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($user)->post(route('account-reports.store'), ['details' => 'short'])->assertSessionHasErrors(['category', 'details']);
        $this->post(route('account-reports.store'), $this->data() + ['reported_user_id' => $user->id])->assertSessionHasErrors('reported_name');
        $this->signInMobile($user);
        $user->forceFill(['archived_at' => now()])->save();
        $this->postJson('/api/mobile/account-reports', $this->data())->assertForbidden();
        $this->assertDatabaseCount('account_reports', 0);
    }

    public function test_admin_cannot_review_a_report_they_filed_or_that_targets_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        foreach ([['reporter_id' => $admin->id], ['reporter_id' => $buyer->id, 'reported_user_id' => $admin->id]] as $parties) {
            $report = AccountReport::create($this->data() + $parties + ['status' => 'open']);
            $this->actingAs($admin)->patch(route('admin.account-reports.review', $report), ['status' => 'in_review'])->assertForbidden();
        }
    }
}
