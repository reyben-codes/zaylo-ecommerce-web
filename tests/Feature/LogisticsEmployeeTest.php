<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function createLogisticsOwner(): array
    {
        $owner = User::factory()->create([
            'role' => 'logistics',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $provider = LogisticsProvider::create([
            'user_id' => $owner->id,
            'name' => 'Metro Fast Express',
            'slug' => 'metro-fast-express',
            'status' => 'approved',
            'approved_at' => now(),
            'contact_phone' => '09171234567',
        ]);

        return [$owner, $provider];
    }

    private function createEmployee(LogisticsProvider $provider, string $position = 'sorting_staff', bool $mustChangePassword = false): User
    {
        return User::factory()->create([
            'role' => 'logistics',
            'status' => 'active',
            'email_verified_at' => now(),
            'logistics_employee_provider_id' => $provider->id,
            'logistics_position' => $position,
            'must_change_password' => $mustChangePassword,
        ]);
    }

    public function test_owner_can_view_employees_page_and_create_employee(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();

        $this->actingAs($owner)
            ->get(route('logistics.employees'))
            ->assertOk()
            ->assertSee('Employee Accounts')
            ->assertSee('Total Staff');

        $response = $this->actingAs($owner)
            ->post(route('logistics.employees.store'), [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@metrofast.ph',
                'phone' => '09181234567',
                'position' => 'sorting_staff',
                'password' => 'SecurePass123!@#',
                'password_confirmation' => 'SecurePass123!@#',
            ]);

        $response->assertRedirect(route('logistics.employees'));
        $this->assertDatabaseHas('users', [
            'email' => 'maria.santos@metrofast.ph',
            'logistics_employee_provider_id' => $provider->id,
            'logistics_position' => 'sorting_staff',
            'must_change_password' => true,
        ]);
    }

    public function test_non_owner_and_employees_cannot_manage_employees(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $employee = $this->createEmployee($provider, 'hub_supervisor');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $this->actingAs($employee)
            ->get(route('logistics.employees'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('logistics.employees'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('logistics.employees.store'), [
                'name' => 'Hacker Staff',
                'email' => 'hacker@test.com',
                'position' => 'hub_supervisor',
                'password' => 'Password123!@#',
                'password_confirmation' => 'Password123!@#',
            ])->assertForbidden();
    }

    public function test_owner_can_update_employee_position_and_status(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $employee = $this->createEmployee($provider, 'sorting_staff');

        $this->actingAs($owner)
            ->patch(route('logistics.employees.update', $employee), [
                'position' => 'dispatcher',
                'status' => 'suspended',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'logistics_position' => 'dispatcher',
            'status' => 'suspended',
        ]);
    }

    public function test_owner_can_reset_employee_password(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $employee = $this->createEmployee($provider, 'dispatcher', false);

        $this->actingAs($owner)
            ->put(route('logistics.employees.password', $employee), [
                'password' => 'NewTempPass123!@#',
                'password_confirmation' => 'NewTempPass123!@#',
            ])
            ->assertRedirect();

        $employee->refresh();
        $this->assertTrue($employee->must_change_password);
    }

    public function test_sorting_staff_permissions(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $staff = $this->createEmployee($provider, 'sorting_staff', false);

        // Permitted routes
        $this->actingAs($staff)->get(route('logistics.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('logistics.sorting'))->assertOk();
        $this->actingAs($staff)->get(route('logistics.scan'))->assertOk();
        $this->actingAs($staff)->get(route('logistics.incoming'))->assertOk();
        $this->actingAs($staff)->get(route('logistics.account'))->assertOk();

        // Forbidden routes
        $this->actingAs($staff)->get(route('logistics.cash'))->assertForbidden();
        $this->actingAs($staff)->get(route('logistics.riders'))->assertForbidden();
        $this->actingAs($staff)->get(route('logistics.couriers'))->assertForbidden();
        $this->actingAs($staff)->get(route('logistics.reports'))->assertForbidden();
        $this->actingAs($staff)->get(route('logistics.rider-withdrawals'))->assertForbidden();
    }

    public function test_finance_staff_permissions(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $finance = $this->createEmployee($provider, 'finance_staff', false);

        // Permitted routes
        $this->actingAs($finance)->get(route('logistics.dashboard'))->assertOk();
        $this->actingAs($finance)->get(route('logistics.cash'))->assertOk();
        $this->actingAs($finance)->get(route('logistics.rider-withdrawals'))->assertOk();
        $this->actingAs($finance)->get(route('logistics.account'))->assertOk();

        // Forbidden routes
        $this->actingAs($finance)->get(route('logistics.sorting'))->assertForbidden();
        $this->actingAs($finance)->get(route('logistics.pickups'))->assertForbidden();
        $this->actingAs($finance)->get(route('logistics.scan'))->assertForbidden();
        $this->actingAs($finance)->get(route('logistics.riders'))->assertForbidden();
    }

    public function test_dispatcher_permissions(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $dispatcher = $this->createEmployee($provider, 'dispatcher', false);

        // Permitted routes
        $this->actingAs($dispatcher)->get(route('logistics.dashboard'))->assertOk();
        $this->actingAs($dispatcher)->get(route('logistics.pickups'))->assertOk();
        $this->actingAs($dispatcher)->get(route('logistics.assignments'))->assertOk();
        $this->actingAs($dispatcher)->get(route('logistics.deliveries'))->assertOk();
        $this->actingAs($dispatcher)->get(route('logistics.monitoring'))->assertOk();

        // Forbidden routes
        $this->actingAs($dispatcher)->get(route('logistics.cash'))->assertForbidden();
        $this->actingAs($dispatcher)->get(route('logistics.rider-withdrawals'))->assertForbidden();
        $this->actingAs($dispatcher)->get(route('logistics.incoming'))->assertForbidden();
    }

    public function test_hub_supervisor_permissions(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $supervisor = $this->createEmployee($provider, 'hub_supervisor', false);

        // Permitted routes
        $this->actingAs($supervisor)->get(route('logistics.dashboard'))->assertOk();
        $this->actingAs($supervisor)->get(route('logistics.pickups'))->assertOk();
        $this->actingAs($supervisor)->get(route('logistics.sorting'))->assertOk();
        $this->actingAs($supervisor)->get(route('logistics.scan'))->assertOk();
        $this->actingAs($supervisor)->get(route('logistics.riders'))->assertOk();
        $this->actingAs($supervisor)->get(route('logistics.reports'))->assertOk();

        // Forbidden routes (supervisor does not have finance or employee management or earnings)
        $this->actingAs($supervisor)->get(route('logistics.cash'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('logistics.earnings'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('logistics.employees'))->assertForbidden();
    }

    public function test_employees_cannot_update_company_settings(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $supervisor = $this->createEmployee($provider, 'hub_supervisor', false);

        $this->actingAs($supervisor)
            ->patch(route('logistics.account.company'), [
                'company_name' => 'Hacked Company Name',
                'contact_phone' => '09170000000',
            ])
            ->assertForbidden();
    }

    public function test_must_change_password_redirects_to_account_until_password_is_updated(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $staff = $this->createEmployee($provider, 'sorting_staff', true);

        // Attempting to visit sorting redirects to account
        $this->actingAs($staff)
            ->get(route('logistics.sorting'))
            ->assertRedirect(route('logistics.account'));

        // Employee changes their password
        $this->actingAs($staff)
            ->put(route('logistics.account.password'), [
                'current_password' => 'password',
                'password' => 'NewSecuredPassword123!@#',
                'password_confirmation' => 'NewSecuredPassword123!@#',
            ])
            ->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertFalse((bool) $staff->must_change_password);

        $res = $this->actingAs($staff)
            ->get(route('logistics.sorting'));
        $res->assertOk();
    }

    public function test_suspended_employee_or_inactive_company_is_forbidden(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();
        $suspendedStaff = $this->createEmployee($provider, 'sorting_staff', false);
        $suspendedStaff->update(['status' => 'suspended']);

        $this->actingAs($suspendedStaff)
            ->get(route('logistics.dashboard'))
            ->assertForbidden();

        // Active staff under a suspended company
        $staff = $this->createEmployee($provider, 'sorting_staff', false);
        $provider->update(['status' => 'suspended']);

        $this->actingAs($staff)
            ->get(route('logistics.dashboard'))
            ->assertForbidden();
    }

    public function test_position_based_parcel_advancement_permissions(): void
    {
        [$owner, $provider] = $this->createLogisticsOwner();

        $supervisor = $this->createEmployee($provider, 'hub_supervisor', false);
        $dispatcher = $this->createEmployee($provider, 'dispatcher', false);
        $sorting = $this->createEmployee($provider, 'sorting_staff', false);
        $finance = $this->createEmployee($provider, 'finance_staff', false);

        // Supervisor can advance picked_up, in_transit, returned
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($supervisor, 'picked_up'));
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($supervisor, 'in_transit'));
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($supervisor, 'returned'));

        // Dispatcher can advance picked_up, but not in_transit or returned
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($dispatcher, 'picked_up'));
        $this->assertFalse(\App\Services\LogisticsAccess::canAdvance($dispatcher, 'in_transit'));
        $this->assertFalse(\App\Services\LogisticsAccess::canAdvance($dispatcher, 'returned'));

        // Sorting staff can advance in_transit and returned, but not picked_up
        $this->assertFalse(\App\Services\LogisticsAccess::canAdvance($sorting, 'picked_up'));
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($sorting, 'in_transit'));
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($sorting, 'returned'));

        // Finance cannot advance anything
        $this->assertFalse(\App\Services\LogisticsAccess::canAdvance($finance, 'picked_up'));
        $this->assertFalse(\App\Services\LogisticsAccess::canAdvance($finance, 'in_transit'));
        $this->assertFalse(\App\Services\LogisticsAccess::canAdvance($finance, 'returned'));

        // Owner can advance anything
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($owner, 'picked_up'));
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($owner, 'in_transit'));
        $this->assertTrue(\App\Services\LogisticsAccess::canAdvance($owner, 'returned'));
    }
}
