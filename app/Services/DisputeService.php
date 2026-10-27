<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DisputeUpdated;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Opening complaints, replying in the case thread, and the admin's decision, with emails to the people involved.
 */
class DisputeService
{
    /** @param UploadedFile[] $evidence */
    public function open(Order $order, User $buyer, string $category, string $details, array $evidence, array $returnDetails = []): Dispute
    {
        $paths = [];
        foreach ($evidence as $file) {
            // Private disk: evidence is only served through the case page to the people in it.
            $paths[] = $file->store('disputes/'.$order->id, 'local');
        }

        $tracking = null;
        if (! empty($returnDetails['return_method'])) {
            $tracking = 'RET-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(12));
        }

        $dispute = DB::transaction(fn () => Dispute::create([
            'order_id' => $order->id,
            'opened_by' => $buyer->id,
            'status' => 'open',
            'category' => $category,
            'reason' => $details,
            'evidence' => $paths,
            'return_type' => $returnDetails['return_type'] ?? 'return_refund',
            'return_method' => $returnDetails['return_method'] ?? null,
            'return_courier' => $returnDetails['return_courier'] ?? 'zaylo_express',
            'pickup_date' => $returnDetails['pickup_date'] ?? null,
            'pickup_slot' => $returnDetails['pickup_slot'] ?? null,
            'dropoff_location' => $returnDetails['dropoff_location'] ?? null,
            'dropoff_deadline' => ! empty($returnDetails['return_method']) && $returnDetails['return_method'] === 'dropoff' ? now('Asia/Manila')->addDays(3)->toDateString() : null,
            'return_tracking_number' => $tracking,
            'return_status' => ! empty($returnDetails['return_method']) ? 'scheduled' : null,
        ]));
        $this->notify($dispute, 'opened', except: $buyer->id);

        return $dispute;
    }

    public function reply(Dispute $dispute, User $sender, string $body, ?UploadedFile $attachment = null): DisputeMessage
    {
        $message = $dispute->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
            'attachment_path' => $attachment?->store('disputes/'.$dispute->order_id, 'local'),
        ]);
        // A reply from the buyer, seller or courier moves a new case into review; admins handle the status themselves.
        if (! $sender->hasRole('admin') && $dispute->status === 'open' && (int) $sender->id !== (int) $dispute->opened_by) {
            $dispute->update(['status' => 'in_review']);
        }
        if ($sender->hasRole('admin')) {
            $this->notify($dispute, 'updated', except: $sender->id);
        }

        return $message;
    }

    public function decide(Dispute $dispute, User $admin, string $status, ?string $resolution): void
    {
        $closing = in_array($status, ['resolved', 'rejected'], true);
        $dispute->update([
            'status' => $status,
            'resolution' => $resolution,
            'resolved_by' => $closing ? $admin->id : null,
            'resolved_at' => $closing ? now() : null,
        ]);
        if ($closing) {
            $this->notify($dispute, 'decided', except: $admin->id);
        }
    }

    /**
     * A step the seller takes on a return (item received, refund issued): saved on the case, noted in its thread,
     * and emailed to the buyer and courier.
     */
    public function sellerStep(Dispute $dispute, User $seller, string $event, array $changes, string $note): void
    {
        DB::transaction(function () use ($dispute, $seller, $changes, $note) {
            $dispute->update($changes);
            $dispute->messages()->create(['sender_id' => $seller->id, 'body' => $note]);
        });
        $this->notify($dispute, $event, except: $seller->id);
    }

    public function deleteFiles(Dispute $dispute): void
    {
        Storage::disk('local')->delete(array_merge($dispute->evidence ?? [], $dispute->messages->pluck('attachment_path')->filter()->all()));
    }

    private function notify(Dispute $dispute, string $event, ?int $except = null): void
    {
        $ids = array_values(array_filter($dispute->parties(), fn ($id) => $id && $id !== $except));
        foreach (User::whereIn('id', $ids)->get() as $user) {
            try {
                $user->notify(new DisputeUpdated($dispute, $event));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
