@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Pickup fleet (First Mile)')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/document-viewer.css') }}?v={{ filemtime(public_path('css/document-viewer.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/document-viewer.js') }}?v={{ filemtime(public_path('js/document-viewer.js')) }}" defer></script>
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Pickup fleet (First Mile)</h1>
            <p>Manage the riders and couriers who collect parcels from sellers for {{ $provider->name }}. Change a rider's assignment type below, or open All riders to assign a delivery rider to pickups.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('logistics.riders', ['status' => 'approved']) }}" class="seller-button seller-button--secondary">All riders &amp; assignment types</a>
        </div>
    </section>

    <section class="seller-panel">
        <nav class="workspace-tabs" aria-label="Filter couriers">
            @foreach($statusLabels as $key => $label)
                <a href="{{ route('logistics.couriers', ['status' => $key]) }}" class="workspace-tab{{ $status === $key ? ' is-active' : '' }}" @if($status === $key) aria-current="true" @endif>
                    {{ $label }} <span>{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead>
                    <tr>
                        <th>Courier</th>
                        <th>Vehicle</th>
                        <th>Assignment type</th>
                        <th>Driver's license</th>
                        <th>{{ $status === 'pending' ? 'Applied' : 'Reviewed' }}</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($couriers as $courier)
                        <tr>
                            <td>
                                <strong>{{ $courier->user->name }}</strong>
                                <span class="seller-cell-note">{{ $courier->user->email }} · {{ $courier->user->phone ?: 'no phone' }}</span>
                                @if($courier->user->date_of_birth)
                                    <span class="seller-cell-note">Age {{ \Illuminate\Support\Carbon::parse($courier->user->date_of_birth)->age }}</span>
                                @endif
                                @if($courier->user->email_verified_at === null)
                                    <span class="seller-cell-note">Email not verified yet</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $courier->vehicle_type ?: '—' }}</strong>
                                <span class="seller-cell-note">{{ $courier->plate_no ?: 'No plate' }}</span>
                                @if($courier->status === 'approved' && ! $courier->is_active)
                                    <span class="seller-cell-note rider-paused"><i class="fas fa-pause" aria-hidden="true"></i> Paused: no new pickups</span>
                                @endif
                            </td>
                            <td>@include('logistics.partials.rider-type', ['rider' => $courier])</td>
                            <td>
                                <strong>{{ $courier->license_number ?: '—' }}</strong>
                                @if($courier->license_expires_on)
                                    <span class="seller-cell-note{{ $courier->license_expired ? ' rider-license--expired' : '' }}">
                                        {{ $courier->license_expired ? 'Expired' : 'Expires' }} {{ $courier->license_expires_on->format('M j, Y') }}
                                    </span>
                                @endif
                                @php $files = collect(\App\Models\Rider::DOCUMENTS)->filter(fn ($label, $field) => $courier->{$field.'_path'}); @endphp
                                @if($files->isNotEmpty())
                                    <span class="rider-documents" data-document-gallery>
                                        @foreach($files as $field => $label)
                                            <a href="{{ route('logistics.riders.document', [$courier, $field]) }}" target="_blank" rel="noopener" class="workspace-link" data-document-viewer data-document-title="{{ $label }} · {{ $courier->user?->name }}" data-document-type="{{ \App\Support\DocumentPreview::type($courier->{$field.'_path'}) }}"><i class="fas fa-file-lines" aria-hidden="true"></i> {{ $label }}</a>
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td>
                                {{ ($status === 'pending' ? $courier->created_at : ($courier->reviewed_at ?? $courier->updated_at))?->timezone('Asia/Manila')->format('M j, Y') }}
                                @if($courier->rejection_reason && in_array($status, ['rejected', 'suspended'], true))
                                    <span class="seller-cell-note">{{ $courier->rejection_reason }}</span>
                                @endif
                            </td>
                            <td>@include('logistics.partials.rider-actions', ['rider' => $courier])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="seller-empty">
                                    <i class="fas fa-truck-pickup" aria-hidden="true"></i>
                                    <strong>No {{ Str::lower($statusLabels[$status]) }} couriers</strong>
                                    @if($status === 'pending')
                                        <span>Couriers choose {{ $provider->name }} when they sign up at "Become a courier".</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($couriers->hasPages())
            <div class="seller-pagination">{{ $couriers->links() }}</div>
        @endif
    </section>
@endsection

