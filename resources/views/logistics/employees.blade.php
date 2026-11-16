@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Employee Accounts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/logistics-employees.css') }}?v={{ filemtime(public_path('css/logistics-employees.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/smooth-disclosure.js') }}" defer></script>
@endpush

@section('content')
    <section class="workspace-page-heading">
        <div class="employee-management-header">
            <div>
                <p class="workspace-eyebrow">{{ $provider->name }}</p>
                <h1>Employee Accounts & Role Control</h1>
                <p>Add staff accounts for your hub, set position limits, and manage login access.</p>
            </div>
        </div>
    </section>

    @if (session('status'))
        <div class="workspace-alert is-success" role="status">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="workspace-alert is-danger" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Please correct the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="employee-stat-grid" aria-label="Employee summary">
        <article class="employee-card">
            <span class="workspace-stat__label">Total Staff</span>
            <strong class="workspace-stat__value">{{ $employees->total() }}</strong>
        </article>
        <article class="employee-card">
            <span class="workspace-stat__label">Active</span>
            <strong class="workspace-stat__value">{{ $employees->where('status', 'active')->count() }}</strong>
        </article>
        <article class="employee-card">
            <span class="workspace-stat__label">Suspended</span>
            <strong class="workspace-stat__value">{{ $employees->where('status', 'suspended')->count() }}</strong>
        </article>
        <article class="employee-card">
            <span class="workspace-stat__label">Positions</span>
            <strong class="workspace-stat__value">{{ count($positions) }} Roles</strong>
        </article>
    </section>

    {{-- Add Employee Section --}}
    <section class="employee-card employee-card--spaced">
        <details data-smooth-disclosure>
            <summary class="employee-btn employee-btn--primary">
                <i class="fas fa-user-plus" aria-hidden="true"></i> Add New Employee
            </summary>
            <div data-disclosure-content class="employee-disclosure-body">
                <form action="{{ route('logistics.employees.store') }}" method="POST">
                    @csrf
                    <div class="employee-form-grid">
                        <div class="employee-field">
                            <label for="emp-name">Full Name *</label>
                            <input type="text" id="emp-name" name="name" required value="{{ old('name') }}" placeholder="e.g. Juan dela Cruz">
                        </div>
                        <div class="employee-field">
                            <label for="emp-email">Corporate / Work Email *</label>
                            <input type="email" id="emp-email" name="email" required value="{{ old('email') }}" placeholder="juan@company.com">
                        </div>
                        <div class="employee-field">
                            <label for="emp-phone">Contact Phone</label>
                            <input type="text" id="emp-phone" name="phone" value="{{ old('phone') }}" placeholder="0917XXXXXXX">
                        </div>
                        <div class="employee-field">
                            <label for="emp-position">Employee Position *</label>
                            <select id="emp-position" name="position" required>
                                @foreach($positions as $key => $pos)
                                    <option value="{{ $key }}" {{ old('position') === $key ? 'selected' : '' }}>
                                        {{ $pos['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="employee-field">
                            <label for="emp-password">Temporary Password *</label>
                            <input type="password" id="emp-password" name="password" required minlength="12" placeholder="Min 12 characters">
                        </div>
                        <div class="employee-field">
                            <label for="emp-password-confirmation">Confirm Password *</label>
                            <input type="password" id="emp-password-confirmation" name="password_confirmation" required minlength="12" placeholder="Confirm password">
                        </div>
                    </div>

                    <div class="position-card-grid">
                        @foreach($positions as $pos)
                            <div class="position-card">
                                <strong>{{ $pos['label'] }}</strong>
                                <p>{{ $pos['description'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="employee-form-actions">
                        <button type="submit" class="employee-btn employee-btn--primary">
                            <i class="fas fa-check" aria-hidden="true"></i> Create Employee Account
                        </button>
                    </div>
                </form>
            </div>
        </details>
    </section>

    {{-- Employees Table --}}
    <section class="employee-card">
        <h2 class="employee-card__title">
            <i class="fas fa-users" aria-hidden="true"></i> Active & Registered Employees
        </h2>

        @if($employees->isEmpty())
            <div class="employee-empty-state">
                <i class="fas fa-id-badge employee-empty-icon" aria-hidden="true"></i>
                <p>No employees registered yet. Click "Add New Employee" to grant position-specific hub access to your team.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="employee-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Contact</th>
                            <th>Assigned Position</th>
                            <th>Status</th>
                            <th>Security</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employees as $employee)
                            @php
                                $posKey = $employee->logistics_position;
                                $posLabel = $positions[$posKey]['label'] ?? ucfirst(str_replace('_', ' ', $posKey));
                            @endphp
                            <tr>
                                <td>
                                    <div class="employee-user-cell">
                                        <div class="employee-avatar">
                                            {{ strtoupper(substr($employee->name, 0, 1)) }}
                                        </div>
                                        <div class="employee-meta">
                                            <span class="employee-name">{{ $employee->name }}</span>
                                            <span class="employee-email">{{ $employee->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $employee->phone ?: 'None' }}</td>
                                <td>
                                    <span class="employee-pill employee-pill--{{ $posKey }}">
                                        {{ $posLabel }}
                                    </span>
                                </td>
                                <td>
                                    @if($employee->status === 'active')
                                        <span class="employee-pill employee-pill--active">
                                            <i class="fas fa-circle-check" aria-hidden="true"></i> Active
                                        </span>
                                    @else
                                        <span class="employee-pill employee-pill--suspended">
                                            <i class="fas fa-ban" aria-hidden="true"></i> Suspended
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($employee->must_change_password)
                                        <span class="employee-pill employee-pill--warn">
                                            <i class="fas fa-key" aria-hidden="true"></i> Must Change Password
                                        </span>
                                    @else
                                        <span class="employee-pill employee-pill--active">
                                            <i class="fas fa-shield" aria-hidden="true"></i> Secured
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <details data-smooth-disclosure class="employee-disclosure">
                                        <summary class="employee-btn employee-btn--outline">
                                            <i class="fas fa-sliders" aria-hidden="true"></i> Manage
                                        </summary>
                                        <div data-disclosure-content class="employee-manage-menu">
                                            {{-- Update Position and Status --}}
                                            <form action="{{ route('logistics.employees.update', $employee) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <div class="employee-action-row">
                                                    <select name="position" class="employee-action-select">
                                                        @foreach($positions as $key => $pos)
                                                            <option value="{{ $key }}" {{ $employee->logistics_position === $key ? 'selected' : '' }}>
                                                                {{ $pos['label'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <select name="status" class="employee-action-select">
                                                        <option value="active" {{ $employee->status === 'active' ? 'selected' : '' }}>Active</option>
                                                        <option value="suspended" {{ $employee->status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                                    </select>
                                                    <button type="submit" class="employee-btn employee-btn--primary">Update</button>
                                                </div>
                                            </form>

                                            {{-- Reset Password --}}
                                            <form action="{{ route('logistics.employees.password', $employee) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="employee-action-row">
                                                    <input type="password" name="password" required minlength="12" placeholder="New temporary password" class="employee-action-input">
                                                    <input type="password" name="password_confirmation" required minlength="12" placeholder="Confirm" class="employee-action-input">
                                                    <button type="submit" class="employee-btn employee-btn--outline">Reset Password</button>
                                                </div>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($employees->hasPages())
                <div class="employee-pagination">
                    {{ $employees->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection
