<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;

class CheckDeployment extends Command
{
    protected $signature = 'deployment:check';

    protected $description = 'Check production upload durability, shared scheduling cache and pending migrations without modifying data';

    public function handle(Migrator $migrator): int
    {
        $errors = [];
        if (filled(config('push.project_id')) || filled(config('push.credentials'))) {
            try {
                app(\App\Services\FirebaseMessaging::class)->validateConfiguration();
            } catch (\Throwable) {
                $errors[] = 'Firebase credential is unreadable, invalid or does not match FIREBASE_PROJECT_ID.';
            }
        }
        if (app()->environment('production')) {
            if (config('app.debug')) $errors[] = 'APP_DEBUG must be false.';
            foreach (['public', 'local'] as $name) {
                $disk = config('filesystems.disks.'.$name, []);
                if (($disk['driver'] ?? null) !== 's3') {
                    $errors[] = 'Configure durable R2 storage for the '.$name.' upload disk before deploying.';
                    continue;
                }
                foreach (['key', 'secret', 'bucket', 'endpoint'] as $field) {
                    if (blank($disk[$field] ?? null)) $errors[] = 'The '.$name.' upload disk is missing '.$field.'.';
                }
            }
            $public = config('filesystems.disks.public');
            $private = config('filesystems.disks.local');
            if (filled($public['bucket'] ?? null) && ($public['bucket'] ?? null) === ($private['bucket'] ?? null)) {
                $errors[] = 'Public and private uploads must use separate buckets.';
            }
            if (! filter_var($public['url'] ?? '', FILTER_VALIDATE_URL) || ! str_starts_with($public['url'] ?? '', 'https://')) {
                $errors[] = 'R2_PUBLIC_URL must be an HTTPS URL.';
            }
            $cache = config('cache.default');
            if (! in_array(config('cache.stores.'.$cache.'.driver'), ['database', 'redis'], true)) {
                $errors[] = 'Use a shared database or Redis cache for single-server scheduled tasks.';
            }
        }
        try {
            if (! $migrator->repositoryExists()) {
                $errors[] = 'Run database migrations before starting the application.';
            } else {
                $files = $migrator->getMigrationFiles(database_path('migrations'));
                if (array_diff(array_keys($files), $migrator->getRepository()->getRan())) {
                    $errors[] = 'Database migrations are pending. Run php artisan migrate --force before starting the application.';
                }
            }
        } catch (\Throwable $exception) {
            // Do not print connection strings or credentials in deployment logs.
            $errors[] = 'Could not check database migrations. Verify the database connection.';
        }
        foreach ($errors as $error) $this->error($error);
        if ($errors) return self::FAILURE;
        $this->info('Deployment configuration checks passed. Bucket access and backup restoration still require verification.');

        return self::SUCCESS;
    }
}
