@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Users')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>Users</h1>
            <p>Find any account, check its status, and suspend or reactivate it.</p>
        </div>
    </section>

    <section class="seller-panel">
        <form method="GET" action="{{ route('admin.users') }}" class="seller-filter-bar" role="search">
            <div class="seller-field seller-field--search">
                <label for="user-search">Search</label>
                <input type="search" id="user-search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Name or email">
            </div>
            <div class="seller-field">
                <label for="user-role">Role</label>
                <select id="user-role" name="role">
                    <option value="">All roles</option>
                    @foreach($roleLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="seller-field">
                <label for="user-status">Status</label>
                <select id="user-status" name="status">
                    <option value="">All statuses</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="review-actions">
                <button type="submit" class="seller-button"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.users') }}" class="seller-button seller-button--secondary">Reset</a>
                @endif
            </div>
        </form>

        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $account)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $account) }}" class="workspace-link"><strong>{{ $account->name }}</strong></a>
                                <span class="seller-cell-note">ID #{{ $account->id }} · {{ $account->email }}</span>
                            </td>
                            <td>
                                <span class="role-pill">{{ $roleLabels[$account->role] ?? Str::headline($account->role) }}</span>
                                @if($account->role === 'logistics' && $account->logisticsProvider)
                                    <span class="seller-cell-note">{{ $account->logisticsProvider->name }}</span>
                                @elseif($account->role === 'rider' && $account->rider?->provider)
                                    <span class="seller-cell-note">Rides for {{ $account->rider->provider->name }}</span>
                                @endif
                            </td>
                            <td><span class="role-pill account-status--{{ $account->status }}">{{ $statusLabels[$account->status] ?? Str::headline($account->status) }}</span></td>
                            <td>{{ $account->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</td>
                            <td>@include('admin.partials.account-actions', ['account' => $account])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="seller-empty">
                                    <i class="fas fa-users" aria-hidden="true"></i>
                                    <strong>No accounts match these filters</strong>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="seller-pagination">{{ $users->links() }}</div>
        @endif
    </section>
@endsection
