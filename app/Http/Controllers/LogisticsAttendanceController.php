<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\RiderAttendance;
use App\Services\ActivityLogger;
use App\Services\RiderAttendanceClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Rider attendance for the logistics partner: the QR code riders scan at the hub to time in and out, each
 * approved rider's time in and time out for a day, and each rider's work schedule.
 */
class LogisticsAttendanceController extends Controller
{
    public function __construct(private readonly RiderAttendanceClock $clock) {}

    public function index(Request $request)
    {
        $provider = $this->provider();
        $today = now(RiderAttendanceClock::TIMEZONE)->startOfDay();
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString()]]);
        $date = isset($data['date']) ? Carbon::parse($data['date'], RiderAttendanceClock::TIMEZONE)->startOfDay() : $today;

        $riders = $provider->riders()->where('status', 'approved')->with('user')->get()
            ->sortBy(fn (Rider $rider) => mb_strtolower($rider->user?->name ?? ''))->values();
        $attendances = RiderAttendance::whereIn('rider_id', $riders->pluck('id'))
            ->whereDate('work_date', $date->toDateString())->get()->keyBy('rider_id');

        $rows = $riders->map(fn (Rider $rider) => [
            'rider' => $rider,
            'attendance' => $attendance = $attendances->get($rider->id),
            'status' => $this->clock->status($rider, $date, $attendance),
        ]);

        return view('logistics.attendance', [
            'provider' => $provider,
            'date' => $date,
            'isToday' => $date->equalTo($today),
            'rows' => $rows,
            'summary' => [
                'scheduled' => $rows->filter(fn ($row) => $row['rider']->worksOn($date) || $row['attendance'])->count(),
                'present' => $rows->filter(fn ($row) => $row['attendance'])->count(),
                'late' => $rows->filter(fn ($row) => $row['status']['late'])->count(),
                'absent' => $rows->filter(fn ($row) => $row['status']['status'] === 'absent')->count(),
            ],
            'statusLabels' => RiderAttendanceClock::STATUS_LABELS,
            'workDays' => Rider::WORK_DAYS,
            'qr' => $date->equalTo($today) ? $this->clock->current($provider) : null,
        ]);
    }

    /** The code the hub screen shows now; the page asks for the next one before this one changes. */
    public function code(): JsonResponse
    {
        return response()->json($this->clock->current($this->provider()));
    }

    public function updateSchedule(Request $request, Rider $rider)
    {
        abort_unless((int) $rider->logistics_provider_id === (int) $this->provider()->id, 403);
        abort_unless($rider->status === 'approved', 422, 'Only approved riders have a schedule.');

        // Each rider's form has its own error bag, so a mistake shows on that rider's form only.
        $data = $request->validateWithBag('schedule'.$rider->id, [
            'clear' => ['nullable', 'boolean'],
            'shift_start' => ['exclude_if:clear,1', 'required', 'date_format:H:i'],
            'shift_end' => ['exclude_if:clear,1', 'required', 'date_format:H:i', 'different:shift_start'],
            'work_days' => ['exclude_if:clear,1', 'required', 'array', 'min:1'],
            'work_days.*' => ['exclude_if:clear,1', Rule::in(array_keys(Rider::WORK_DAYS))],
        ], [
            'shift_start.required' => 'Enter when the shift starts.',
            'shift_end.required' => 'Enter when the shift ends.',
            'shift_end.different' => 'The shift has to end at a different time than it starts.',
            'work_days.required' => 'Pick at least one work day.',
            'work_days.min' => 'Pick at least one work day.',
        ]);

        $clear = $request->boolean('clear');
        $rider->update($clear
            ? ['shift_start' => null, 'shift_end' => null, 'work_days' => null]
            : [
                'shift_start' => $data['shift_start'],
                'shift_end' => $data['shift_end'],
                'work_days' => array_values(array_intersect(array_keys(Rider::WORK_DAYS), $data['work_days'])),
            ]);

        $name = $rider->user?->name ?? 'The rider';
        app(ActivityLogger::class)->record('rider.schedule', 'logistics', $clear
            ? "Cleared {$name}'s work schedule"
            : "Set {$name}'s work schedule to {$rider->schedule_label}", auth()->user(), $rider);

        return back()->with('status', $clear ? "{$name}'s schedule was cleared." : "{$name}'s schedule is now {$rider->schedule_label}.");
    }

    private function provider(): LogisticsProvider
    {
        $provider = auth()->user()->logisticsProvider;
        abort_unless($provider, 404, 'No logistics company is linked to this account.');

        return $provider;
    }
}
