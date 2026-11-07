<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\RiderAttendance;
use App\Models\User;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rider time in and time out. The logistics partner shows an attendance QR code at its hub that changes every
 * WINDOW_SECONDS, so a photo of it stops working within minutes; the rider scans it in the app once to time in
 * and again to time out. Each code is signed for one partner, so it only works for that partner's riders.
 */
class RiderAttendanceClock
{
    public const TIMEZONE = 'Asia/Manila';

    public const CODE_PREFIX = 'ZAYLO-ATT';

    /** How long each code is shown before the hub screen loads the next one. */
    public const WINDOW_SECONDS = 30;

    /** Earlier codes still accepted, for a slow scan or a phone clock that is a little off (about two minutes). */
    private const GRACE_WINDOWS = 3;

    /** A second scan this soon after timing in is the same code read twice, not a time out. */
    private const MIN_SHIFT_MINUTES = 1;

    /** An open time in older than this is a forgotten time out; the next scan starts a new day instead. */
    public const MAX_SHIFT_HOURS = 18;

    public const STATUS_LABELS = [
        'on_shift' => 'On shift',
        'timed_out' => 'Timed out',
        'not_in_yet' => 'Not in yet',
        'absent' => 'Absent',
        'day_off' => 'Day off',
        'no_schedule' => 'No schedule',
    ];

    /** The code the hub screen shows right now, as text and as an SVG data URI, with when it changes. */
    public function current(LogisticsProvider $provider): array
    {
        $window = $this->window();
        $code = $this->code($provider, $window);

        return [
            'code' => $code,
            'image' => (new QRCode(new QROptions([
                'outputType' => QROutputInterface::MARKUP_SVG,
                'eccLevel' => EccLevel::M,
                'quietzoneSize' => 2,
                'outputBase64' => true,
            ])))->render($code),
            'refresh_in' => ($window + 1) * self::WINDOW_SECONDS - now()->getTimestamp(),
        ];
    }

    /** True when [$code] looks like an attendance code, so a waybill scanned by mistake gets a clear message. */
    public static function isAttendanceCode(string $code): bool
    {
        return str_starts_with(trim($code), self::CODE_PREFIX.'.');
    }

    /**
     * Times the rider in, or out when they already timed in. Returns the attendance and 'in' or 'out'.
     *
     * @return array{0: RiderAttendance, 1: string}
     */
    public function scan(User $user, string $code): array
    {
        $rider = $user->rider;
        $this->verify($rider, trim($code));
        $now = now();

        try {
            return DB::transaction(function () use ($rider, $now) {
                $open = $rider->attendances()->whereNull('time_out_at')
                    ->where('time_in_at', '>=', $now->copy()->subHours(self::MAX_SHIFT_HOURS))
                    ->latest('time_in_at')->lockForUpdate()->first();
                if ($open) {
                    if ($open->time_in_at->diffInMinutes($now) < self::MIN_SHIFT_MINUTES) {
                        throw ValidationException::withMessages(['code' => 'You just timed in. Scan again when your shift ends to time out.']);
                    }
                    $open->update(['time_out_at' => $now]);

                    return [$open, 'out'];
                }

                $today = $now->copy()->timezone(self::TIMEZONE)->toDateString();
                if ($rider->attendances()->whereDate('work_date', $today)->exists()) {
                    throw ValidationException::withMessages(['code' => 'You already timed in and out today.']);
                }

                return [$rider->attendances()->create([
                    'logistics_provider_id' => $rider->logistics_provider_id,
                    'work_date' => $today,
                    'time_in_at' => $now,
                    'scheduled_start' => $rider->has_schedule ? $rider->shift_start : null,
                    'scheduled_end' => $rider->has_schedule ? $rider->shift_end : null,
                ]), 'in'];
            });
        } catch (QueryException) {
            // Two scans at the same moment: the unique day index lets only one time in through.
            throw ValidationException::withMessages(['code' => 'Your time in was already recorded. Pull down to refresh.']);
        }
    }

    /**
     * Riders on shift right now: timed in (within MAX_SHIFT_HOURS, like the scanner) and not timed out yet. Only they
     * can be given parcels or return pickups.
     */
    public static function whereOnShift(Builder $riders): Builder
    {
        return $riders->whereHas('attendances', fn (Builder $attendance) => $attendance
            ->whereNull('time_out_at')
            ->where('time_in_at', '>=', now()->subHours(self::MAX_SHIFT_HOURS)));
    }

    /**
     * Where a rider stands on [$date]: a status key from STATUS_LABELS plus late and left-early flags.
     *
     * @return array{status: string, label: string, late: bool, left_early: bool}
     */
    public function status(Rider $rider, Carbon $date, ?RiderAttendance $attendance): array
    {
        $late = $leftEarly = false;
        if ($attendance) {
            $status = $attendance->time_out_at ? 'timed_out' : 'on_shift';
            [$start, $end] = $this->shiftBounds($attendance->work_date, $attendance->scheduled_start, $attendance->scheduled_end);
            $late = $start !== null && $attendance->time_in_at->gt($start);
            $leftEarly = $end !== null && $attendance->time_out_at?->lt($end);
        } elseif (! $rider->has_schedule) {
            $status = 'no_schedule';
        } elseif (! $rider->worksOn($date)) {
            $status = 'day_off';
        } else {
            [$start] = $this->shiftBounds($date, $rider->shift_start, $rider->shift_end);
            $status = now()->lt($start) ? 'not_in_yet' : 'absent';
        }

        return ['status' => $status, 'label' => self::STATUS_LABELS[$status], 'late' => $late, 'left_early' => (bool) $leftEarly];
    }

    /** The rider's day for the app: their schedule, today's time in and out, and what the next scan does. */
    public function riderPayload(Rider $rider): array
    {
        $today = now(self::TIMEZONE)->startOfDay();
        $attendance = $rider->attendances()->whereNull('time_out_at')
            ->where('time_in_at', '>=', now()->subHours(self::MAX_SHIFT_HOURS))->latest('time_in_at')->first()
            ?? $rider->attendances()->whereDate('work_date', $today->toDateString())->first();
        $status = $this->status($rider, $today, $attendance);

        return [
            'schedule' => $rider->schedule_label,
            'works_today' => $rider->worksOn($today),
            'shift' => $rider->has_schedule ? Rider::shiftLabel($rider->shift_start, $rider->shift_end) : null,
            'time_in' => $attendance?->time_in_at->toIso8601String(),
            'time_out' => $attendance?->time_out_at?->toIso8601String(),
            'late' => $status['late'],
            'status' => $status['status'],
            'status_label' => $status['label'],
            'next_action' => $attendance === null ? 'time_in' : ($attendance->time_out_at === null ? 'time_out' : null),
        ];
    }

    /** Scheduled start and end as moments, the end on the next day for a shift that runs past midnight. */
    private function shiftBounds(Carbon $date, ?string $start, ?string $end): array
    {
        if ($start === null || $end === null) {
            return [null, null];
        }
        $day = Carbon::parse($date->toDateString(), self::TIMEZONE);
        $startsAt = $day->copy()->setTimeFromTimeString($start);
        $endsAt = $day->copy()->setTimeFromTimeString($end);

        return [$startsAt, $endsAt->lte($startsAt) ? $endsAt->addDay() : $endsAt];
    }

    private function verify(Rider $rider, string $code): void
    {
        $parts = explode('.', $code);
        if (count($parts) !== 4 || $parts[0] !== self::CODE_PREFIX || ! ctype_digit($parts[1]) || ! ctype_digit($parts[2])) {
            throw ValidationException::withMessages(['code' => 'That is not an attendance code. Scan the QR code on your logistics hub\'s attendance screen.']);
        }
        [, $providerId, $window] = $parts;
        $provider = LogisticsProvider::find((int) $providerId);
        if (! $provider || ! hash_equals($this->code($provider, (int) $window), $code)) {
            throw ValidationException::withMessages(['code' => 'That attendance code is not valid. Scan the code on the hub screen again.']);
        }
        if ((int) $provider->id !== (int) $rider->logistics_provider_id) {
            throw ValidationException::withMessages(['code' => 'That attendance code belongs to another logistics partner.']);
        }
        $age = $this->window() - (int) $window;
        if ($age < 0 || $age > self::GRACE_WINDOWS) {
            throw ValidationException::withMessages(['code' => 'That attendance code has expired. Scan the code on the hub screen again.']);
        }
    }

    private function code(LogisticsProvider $provider, int $window): string
    {
        $signature = substr(hash_hmac('sha256', "rider-attendance|{$provider->id}|{$window}", (string) config('app.key')), 0, 24);

        return implode('.', [self::CODE_PREFIX, $provider->id, $window, $signature]);
    }

    private function window(): int
    {
        return intdiv(now()->getTimestamp(), self::WINDOW_SECONDS);
    }
}
