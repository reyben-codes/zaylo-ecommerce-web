{{-- Activity log table with filters, for the seller, logistics and admin workspaces. --}}
@php
    $roleLabels ??= [];
    $showActor ??= false;
    $showDevice ??= false;
    $hasFilters = collect($filters)->except('user')->filter()->isNotEmpty();
@endphp
<section class="seller-panel activity-log">
    <form method="GET" action="{{ $formAction }}" class="seller-filter-bar" role="search">
        @if(! empty($filters['user']))
            <input type="hidden" name="user" value="{{ $filters['user'] }}">
        @endif
        <div class="seller-field seller-field--search">
            <label for="activity-search">Search</label>
            <input type="search" id="activity-search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="{{ $showActor ? 'Action, name, order or parcel' : 'Action, order, product or parcel' }}">
        </div>
        <div class="seller-field">
            <label for="activity-category">Type</label>
            <select id="activity-category" name="category">
                <option value="">All activity</option>
                @foreach($categories as $value => $label)
                    <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($roleLabels)
            <div class="seller-field">
                <label for="activity-role">{{ $roleFilterLabel ?? 'Role' }}</label>
                <select id="activity-role" name="role">
                    <option value="">Everyone</option>
                    @foreach($roleLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="seller-field activity-log__date">
            <label for="activity-from">From</label>
            <input type="date" id="activity-from" name="from" value="{{ $filters['from'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
        </div>
        <div class="seller-field activity-log__date">
            <label for="activity-to">To</label>
            <input type="date" id="activity-to" name="to" value="{{ $filters['to'] }}" max="{{ now('Asia/Manila')->toDateString() }}">
        </div>
        <div class="review-actions">
            <button type="submit" class="seller-button"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
            @if($hasFilters)
                <a href="{{ $formAction.(! empty($filters['user']) ? '?user='.$filters['user'] : '') }}" class="seller-button seller-button--secondary">Reset</a>
            @endif
        </div>
    </form>

    @if($errors->any())
        <p class="activity-log__error" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="seller-table-wrap">
        <table class="seller-table workspace-table activity-log__table">
            <thead>
                <tr>
                    <th scope="col">When</th>
                    @if($showActor)
                        <th scope="col">Who</th>
                    @endif
                    <th scope="col">Activity</th>
                    <th scope="col">Type</th>
                    @if($showDevice)
                        <th scope="col">IP address</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="activity-log__when">
                            <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</time>
                            <span class="seller-cell-note">{{ $log->created_at?->timezone('Asia/Manila')->format('g:i A') }}</span>
                        </td>
                        @if($showActor)
                            <td>
                                @if($log->user_id && ($actorLink ?? false))
                                    <a href="{{ route('admin.users.show', $log->user_id) }}" class="workspace-link"><strong>{{ $log->actor_name ?? 'Deleted account' }}</strong></a>
                                @else
                                    <strong>{{ $log->actor_name ?? 'Deleted account' }}</strong>
                                @endif
                                <span class="seller-cell-note">{{ $log->role_label }}</span>
                            </td>
                        @endif
                        <td>
                            <div class="activity-log__entry">
                                <i class="fas {{ $log->icon }} activity-log__icon{{ $log->event === 'auth.failed' ? ' activity-log__icon--warning' : '' }}" aria-hidden="true"></i>
                                <div>
                                    <strong>{{ $log->description }}</strong>
                                    @if($log->subject_label)
                                        <span class="activity-log__subject">{{ $log->subject_label }}</span>
                                    @endif
                                    @if($log->result)
                                        <span class="seller-cell-note">{{ $log->result }}</span>
                                    @endif
                                    @if($details = $log->details())
                                        <ul class="activity-log__details" aria-label="Details">
                                            @foreach($details as $label => $value)
                                                <li><span>{{ $label }}:</span> {{ $value }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><span class="role-pill activity-log__category">{{ $log->category_label }}</span></td>
                        @if($showDevice)
                            <td>
                                {{ $log->ip_address ?? '—' }}
                                @if($log->user_agent)
                                    <span class="seller-cell-note activity-log__agent" title="{{ $log->user_agent }}">{{ Str::limit($log->user_agent, 48) }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + ($showActor ? 1 : 0) + ($showDevice ? 1 : 0) }}">
                            <div class="seller-empty">
                                <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                                <strong>{{ $hasFilters ? 'No activity matches these filters' : 'No activity yet' }}</strong>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="seller-pagination">{{ $logs->links() }}</div>
    @endif
</section>
