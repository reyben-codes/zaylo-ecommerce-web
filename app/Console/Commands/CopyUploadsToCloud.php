<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Copies uploads saved on this computer (storage/app/public and storage/app/private) to the Cloudflare R2 buckets
 * the "public" and "local" disks use once the R2 settings are in .env. Files already there with the same size are
 * skipped, so it is safe to run again. Nothing is deleted.
 */
class CopyUploadsToCloud extends Command
{
    protected $signature = 'uploads:copy-to-r2
                            {--dry-run : List what would be copied without uploading anything}';

    protected $description = 'Copy uploads from storage/app to the Cloudflare R2 buckets';

    public function handle(): int
    {
        $folders = ['public' => storage_path('app/public'), 'local' => storage_path('app/private')];

        foreach (array_keys($folders) as $disk) {
            if (config("filesystems.disks.{$disk}.driver") !== 's3') {
                $this->error("The \"{$disk}\" disk is not on R2 yet. Add the R2_* settings to .env (see .env.example), then run this again.");

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $totals = ['copied' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($folders as $disk => $root) {
            if (! is_dir($root)) {
                continue;
            }
            $source = Storage::build(['driver' => 'local', 'root' => $root]);
            $target = Storage::disk($disk);
            $this->line("<info>{$disk}</info> ← {$root}");

            foreach ($source->allFiles() as $file) {
                if (basename($file) === '.gitignore') {
                    continue;
                }
                $result = $this->copy($source, $target, $file, $dryRun);
                $totals[$result]++;
                $this->line("  {$result}  {$file}");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Dry run: ' : '')."{$totals['copied']} copied, {$totals['skipped']} already there, {$totals['failed']} failed.");

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return 'copied'|'skipped'|'failed' */
    private function copy(Filesystem $source, Filesystem $target, string $file, bool $dryRun): string
    {
        if ($target->exists($file) && $target->size($file) === $source->size($file)) {
            return 'skipped';
        }
        if ($dryRun) {
            return 'copied';
        }

        $stream = $source->readStream($file);
        try {
            return $target->writeStream($file, $stream) ? 'copied' : 'failed';
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
