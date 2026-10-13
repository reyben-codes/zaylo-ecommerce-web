@extends('sorting-center.layout')

@section('page-title', 'Seller pickup requests')

@section('module-content')
    @php
        $pickupSamples = [
            ['seller' => 'Northline Goods', 'reference' => 'PU-10482', 'parcels' => 8, 'area' => 'Makati City', 'window' => 'Today · 10:00–12:00', 'status' => 'Awaiting confirmation'],
            ['seller' => 'Cedar & Co.', 'reference' => 'PU-10479', 'parcels' => 3, 'area' => 'Quezon City', 'window' => 'Today · 13:00–15:00', 'status' => 'Confirmed'],
            ['seller' => 'Everyday Studio', 'reference' => 'PU-10475', 'parcels' => 12, 'area' => 'Pasig City', 'window' => 'Tomorrow · 09:00–11:00', 'status' => 'Awaiting approval'],
            ['seller' => 'Sunday Supply', 'reference' => 'PU-10471', 'parcels' => 5, 'area' => 'Taguig City', 'window' => 'Tomorrow · 14:00–16:00', 'status' => 'Approved'],
        ];
    @endphp
    <div class="sc-page-heading"><div><div class="sc-eyebrow">Seller handover</div><h1>Pickup requests</h1><p>Review the seller-to-center parcel handover queue.</p></div></div>
    <div class="sc-demo-notice" data-demo-feedback hidden><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Preview status changed in this browser only. Pickup requests are sample data and have not been saved.</span></div>
    <div class="sc-demo-notice"><i class="fa-solid fa-flask" aria-hidden="true"></i><span><strong>Sample data:</strong> this project does not have a pickup-request data model or backend workflow. Buttons below demonstrate the interface only and do not confirm or verify a real pickup.</span></div>
    <section class="sc-metrics" aria-label="Sample pickup summary">
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span><span class="sc-metric-label">Requests today</span><strong class="sc-metric-value">06</strong><div class="sc-metric-note">Sample dashboard count</div></article>
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span><span class="sc-metric-label">Awaiting confirmation</span><strong class="sc-metric-value">03</strong><div class="sc-metric-note">Sample dashboard count</div></article>
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></span><span class="sc-metric-label">Parcels to collect</span><strong class="sc-metric-value">23</strong><div class="sc-metric-note">Sample dashboard count</div></article>
        <article class="sc-card sc-metric"><span class="sc-metric-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span class="sc-metric-label">Verified handovers</span><strong class="sc-metric-value">04</strong><div class="sc-metric-note">Sample dashboard count</div></article>
    </section>
    <section class="sc-card">
        <div class="sc-card-header"><div><h2>Pickup schedule</h2><p>All listed requests are illustrative examples.</p></div></div>
        <div class="sc-table-wrap"><table class="sc-table">
            <thead><tr><th>Request</th><th>Seller</th><th>Parcels</th><th>Pickup area</th><th>Time window</th><th>Status</th><th>Demo actions</th></tr></thead>
            <tbody>
                @foreach ($pickupSamples as $pickup)
                    <tr data-demo-row>
                        <td><strong>{{ $pickup['reference'] }}</strong><small>Sample request</small></td><td>{{ $pickup['seller'] }}</td><td>{{ $pickup['parcels'] }}</td><td>{{ $pickup['area'] }}</td><td>{{ $pickup['window'] }}</td>
                        <td><span data-demo-status @class(['sc-badge', 'sc-badge-warning' => str_starts_with($pickup['status'], 'Awaiting'), 'sc-badge-info' => in_array($pickup['status'], ['Confirmed', 'Approved'], true)])>{{ $pickup['status'] }}</span></td>
                        <td>
                            <div data-demo-actions>
                                @if ($pickup['status'] === 'Awaiting confirmation')
                                    <button class="sc-button sc-button-small" type="button" data-demo-action="confirmed">Confirm</button>
                                @elseif ($pickup['status'] === 'Awaiting approval')
                                    <button class="sc-button sc-button-small" type="button" data-demo-action="approved">Approve</button>
                                @elseif ($pickup['status'] === 'Confirmed')
                                    <button class="sc-button sc-button-small" type="button" data-demo-action="approved">Approve</button>
                                @else
                                    <button class="sc-button sc-button-secondary sc-button-small" type="button" data-demo-action="verified">Verify pickup</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>
@endsection
