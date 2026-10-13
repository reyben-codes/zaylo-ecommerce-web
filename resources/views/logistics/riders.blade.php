@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Riders')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Riders</h1>
            <p>Review rider applications and manage who can deliver for {{ $provider->name }}.</p>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Filter riders">
            @foreach($statusLabels as $key => $label)
                <a href="{{ route('logistics.riders', ['status' => $key]) }}" class="workspace-tab{{ $status === $key ? ' is-active' : '' }}" @if($status === $key) aria-current="true" @endif>
                    {{ $label }} <span>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Vehicle</th>
                        <th>Driver's license</th>
                        <th>{{ $status === 'pending' ? 'Applied' : 'Reviewed' }}</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($riders as $rider)
                        <tr>
                            <td>
                                <strong>{{ $rider->user->name }}</strong>
                                <span class="seller-cell-note">{{ $rider->user->email }} · {{ $rider->user->phone ?: 'no phone' }}</span>
                                @if($rider->user->date_of_birth)
                                    <span class="seller-cell-note">Age {{ \Illuminate\Support\Carbon::parse($rider->user->date_of_birth)->age }}</span>
                                @endif
                                @if($rider->user->email_verified_at === null)
                                    <span class="seller-cell-note">Email not verified yet</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $rider->vehicle_type ?: '—' }}</strong>
                                <span class="seller-cell-note">{{ $rider->plate_no ?: 'No plate' }}</span>
                            </td>
                            <td>
                                <strong>{{ $rider->license_number ?: '—' }}</strong>
                                @if($rider->license_expires_on)
                                    <span class="seller-cell-note{{ $rider->license_expired ? ' rider-license--expired' : '' }}">
                                        {{ $rider->license_expired ? 'Expired' : 'Expires' }} {{ $rider->license_expires_on->format('M j, Y') }}
                                    </span>
                                @endif
                                @php $files = collect(\App\Models\Rider::DOCUMENTS)->filter(fn ($label, $field) => $rider->{$field.'_path'}); @endphp
                                @if($files->isNotEmpty())
                                    <span class="rider-documents">
                                        @foreach($files as $field => $label)
                                            <a href="{{ route('logistics.riders.document', [$rider, $field]) }}" target="_blank" rel="noopener" class="workspace-link"><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $label }}</a>
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td>
                                {{ ($status === 'pending' ? $rider->created_at : ($rider->reviewed_at ?? $rider->updated_at))?->timezone('Asia/Manila')->format('M j, Y') }}
                                @if($rider->rejection_reason && in_array($status, ['rejected', 'suspended'], true))
                                    <span class="seller-cell-note">{{ $rider->rejection_reason }}</span>
                                @endif
                            </td>
                            <td>@include('logistics.partials.rider-actions', ['rider' => $rider])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="seller-empty">
                                    <i class="fas fa-motorcycle" aria-hidden="true"></i>
                                    <strong>No {{ Str::lower($statusLabels[$status]) }} riders</strong>
                                    @if($status === 'pending')
                                        <span>Riders choose {{ $provider->name }} when they sign up at "Become a rider".</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($riders->hasPages())
            <div class="seller-pagination">{{ $riders->links() }}</div>
        @endif
    </section>
@endsection
