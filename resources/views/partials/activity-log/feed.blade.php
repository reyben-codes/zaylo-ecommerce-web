{{-- A compact list of activity entries: the rider page, an account's recent activity, the buyer account page. --}}
<ul class="activity-feed">
    @forelse($logs as $log)
        <li class="activity-feed__item">
            <i class="fas {{ $log->icon }} activity-feed__icon{{ $log->event === 'auth.failed' ? ' activity-feed__icon--warning' : '' }}" aria-hidden="true"></i>
            <div class="activity-feed__main">
                <strong>{{ $log->description }}@if($log->subject_label) · <span class="activity-feed__subject">{{ $log->subject_label }}</span>@endif</strong>
                @if($log->result)
                    <span>{{ $log->result }}</span>
                @endif
                @if($showDevice ?? false)
                    <span>{{ $log->ip_address ?? 'Unknown IP' }}{{ $log->user_agent ? ' · '.Str::limit($log->user_agent, 60) : '' }}</span>
                @endif
            </div>
            <time class="activity-feed__time" datetime="{{ $log->created_at?->toIso8601String() }}" title="{{ $log->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}">
                {{ $log->created_at?->timezone('Asia/Manila')->format('M j, g:i A') }}
            </time>
        </li>
    @empty
        <li class="activity-feed__empty">{{ $emptyText ?? 'No activity yet.' }}</li>
    @endforelse
</ul>
