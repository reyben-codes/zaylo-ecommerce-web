<?php

use App\Models\RiderApplication;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Rider application review, for logistics staff until a review screen exists.
|   php artisan riders:applications            pending applications
|   php artisan riders:applications --all      every application
|   php artisan riders:review 7 --approve --by=staff@example.com
|   php artisan riders:review 7 --reject="License photo is unreadable." --by=staff@example.com
*/
Artisan::command('riders:applications {--all : Include approved and rejected applications}', function () {
    $applications = RiderApplication::with('user')
        ->when(! $this->option('all'), fn ($query) => $query->pending())
        ->orderBy('created_at')
        ->get();

    if ($applications->isEmpty()) {
        $this->info($this->option('all') ? 'No rider applications yet.' : 'No pending rider applications.');

        return;
    }

    $this->table(
        ['ID', 'Status', 'Name', 'Email', 'Mobile', 'License', 'Expires', 'Vehicle', 'Plate', 'Submitted'],
        $applications->map(fn (RiderApplication $application) => [
            $application->id,
            $application->status,
            $application->user->name,
            $application->user->email,
            $application->user->phone,
            $application->license_number,
            $application->license_expires_on?->toDateString(),
            RiderApplication::VEHICLE_TYPES[$application->vehicle_type] ?? $application->vehicle_type,
            $application->plate_number,
            $application->created_at?->diffForHumans(),
        ]),
    );
    $this->line('Documents are on the private disk: storage/app/private/rider-documents/{user id}/');
})->purpose('List rider applications waiting for logistics review');

Artisan::command('riders:review {application : Rider application ID}
        {--approve : Approve the application}
        {--reject= : Reject the application with this reason}
        {--by= : Email of the logistics staff member reviewing it}', function () {
    $application = RiderApplication::with('user')->find($this->argument('application'));
    if (! $application) {
        $this->error('Rider application not found.');

        return 1;
    }

    $approve = (bool) $this->option('approve');
    $reason = $this->option('reject');
    if ($approve === filled($reason)) {
        $this->error('Use exactly one of --approve or --reject="reason".');

        return 1;
    }

    $reviewer = null;
    if ($this->option('by')) {
        $reviewer = User::where('email', $this->option('by'))->first();
        if (! $reviewer) {
            $this->error('No user with email '.$this->option('by').'.');

            return 1;
        }
    }

    try {
        $approve ? $application->approve($reviewer) : $application->reject($reason, $reviewer);
    } catch (LogicException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info(sprintf(
        'Application #%d for %s (%s) was %s.',
        $application->id,
        $application->user->name,
        $application->user->email,
        $application->status,
    ));
})->purpose('Approve or reject a rider application');
