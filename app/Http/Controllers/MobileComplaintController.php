<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Order;
use App\Models\User;
use App\Services\DisputeService;
use App\Support\OrderParties;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Complaints in the buyer and rider apps: the buyer files one about an order,
 * and the buyer, seller and rider follow the case and reply to the ZAYLO team.
 * These are the same cases the website and the Admin Center show.
 */
class MobileComplaintController extends Controller
{
    public function __construct(private DisputeService $disputes) {}

    public function index(Request $request): JsonResponse
    {
        $this->available();
        $disputes = Dispute::involving($request->user())->with('order')->latest()->get();

        return response()->json([
            'complaints' => $disputes->map(fn (Dispute $dispute) => $this->summary($dispute))->values(),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => collect(Dispute::CATEGORIES)->map(fn ($label, $key) => ['id' => $key, 'label' => $label])->values(),
            'max_files' => Dispute::MAX_EVIDENCE,
        ]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->available();
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        abort_if($order->status === 'cancelled', 422, 'Cancelled orders cannot have complaints.');
        if ($order->disputes()->exists()) {
            throw ValidationException::withMessages(['details' => 'You already filed a complaint about this order. Reply in that case instead.']);
        }
        $data = $request->validate(Dispute::filingRules(), Dispute::filingMessages());

        $dispute = $this->disputes->open($order, $request->user(), $data['category'], $data['details'], $request->file('evidence', []));

        return response()->json([
            'message' => 'Your complaint was filed. The ZAYLO team will review it and reply here.',
            'complaint' => $this->detail($dispute, $request->user()),
        ], 201);
    }

    public function show(Request $request, Dispute $dispute): JsonResponse
    {
        $this->available();
        abort_unless($dispute->isOpenTo($request->user()), 404);

        return response()->json(['complaint' => $this->detail($dispute, $request->user())]);
    }

    public function reply(Request $request, Dispute $dispute): JsonResponse
    {
        $this->available();
        abort_unless($dispute->isOpenTo($request->user()), 404);
        if ($dispute->isClosed()) {
            throw ValidationException::withMessages(['body' => 'This case is closed. Contact ZAYLO support if you need more help.']);
        }
        $data = $request->validate(Dispute::replyRules(), Dispute::replyMessages());
        $this->disputes->reply($dispute, $request->user(), trim($data['body']), $request->file('attachment'));

        return response()->json(['complaint' => $this->detail($dispute->fresh(), $request->user())], 201);
    }

    /** An evidence file from the complaint, for the people in the case only. */
    public function evidence(Request $request, Dispute $dispute, int $index)
    {
        abort_unless($dispute->isOpenTo($request->user()), 404);

        return $this->stream(($dispute->evidence ?? [])[$index] ?? null);
    }

    public function attachment(Request $request, DisputeMessage $message)
    {
        abort_unless($message->dispute && $message->dispute->isOpenTo($request->user()), 404);

        return $this->stream($message->attachment_path);
    }

    private function summary(Dispute $dispute): array
    {
        return [
            'id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'order_number' => $dispute->order?->order_number,
            'category' => $dispute->category,
            'category_label' => $dispute->category_label,
            'status' => $dispute->status,
            'status_label' => $dispute->status_label,
            'closed' => $dispute->isClosed(),
            'opened_at' => $dispute->created_at?->toIso8601String(),
            'updated_at' => $dispute->updated_at?->toIso8601String(),
        ];
    }

    private function detail(Dispute $dispute, User $viewer): array
    {
        $dispute->loadMissing(['order', 'messages.sender']);
        $routes = $viewer->hasRole('rider', 'courier') ? 'mobile.rider.complaints.' : 'mobile.complaints.';

        return $this->summary($dispute) + [
            'details' => $dispute->reason,
            'resolution' => $dispute->resolution,
            'resolved_at' => $dispute->resolved_at?->toIso8601String(),
            'can_reply' => ! $dispute->isClosed(),
            'evidence' => collect($dispute->evidence ?? [])->map(fn (string $path, int $index) => $this->file(
                $path, route($routes.'evidence', ['dispute' => $dispute->id, 'index' => $index])
            ))->values(),
            'messages' => $dispute->messages->map(fn (DisputeMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'mine' => (int) $message->sender_id === (int) $viewer->id,
                'sender' => $this->senderLabel($dispute, $message),
                'sent_at' => $message->created_at?->toIso8601String(),
                'attachment' => $message->attachment_path
                    ? $this->file($message->attachment_path, route($routes.'attachment', ['message' => $message->id]))
                    : null,
            ])->values(),
        ];
    }

    /** "Maria · Buyer", "Jun · Rider", or "ZAYLO team" for administrators. */
    private function senderLabel(Dispute $dispute, DisputeMessage $message): string
    {
        $role = $dispute->roleOf($message->sender_id === null ? null : (int) $message->sender_id);
        if ($role === 'Zaylo admin') {
            return 'ZAYLO team';
        }
        $name = Str::of($message->sender?->name ?? '')->before(' ')->toString();

        return trim(($name !== '' ? $name.' · ' : '').($role === 'Courier' ? 'Rider' : $role));
    }

    private function file(string $path, string $url): array
    {
        return [
            'name' => basename($path),
            'url' => $url,
            'is_image' => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true),
        ];
    }

    private function stream(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function available(): void
    {
        abort_unless(Schema::hasTable('dispute_messages'), 503, 'Complaints are being set up. Please try again later.');
    }
}
