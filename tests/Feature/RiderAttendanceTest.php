<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Services\RiderAttendanceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Riders time in and out by scanning the attendance QR code their logistics partner shows at the hub, and the
 * partner sets each rider's schedule and sees who came in, late or not at all.
 */
class RiderAttendanceTest extends TestCase
{
    use RefreshDatabase;
    use SignsInMobile;

    private LogisticsProvider $partner;

    private RiderAttendanceClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $this->partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $this->clock = app(RiderAttendanceClock::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rider(LogisticsProvider $partner, string $name = 'Jun Reyes', array $schedule = []): User
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => $name]);
        Rider::create([
            'user_id' => $user->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle',
            'status' => 'approved', 'is_active' => true,
        ] + $schedule);

        return $user;
    }

    private function at(string $manilaTime): void
    {
        Carbon::setTestNow(Carbon::parse($manilaTime, RiderAttendanceClock::TIMEZONE));
    }

    private function scan(User $rider, ?string $code = null)
    {
        return $this->signInMobile($rider)->postJson(route('mobile.rider.attendance.scan'), [
            'code' => $code ?? $this->clock->current($this->partner)['code'],
        ]);
    }

    public function test_scanning_the_hub_code_times_the_rider_in_then_out(): void
    {
        $rider = $this->rider($this->partner, schedule: ['shift_start' => '08:00', 'shift_end' => '17:00', 'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri']]);

        $this->at('2026-11-02 08:10'); // a Monday, ten minutes late
        $this->scan($rider)->assertOk()
            ->assertJsonPath('action', 'in')
            ->assertJsonPath('message', 'Timed in at 8:10 AM. Have a safe shift!')
            ->assertJsonPath('attendance.next_action', 'time_out')
            ->assertJsonPath('attendance.late', true)
            ->assertJsonPath('attendance.schedule', 'Mon–Fri · 8:00 AM – 5:00 PM');

        $this->at('2026-11-02 08:10:30');
        $this->scan($rider)->assertStatus(422)->assertJsonValidationErrors(['code' => 'You just timed in']);

        $this->at('2026-11-02 17:05');
        $this->scan($rider)->assertOk()->assertJsonPath('action', 'out')->assertJsonPath('attendance.next_action', null);

        $this->at('2026-11-02 18:00');
        $this->scan($rider)->assertStatus(422)->assertJsonValidationErrors(['code' => 'already timed in and out today']);

        $this->signInMobile($rider)->getJson(route('mobile.rider.dashboard'))->assertOk()
            ->assertJsonPath('attendance.status', 'timed_out')
            ->assertJsonPath('attendance.shift', '8:00 AM – 5:00 PM');
    }

    public function test_time_in_and_time_out_are_kept_in_the_activity_log(): void
    {
        $rider = $this->rider($this->partner, schedule: ['shift_start' => '08:00', 'shift_end' => '17:00', 'work_days' => ['mon', 'tue', 'wed', 'thu', 'fri']]);

        $this->at('2026-11-02 08:10');
        $this->scan($rider)->assertOk();
        $this->at('2026-11-02 16:30');
        $this->scan($rider)->assertOk();

        $logs = \App\Models\ActivityLog::where('category', 'attendance')->orderBy('id')->get();
        $this->assertSame(['rider.time_in', 'rider.time_out'], $logs->pluck('event')->all());
        $this->assertSame(['Timed in at 8:10 AM (late)', 'Timed out at 4:30 PM (left early)'], $logs->pluck('description')->all());
        $this->assertSame($rider->id, $logs[0]->user_id);
        $this->assertSame($this->partner->id, (int) $logs[0]->logistics_provider_id);
        $this->assertSame(['Scheduled shift' => '8:00 AM – 5:00 PM'], $logs[0]->details());

        // The rider's logistics partner sees them on its activity log.
        $this->actingAs($this->partner->owner)->get(route('logistics.activity'))->assertOk()
            ->assertSee('Timed in at 8:10 AM (late)')->assertSee('Timed out at 4:30 PM (left early)')->assertSee('Rider attendance');
    }

    public function test_a_night_shift_times_out_after_midnight_on_the_same_day(): void
    {
        $rider = $this->rider($this->partner);

        $this->at('2026-11-02 22:00');
        $this->scan($rider)->assertOk()->assertJsonPath('action', 'in');
        $this->at('2026-11-03 06:00');
        $this->scan($rider)->assertOk()->assertJsonPath('action', 'out');

        $attendance = $rider->rider->attendances()->sole();
        $this->assertSame('2026-11-02', $attendance->work_date->toDateString());
        $this->assertNotNull($attendance->time_out_at);
    }

    public function test_old_forged_and_other_partner_codes_are_refused(): void
    {
        $rider = $this->rider($this->partner);
        $this->at('2026-11-02 08:00');
        $old = $this->clock->current($this->partner)['code'];

        $this->at('2026-11-02 08:05');
        $this->scan($rider, $old)->assertStatus(422)->assertJsonValidationErrors(['code' => 'expired']);
        $this->scan($rider, preg_replace('/\.[0-9a-f]+$/', '.000000000000000000000000', $this->clock->current($this->partner)['code']))
            ->assertStatus(422)->assertJsonValidationErrors(['code' => 'not valid']);
        $this->scan($rider, 'TRK-123')->assertStatus(422)->assertJsonValidationErrors(['code' => 'not an attendance code']);

        $otherOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $other = LogisticsProvider::create(['user_id' => $otherOwner->id, 'name' => 'Other', 'slug' => 'other', 'status' => 'approved']);
        $this->scan($rider, $this->clock->current($other)['code'])->assertStatus(422)->assertJsonValidationErrors(['code' => 'another logistics partner']);

        $this->assertSame(0, $rider->rider->attendances()->count());
    }

    public function test_the_partner_sets_schedules_and_sees_the_days_attendance(): void
    {
        $jun = $this->rider($this->partner, 'Jun Reyes');
        $ana = $this->rider($this->partner, 'Ana Cruz', ['shift_start' => '07:00', 'shift_end' => '15:00', 'work_days' => ['mon']]);
        $owner = $this->partner->owner;

        $this->actingAs($owner)->patch(route('logistics.riders.schedule', $jun->rider), [
            'shift_start' => '08:00', 'shift_end' => '17:00', 'work_days' => ['sat', 'mon', 'tue', 'wed'],
        ])->assertRedirect()->assertSessionHas('status', "Jun Reyes's schedule is now Mon–Wed, Sat · 8:00 AM – 5:00 PM.");
        $this->assertSame(['mon', 'tue', 'wed', 'sat'], $jun->rider->fresh()->work_days);

        $this->actingAs($owner)->patch(route('logistics.riders.schedule', $jun->rider), ['shift_start' => '08:00', 'shift_end' => '08:00', 'work_days' => []])
            ->assertSessionHasErrorsIn('schedule'.$jun->rider->id, ['shift_end', 'work_days']);

        $this->at('2026-11-02 08:30'); // Monday: Jun timed in late, Ana never came
        $this->scan($jun)->assertOk();

        $this->actingAs($owner)->get(route('logistics.attendance'))->assertOk()
            ->assertSee('Attendance QR code')
            ->assertSee('8:30 AM')
            ->assertSee('Late · scheduled 8:00 AM')
            ->assertSeeInOrder(['Ana Cruz', 'Absent', 'Jun Reyes', 'On shift']);

        $this->actingAs($owner)->getJson(route('logistics.attendance.code'))->assertOk()->assertJsonStructure(['code', 'image', 'refresh_in']);

        // Past days have no QR code to show.
        $this->actingAs($owner)->get(route('logistics.attendance', ['date' => '2026-11-01']))->assertOk()->assertDontSee('Attendance QR code');

        $this->actingAs($owner)->patch(route('logistics.riders.schedule', $ana->rider), ['clear' => 1])->assertRedirect();
        $this->assertFalse($ana->rider->fresh()->has_schedule);
    }

    public function test_partners_cannot_edit_other_partners_riders(): void
    {
        $otherOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $other = LogisticsProvider::create(['user_id' => $otherOwner->id, 'name' => 'Other', 'slug' => 'other', 'status' => 'approved']);
        $rider = $this->rider($other);

        $this->actingAs($this->partner->owner)->patch(route('logistics.riders.schedule', $rider->rider), [
            'shift_start' => '08:00', 'shift_end' => '17:00', 'work_days' => ['mon'],
        ])->assertForbidden();
        $this->actingAs($this->partner->owner)->get(route('logistics.attendance'))->assertOk()->assertDontSee($rider->name);
    }

    public function test_riders_see_their_own_attendance_and_current_schedule_with_the_original_shift(): void
    {
        $this->at('2026-11-02 08:10');
        $user = $this->rider($this->partner, schedule: ['shift_start' => '08:00', 'shift_end' => '17:00', 'work_days' => ['mon']]);
        $this->scan($user)->assertOk();
        $this->at('2026-11-02 16:30');
        $this->scan($user)->assertOk();
        $other = $this->rider($this->partner, 'Another Rider');
        $this->scan($other)->assertOk();
        $user->rider->update(['shift_start' => '09:00', 'shift_end' => '18:00', 'work_days' => ['tue']]);

        $this->signInMobile($user)->getJson(route('mobile.rider.attendance'))->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('schedule.label', $user->rider->schedule_label)
            ->assertJsonPath('schedule.provider', $this->partner->name)
            ->assertJsonPath('schedule.days.0.working', false)
            ->assertJsonPath('schedule.days.1.working', true)
            ->assertJsonPath('records.0.date', '2026-11-02')
            ->assertJsonPath('records.0.shift', Rider::shiftLabel('08:00', '17:00'))
            ->assertJsonPath('records.0.late', true)
            ->assertJsonPath('records.0.left_early', true)
            ->assertJsonPath('records.0.status_label', 'Timed out')
            ->assertJsonPath('next_page', null);
    }

    public function test_attendance_history_filters_paginates_and_marks_forgotten_time_outs(): void
    {
        $this->at('2026-11-02 12:00');
        $user = $this->rider($this->partner);
        for ($day = 1; $day <= 25; $day++) {
            $user->rider->attendances()->create([
                'logistics_provider_id' => $this->partner->id,
                'work_date' => sprintf('2026-10-%02d', $day),
                'time_in_at' => sprintf('2026-10-%02d 00:00:00', $day),
            ]);
        }
        $this->signInMobile($user)->getJson(route('mobile.rider.attendance'))->assertOk()
            ->assertJsonCount(20, 'records')->assertJsonPath('next_page', 2)
            ->assertJsonPath('records.0.date', '2026-10-25')
            ->assertJsonPath('records.0.status_label', 'Missing time out')
            ->assertJsonPath('schedule.label', null);
        $this->getJson(route('mobile.rider.attendance', ['page' => 2]))->assertOk()
            ->assertJsonCount(5, 'records')->assertJsonPath('next_page', null);
        $this->getJson(route('mobile.rider.attendance', ['from' => '2026-10-10', 'to' => '2026-10-12']))->assertOk()
            ->assertJsonCount(3, 'records')->assertJsonPath('records.0.date', '2026-10-12');
        $this->getJson(route('mobile.rider.attendance', ['from' => '2026-10-12', 'to' => '2026-10-10']))
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }
}
