{{-- Approved riders with their current load. Expects $workload (rows from riderWorkload), $title, $note. --}}
<section class="seller-panel">
    <header class="seller-panel__header">
        <div><h2>{{ $title }}</h2><p>{{ $note }}</p></div>
        <a href="{{ route('logistics.riders', ['status' => 'approved']) }}" class="workspace-link">Manage riders <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </header>
    <div class="seller-table-wrap">
        <table class="seller-table workspace-table">
            <thead>
                <tr>
                    <th>Rider</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Out for delivery</th>
                    <th>Delivered today</th>
                    <th>Failed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($workload as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['rider']->user->name }}</strong>
                            <span class="seller-cell-note">{{ $row['rider']->vehicle_type ?: 'Vehicle not set' }}{{ $row['rider']->plate_no ? ' · '.$row['rider']->plate_no : '' }}</span>
                        </td>
                        <td>
                            @if(! $row['rider']->is_active)
                                <span class="seller-cell-note rider-paused"><i class="fas fa-pause" aria-hidden="true"></i> Paused</span>
                            @elseif($row['assigned'] + $row['out'])
                                <span class="seller-cell-note">On delivery</span>
                            @else
                                <span class="seller-cell-note">Available</span>
                            @endif
                        </td>
                        <td><span class="rider-load{{ $row['assigned'] ? ' rider-load--busy' : '' }}">{{ $row['assigned'] }}</span></td>
                        <td><span class="rider-load{{ $row['out'] ? ' rider-load--busy' : '' }}">{{ $row['out'] }}</span></td>
                        <td><span class="rider-load">{{ $row['delivered'] }}</span></td>
                        <td><span class="rider-load{{ $row['failed'] ? ' rider-load--warn' : '' }}">{{ $row['failed'] }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="seller-empty">
                                <i class="fas fa-motorcycle" aria-hidden="true"></i>
                                <strong>No approved riders yet</strong>
                                <span>Approve rider applications under Riders.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
