<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Services\LogisticsEmployees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LogisticsEmployeeController extends Controller
{
    public function __construct(private LogisticsEmployees $employees) {}

    public function index(Request $request)
    {
        $provider = $this->employees->ownerCompany($request->user());
        if (! Schema::hasColumn('users', 'logistics_employee_provider_id')) {
            return view('admin.setup-needed', ['title' => 'Employee accounts']);
        }
        return view('logistics.employees', ['provider' => $provider, 'positions' => config('logistics_employees.positions'),
            'employees' => User::where('logistics_employee_provider_id', $provider->id)->orderBy('name')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $this->employees->ownerCompany($request->user());
        abort_unless(Schema::hasColumn('users', 'logistics_employee_provider_id'), 503, 'Run the employee accounts migration first.');
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', new PersonName],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', new PhoneNumber],
            'position' => ['required', Rule::in(array_keys(config('logistics_employees.positions')))],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed', new StrongPassword],
        ]);
        $employee = $this->employees->create($request->user(), $data);
        return redirect()->route('logistics.employees')->with('status', $employee->name.' can now sign in at Logistics login. Share their email and temporary password privately. They must verify their email and change the password before starting work.');
    }

    public function update(Request $request, User $employee)
    {
        $data = $request->validate([
            'position' => ['required', Rule::in(array_keys(config('logistics_employees.positions')))],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);
        $this->employees->update($request->user(), $employee, $data);
        return back()->with('status', 'Employee access updated. The employee must sign in again.');
    }

    public function resetPassword(Request $request, User $employee)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:128', 'confirmed', new StrongPassword]]);
        $this->employees->update($request->user(), $employee, $data);
        return back()->with('status', 'Temporary password replaced. Existing sessions were revoked; the employee must change this password after signing in.');
    }

    public function dashboard(Request $request)
    {
        return view('logistics.employee-dashboard', ['user' => $request->user(), 'provider' => $request->user()->logisticsProvider,
            'position' => config('logistics_employees.positions.'.$request->user()->logistics_position)]);
    }

    public function account(Request $request)
    {
        return view('logistics.employee-account', ['user' => $request->user(), 'provider' => $request->user()->logisticsProvider,
            'position' => config('logistics_employees.positions.'.$request->user()->logistics_position)]);
    }
}
