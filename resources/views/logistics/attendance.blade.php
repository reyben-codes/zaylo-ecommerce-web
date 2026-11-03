@extends('layouts.workspace', ['workspace' => 'logistics'])

@section('title', 'Attendance')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rider-attendance.css') }}?v={{ filemtime(public_path('css/rider-attendance.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/rider-attendance.js') }}?v={{ filemtime(public_path('js/rider-attendance.js')) }}" defer></script>
@endpush

@php
    $tz = \App\Services\RiderAttendanceClock::TIMEZONE;
    $statusTone = ['on_shift' => 'active', 'timed_out' => 'active', 'not_in_yet' => 'pending', 'absent' => 'rejected', 'day_off' => 'none', 'no_schedule' => 'none'];
    $hours = function ($attendance) {
        if (! $attendance?->time_out_at) {
            return null;
        }
        $minutes = (int) $attendance->time_in_at->diffInMinutes($attendance->time_out_at);

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    };
@endphp

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $provider->name }}</p>
            <h1>Attendance</h1>
            <p>Riders time in and time out by scanning the attendance QR code in the ZAYLO rider app. Set each rider's work schedule here.</p>
        </div>
    </section>

    <div class="attendance-top{{ $qr ? '' : ' attendance-top--single' }}">
        @if($qr)
            <section class="seller-panel attendance-qr" aria-labelledby="attendance-qr-heading" data-attendance-qr data-code-url="{{ route('logistics.attendance.code') }}" data-refresh-in="{{ $qr['refresh_in'] }}">
                <header class="seller-panel__header">
                    <div>
                        <h2 id="attendance-qr-heading">Attendance QR code</h2>
                        <p>Show this at the hub. Riders tap <strong>Time in / Time out</strong> in the app and scan it.</p>
                    </div>
                    <button type="button" class="seller-button seller-button--small seller-button--secondary" data-attendance-fullscreen hidden>
                        <i class="fas fa-expand" aria-hidden="true"></i> <span>Full screen</span>
                    </button>
                </header>
                <div class="seller-panel__body attendance-qr__body">
                    <img src="{{ $qr['image'] }}" alt="Attendance QR code for {{ $provider->name }} riders" class="attendance-qr__image" width="240" height="240" data-attendance-qr-image>
                    <p class="attendance-qr__note" role="status" data-attendance-qr-status>The code changes every {{ \App\Services\RiderAttendanceClock::WINDOW_SECONDS }} seconds, so a photo of it stops working.</p>
                </div>
            </section>
        @endif

        <section class="seller-panel" aria-labelledby="attendance-summary-heading">
            <header class="seller-panel__header">
                <div>
                    <h2 id="attendance-summary-heading">{{ $isToday ? 'Today' : $date->format('l') }}, {{ $date->format('F j, Y') }}</h2>
                    <p>Approved riders and their time in and time out for the day.</p>
                </div>
            </header>
            <div class="seller-panel__body">
                <form method="GET" action="{{ route('logistics.attendance') }}" class="attendance-date">
                    <a href="{{ route('logistics.attendance', ['date' => $date->copy()->subDay()->toDateString()]) }}" class="seller-button seller-button--small seller-button--secondary" aria-label="Previous day"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
                    <label for="attendance-date" class="sr-only">Date</label>
                    <input id="attendance-date" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now($tz)->toDateString() }}" @error('date') aria-invalid="true" @enderror>
                    <button type="submit" class="seller-button seller-button--small">Show</button>
                    @unless($isToday)
                        <a href="{{ route('logistics.attendance', ['date' => $date->copy()->addDay()->toDateString()]) }}" class="seller-button seller-button--small seller-button--secondary" aria-label="Next day"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <a href="{{ route('logistics.attendance') }}" class="workspace-link">Back to today</a>
                    @endunless
                </form>
                @error('date')<p class="seller-field__error">{{ $message }}</p>@enderror

                <dl class="attendance-summary">
                    <div><dt>Scheduled</dt><dd>{{ $summary['scheduled'] }}</dd></div>
                    <div><dt>Timed in</dt><dd>{{ $summary['present'] }}</dd></div>
                    <div><dt>Late</dt><dd>{{ $summary['late'] }}</dd></div>
                    <div><dt>Absent</dt><dd>{{ $summary['absent'] }}</dd></div>
                </dl>
            </div>
        </section>
    </div>

    <section class="seller-panel" aria-label="Rider attendance">
        <div class="seller-table-wrap">
            <table class="seller-table workspace-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Schedule</th>
                        <th>Time in</th>
                        <th>Time out</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as ['rider' => $rider, 'attendance' => $attendance, 'status' => $status])
                        @php
                            $bag = $errors->getBag('schedule'.$rider->id);
                            $mine = (string) old('schedule_rider') === (string) $rider->id;
                            $days = $mine ? (array) old('work_days', []) : ($rider->work_days ?? []);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $rider->user?->name }}</strong>
                                <span class="seller-cell-note">{{ $rider->vehicle_type ?: 'No vehicle' }}{{ $rider->plate_no ? ' · '.$rider->plate_no : '' }}</span>
                            </td>
                            <td>
                                @if($rider->has_schedule)
                                    <strong>{{ \App\Models\Rider::shiftLabel($rider->shift_start, $rider->shift_end) }}</strong>
                                    <span class="seller-cell-note">{{ \App\Models\Rider::workDaysLabel($rider->work_days) }}</span>
                                @else
                                    <span class="seller-cell-note">No schedule yet</span>
                                @endif
                            </td>
                            <td>
                                {{ $attendance?->time_in_at->timezone($tz)->format('g:i A') ?? '—' }}
                                @if($status['late'])
                                    <span class="seller-cell-note attendance-flag">Late · scheduled {{ \Illuminate\Support\Carbon::createFromFormat('H:i', substr($attendance->scheduled_start, 0, 5))->format('g:i A') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($attendance?->time_out_at)
                                    {{ $attendance->time_out_at->timezone($tz)->format('g:i A') }}
                                    @if(! $attendance->time_out_at->timezone($tz)->isSameDay($attendance->time_in_at->timezone($tz)))
                                        <span class="seller-cell-note">{{ $attendance->time_out_at->timezone($tz)->format('M j') }}</span>
                                    @endif
                                    @if($status['left_early'])
                                        <span class="seller-cell-note attendance-flag">Left early</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $hours($attendance) ?? '—' }}</td>
                            <td><span class="role-pill attendance-status--{{ $statusTone[$status['status']] }}">{{ $status['label'] }}</span></td>
                            <td>
                                <details class="attendance-schedule" data-smooth-disclosure @if($bag->any()) open @endif>
                                    <summary class="seller-button seller-button--small seller-button--secondary">
                                        <i class="fas fa-calendar-days" aria-hidden="true"></i> Edit schedule
                                    </summary>
                                    <div data-disclosure-content>
                                        <form method="POST" action="{{ route('logistics.riders.schedule', $rider) }}" class="attendance-schedule__form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="schedule_rider" value="{{ $rider->id }}">
                                            <div class="attendance-schedule__times">
                                                <div>
                                                    <label for="shift-start-{{ $rider->id }}">Time in</label>
                                                    <input id="shift-start-{{ $rider->id }}" type="time" name="shift_start" required value="{{ $mine ? old('shift_start') : substr((string) $rider->shift_start, 0, 5) }}" @if($bag->has('shift_start')) aria-invalid="true" @endif>
                                                </div>
                                                <div>
                                                    <label for="shift-end-{{ $rider->id }}">Time out</label>
                                                    <input id="shift-end-{{ $rider->id }}" type="time" name="shift_end" required value="{{ $mine ? old('shift_end') : substr((string) $rider->shift_end, 0, 5) }}" @if($bag->has('shift_end')) aria-invalid="true" @endif>
                                                </div>
                                            </div>
                                            @foreach(['shift_start', 'shift_end'] as $field)
                                                @if($bag->has($field))<p class="seller-field__error">{{ $bag->first($field) }}</p>@endif
                                            @endforeach
                                            <fieldset class="attendance-schedule__days">
                                                <legend>Work days</legend>
                                                @foreach($workDays as $key => $label)
                                                    <label><input type="checkbox" name="work_days[]" value="{{ $key }}" @checked(in_array($key, $days, true))> {{ $label }}</label>
                                                @endforeach
                                            </fieldset>
                                            @if($bag->has('work_days') || $bag->has('work_days.*'))<p class="seller-field__error">{{ $bag->first('work_days') ?: $bag->first('work_days.*') }}</p>@endif
                                            <p class="seller-cell-note">A shift that ends earlier than it starts runs past midnight.</p>
                                            <div class="attendance-schedule__actions">
                                                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Save</button>
                                                @if($rider->has_schedule)
                                                    <button type="submit" name="clear" value="1" formnovalidate class="seller-button seller-button--small seller-button--secondary">Clear schedule</button>
                                                @endif
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="seller-empty">
                                    <i class="fas fa-user-clock" aria-hidden="true"></i>
                                    <strong>No approved riders yet</strong>
                                    <span>Approve riders on the <a href="{{ route('logistics.riders') }}" class="workspace-link">Riders</a> page to track their attendance.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
