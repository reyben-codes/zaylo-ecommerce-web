<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LogisticsEmployees
{
    public function ownerCompany(User $owner): LogisticsProvider
    {
        abort_unless($owner->hasRole('logistics') && ! $owner->isLogisticsEmployee() && $owner->isActive(), 403);
        $company = $owner->logisticsProvider;
        abort_unless($company && (int) $company->user_id === (int) $owner->id && $company->status === 'approved', 403);
        return $company;
    }

    public function create(User $owner, array $data): User
    {
        $company = $this->ownerCompany($owner);
        return DB::transaction(function () use ($owner, $company, $data) {
            $employee = new User(['name' => $data['name'], 'email' => Str::lower(trim($data['email'])), 'password' => $data['password'],
                'phone' => $data['phone'] ?? null, 'role' => 'logistics', 'status' => 'active']);
            $employee->forceFill(['logistics_employee_provider_id' => $company->id, 'logistics_position' => $data['position'],
                'must_change_password' => true, 'email_verified_at' => null])->save();
            $this->audit($owner, $employee, 'created');
            return $employee;
        });
    }

    public function update(User $owner, User $employee, array $data): void
    {
        $company = $this->ownerCompany($owner);
        DB::transaction(function () use ($owner, $employee, $data, $company) {
            $employee = User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            abort_unless($employee->isLogisticsEmployee() && (int) $employee->logistics_employee_provider_id === (int) $company->id, 403);
            $before = ['position' => $employee->logistics_position, 'status' => $employee->status];
            if (isset($data['password'])) {
                $employee->forceFill(['password' => $data['password'], 'must_change_password' => true]);
            } else {
                $employee->forceFill(['logistics_position' => $data['position'], 'status' => $data['status']]);
            }
            $employee->forceFill(['remember_token' => Str::random(60)])->save();
            app(AccountSessions::class)->revokeMobileTokens($employee);
            if (Schema::hasTable('sessions')) DB::table('sessions')->where('user_id', $employee->id)->delete();
            $this->audit($owner, $employee, isset($data['password']) ? 'password_reset' : 'updated', $before);
        });
    }

    private function audit(User $actor, User $employee, string $action, array $before = []): void
    {
        app(ActivityLogger::class)->record('logistics.employee.'.$action, 'accounts', 'Logistics employee '.$action.'.', $actor, $employee,
            ['position' => $employee->logistics_position, 'status' => $employee->status, 'before' => $before]);
    }
}
