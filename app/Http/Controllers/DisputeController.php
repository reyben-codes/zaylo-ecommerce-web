<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Order;
use App\Services\DisputeService;
use App\Support\OrderParties;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Complaints for the people in an order: the buyer files one, and the buyer, seller and courier
 * follow the case and reply to the admin.
 */
class DisputeController extends Controller
{
    public function __construct(private DisputeService $disputes) {}

    /** Cases the signed-in buyer, seller or courier is part of. */
    public function index(Request $request)
    {
        $this->available();
        $user = $request->user();
        $disputes = Dispute::involving($user)->with('order')->latest()->get();

        return view('disputes.index', ['disputes' => $disputes, 'layout' => $this->layout($request)]);
    }

    public function create(Request $request, Order $order)
    {
        $this->available();
        $this->ownsAsBuyer($request, $order);
        if ($existing = $order->disputes()->first()) {
            return redirect()->route('disputes.show', $existing);
        }

        return view('disputes.create', ['order' => $order->load('items'), 'categories' => Dispute::CATEGORIES, 'layout' => 'layouts.app']);
    }

    public function store(Request $request, Order $order)
    {
        $this->available();
        $this->ownsAsBuyer($request, $order);
        if ($order->disputes()->exists()) {
            throw ValidationException::withMessages(['details' => 'You already filed a complaint about this order. Reply in that case instead.']);
        }
        $data = $request->validate(Dispute::filingRules(), Dispute::filingMessages());
        $returnDetails = array_filter([
            'return_type' => $data['return_type'] ?? null,
            'return_method' => $data['return_method'] ?? null,
            'return_courier' => $data['return_courier'] ?? null,
            'pickup_date' => $data['pickup_date'] ?? null,
            'pickup_slot' => $data['pickup_slot'] ?? null,
            'dropoff_location' => $data['dropoff_location'] ?? null,
        ]);

        $dispute = $this->disputes->open($order, $request->user(), $data['category'], $data['details'], $request->file('evidence', []), $returnDetails);

        return redirect()->route('disputes.show', $dispute)->with('status', 'Your request was filed. The ZAYLO team and seller will review it and reply here.');
    }

    public function show(Request $request, Dispute $dispute)
    {
        $this->available();
        abort_unless($dispute->isOpenTo($request->user()), 403);
        if ($request->user()->hasRole('admin')) {
            return redirect()->route('admin.disputes.show', $dispute);
        }

        return view('disputes.show', [
            'dispute' => $dispute->load(['order.items', 'messages.sender', 'opener']),
            'layout' => $this->layout($request),
        ]);
    }

    public function reply(Request $request, Dispute $dispute)
    {
        $this->available();
        abort_unless($dispute->isOpenTo($request->user()), 403);
        if ($dispute->isClosed()) {
            throw ValidationException::withMessages(['body' => 'This case is closed. Contact the ZAYLO team in Help & support if you need more help.']);
        }
        $data = $request->validate(Dispute::replyRules(), Dispute::replyMessages());
        $this->disputes->reply($dispute, $request->user(), trim($data['body']), $request->file('attachment'));

        return back()->with('status', 'Reply sent.');
    }

    /** An evidence file from the complaint, or a message attachment, for the people in the case only. */
    public function file(Request $request, Dispute $dispute, int $index)
    {
        abort_unless($dispute->isOpenTo($request->user()), 403);

        return $this->stream(($dispute->evidence ?? [])[$index] ?? null);
    }

    public function attachment(Request $request, DisputeMessage $message)
    {
        abort_unless($message->dispute && $message->dispute->isOpenTo($request->user()), 403);

        return $this->stream($message->attachment_path);
    }

    private function stream(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function ownsAsBuyer(Request $request, Order $order): void
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 403);
        abort_if($order->status === 'cancelled', 422, 'Cancelled orders cannot have complaints.');
    }

    private function layout(Request $request): string
    {
        return $request->user()->hasRole('seller') ? 'layouts.seller' : 'layouts.app';
    }

    private function available(): void
    {
        abort_unless(Schema::hasTable('dispute_messages'), 503, 'Complaints are being set up. Please try again later.');
    }
}
