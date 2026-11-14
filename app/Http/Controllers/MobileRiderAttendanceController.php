<?php

namespace App\Http\Controllers;

use App\Models\Rider;
use App\Models\RiderAttendance;
use App\Services\RiderAttendanceClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileRiderAttendanceController extends Controller
{
    public function index(Request $request, RiderAttendanceClock $clock): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $rider = $request->user()->rider;
        $query = $rider->attendances();
        if (! empty($data['from'])) {
            $query->whereDate('work_date', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->whereDate('work_date', '<=', $data['to']);
        }
        $page = $query->orderByDesc('work_date')->orderByDesc('id')->paginate(20);

        return response()->json([
            'timezone' => RiderAttendanceClock::TIMEZONE,
            'today' => $clock->riderPayload($rider),
            'schedule' => [
                'label' => $rider->schedule_label,
                'provider' => $rider->provider?->name,
                'days' => collect(Rider::WORK_DAYS)->map(fn ($label, $key) => [
                    'label' => $label,
                    'working' => $rider->has_schedule && in_array($key, $rider->work_days, true),
                ])->values(),
            ],
            'records' => collect($page->items())->map(function (RiderAttendance $attendance) use ($clock, $rider) {
                $status = $clock->status($rider, $attendance->work_date, $attendance);
                // An old unclosed shift is not an active shift today.
                $openExpired = $attendance->time_out_at === null
                    && $attendance->time_in_at->lt(now()->subHours(RiderAttendanceClock::MAX_SHIFT_HOURS));

                return [
                    'id' => $attendance->id,
                    'date' => $attendance->work_date->toDateString(),
                    'shift' => $attendance->scheduled_start && $attendance->scheduled_end
                        ? Rider::shiftLabel($attendance->scheduled_start, $attendance->scheduled_end) : null,
                    'time_in' => $attendance->time_in_at->toIso8601String(),
                    'time_out' => $attendance->time_out_at?->toIso8601String(),
                    'status_label' => $openExpired ? 'Missing time out' : $status['label'],
                    'late' => $status['late'],
                    'left_early' => $status['left_early'],
                ];
            }),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
