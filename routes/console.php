<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sign-ups nobody finished within a day are removed, with their uploads (also pruned whenever someone signs up).
Artisan::command('registrations:prune', function (App\Services\PendingRegistrationService $registrations) {
    $this->info($registrations->prune().' unfinished sign-ups removed.');
})->purpose('Remove sign-ups whose email code was never entered');
Schedule::command('registrations:prune')->hourly();

// Delivered orders the buyer has not confirmed complete on their own after the grace period (also run whenever
// buyers or sellers open their orders, since the host may not run the scheduler).
Artisan::command('orders:confirm-delivered', function (App\Services\OrderReceipts $receipts) {
    $this->info($receipts->autoConfirmDue().' delivered orders confirmed automatically.');
})->purpose('Complete delivered orders whose buyers did not confirm them in time');
Schedule::command('orders:confirm-delivered')->hourly();

// Activity log entries older than config('activity.retention_days') are removed.
Schedule::command('model:prune', ['--model' => [App\Models\ActivityLog::class]])->daily();
