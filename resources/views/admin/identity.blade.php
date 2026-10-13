@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'ID Checks')

@section('content')
    @php
        $tabs = ['pending' => 'To review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    @endphp

    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>ID checks</h1>
            <p>Buyers can browse and fill their cart right away, but can't check out until you approve their ID.</p>
        </div>
    </section>

    @unless($enabled)
        <aside class="seller-notice">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            <div>
                <strong>ID checks are not set up yet</strong>
                <p>Run <code>php artisan migrate --force</code> on the server to turn on buyer ID uploads.</p>
            </div>
        </aside>
    @else
        <aside class="seller-notice">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            <div>
                <strong>Before approving</strong>
                <p>The name and date of birth should match the buyer's account, the photo and text must be readable, and the ID must not be expired. Documents open privately and are never shared publicly.</p>
            </div>
        </aside>

        <section class="seller-panel">
            <nav class="workspace-tabs" aria-label="Filter ID checks">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('admin.identity', ['status' => $key]) }}" class="workspace-tab{{ $status === $key ? ' is-active' : '' }}" @if($status === $key) aria-current="true" @endif>
                        {{ $label }} <span>{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="seller-table-wrap">
                <table class="seller-table workspace-table">
                    <thead>
                        <tr>
                            <th>Buyer</th>
                            <th>ID type</th>
                            <th>{{ $status === 'pending' ? 'Submitted' : 'Reviewed' }}</th>
                            <th>Document</th>
                            <th>{{ $status === 'pending' ? 'Decision' : 'Outcome' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($verifications as $verification)
                            @php
                                $buyer = $verification->user;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $buyer?->name ?? 'Deleted account' }}</strong>
                                    <span class="seller-cell-note">{{ $buyer?->email }}</span>
                                    <span class="seller-cell-note">Born {{ $buyer?->date_of_birth?->format('M j, Y') ?? '—' }} · {{ $buyer?->phone ?: 'no phone' }}</span>
                                </td>
                                <td>{{ $verification->id_type_label }}</td>
                                <td>
                                    {{ ($status === 'pending' ? $verification->updated_at : $verification->reviewed_at)?->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                                    @if($status !== 'pending' && $verification->reviewer)
                                        <span class="seller-cell-note">by {{ $verification->reviewer->name }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.identity.document', $verification) }}" target="_blank" rel="noopener" class="seller-button seller-button--secondary seller-button--small">
                                        <i class="fas fa-up-right-from-square" aria-hidden="true"></i> View ID<span class="sr-only"> for {{ $buyer?->name }} (opens a new tab)</span>
                                    </a>
                                </td>
                                <td>
                                    @if($status === 'pending')
                                        <div class="review-actions">
                                            <form method="POST" action="{{ route('admin.identity.review', $verification) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="decision" value="approve">
                                                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Approve</button>
                                            </form>
                                            <details class="review-reason" data-smooth-disclosure>
                                                <summary class="seller-button seller-button--small seller-button--danger"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</summary>
                                                <div data-disclosure-content>
                                                    <form method="POST" action="{{ route('admin.identity.review', $verification) }}" class="review-reason__form">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="decision" value="reject">
                                                        <label for="reason-{{ $verification->id }}">Reason shown to the buyer</label>
                                                        <textarea id="reason-{{ $verification->id }}" name="reason" maxlength="500" required placeholder="e.g. The photo is blurry. Please upload a clear photo of the front of your ID."></textarea>
                                                        <button type="submit" class="seller-button seller-button--small seller-button--danger">Confirm rejection</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </div>
                                    @elseif($status === 'approved')
                                        <span class="role-pill account-status--active">Approved</span>
                                    @else
                                        <span class="role-pill account-status--rejected">Rejected</span>
                                        <span class="seller-cell-note">{{ $verification->rejection_reason }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="seller-empty">
                                        <i class="fas fa-id-card" aria-hidden="true"></i>
                                        <strong>{{ $status === 'pending' ? 'No IDs waiting for review' : 'Nothing here yet' }}</strong>
                                        @if($status === 'pending')
                                            <span>New buyer uploads appear here.</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($verifications->hasPages())
                <div class="seller-pagination">{{ $verifications->links() }}</div>
            @endif
        </section>
    @endunless
@endsection
