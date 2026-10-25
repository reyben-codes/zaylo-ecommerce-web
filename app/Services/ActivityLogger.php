<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes activity log entries. Logging never gets in the way of the action itself:
 * without the table, or when writing fails, it does nothing (the failure is reported).
 */
class ActivityLogger
{
    private ?bool $ready = null;

    public function record(string $event, string $category, string $description, ?User $actor, ?Model $subject = null, array $properties = []): ?ActivityLog
    {
        if (! $this->ready()) {
            return null;
        }

        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            return ActivityLog::create([
                'user_id' => $actor?->getKey(),
                'actor_name' => $actor?->name,
                'actor_role' => $actor ? User::portalOf($actor->role) : null,
                'logistics_provider_id' => $actor ? $this->providerOf($actor) : null,
                'event' => Str::limit($event, 80, ''),
                'category' => Str::limit($category, 30, ''),
                'description' => Str::limit($description, 250),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? ActivityLog::labelFor($subject) : null,
                'properties' => array_filter($properties, fn ($value) => $value !== null && $value !== '') ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** The logistics partner whose team this account belongs to: its owner, or one of its riders. */
    private function providerOf(User $actor): ?int
    {
        return match (User::portalOf($actor->role)) {
            'logistics' => Schema::hasTable('logistics_providers') ? $actor->logisticsProvider?->getKey() : null,
            'rider' => Schema::hasTable('riders') ? $actor->rider?->logistics_provider_id : null,
            default => null,
        };
    }

    private function ready(): bool
    {
        return $this->ready ??= Schema::hasTable('activity_logs');
    }
}
