<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\User;
use App\Services\DisputeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Admin Center: complaints and disputes. Review the details and evidence, talk with the buyer, seller
 * and courier in the case thread, and record the decision.
 */
class AdminDisputeController extends Controller
{
    public function __construct(private DisputeService $disputes) {}

    public function index(Request $request)
    {
        if (! Schema::hasTable('dispute_messages')) {
            return view('admin.setup-needed', ['title' => 'Complaints and disputes']);
        }
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Dispute::STATUSES))],
            'category' => ['nullable', Rule::in(array_keys(Dispute::CATEGORIES))],
        ]);
        $status = $filters['status'] ?? null;
        $disputes = Dispute::with(['order', 'opener'])->withCount('messages')
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END"))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.disputes', [
            'disputes' => $disputes,
            'status' => $status,
            'category' => $filters['category'] ?? null,
            'counts' => Dispute::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Dispute $dispute)
    {
        $dispute->load(array_filter(['order.items', 'order.payment', Schema::hasTable('order_status_histories') ? 'order.statusHistory' : null, 'messages.sender', 'opener', 'resolver']));
        $parties = collect($dispute->parties())->map(fn ($id) => $id ? User::find($id) : null);

        return view('admin.dispute', ['dispute' => $dispute, 'parties' => $parties]);
    }

    public function update(Request $request, Dispute $dispute)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Dispute::STATUSES))],
            'resolution' => ['required_if:status,resolved,rejected', 'nullable', 'string', 'max:2000'],
        ], ['resolution.required_if' => 'Write the decision. The buyer, seller and courier see it.']);
        $this->disputes->decide($dispute, $request->user(), $data['status'], $data['resolution'] ?? null);

        return back()->with('status', 'Case marked as '.Dispute::STATUSES[$data['status']].'.');
    }

    public function reply(Request $request, Dispute $dispute)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.Dispute::MAX_EVIDENCE_KB],
        ], ['body.required' => 'Write a message first.']);
        $this->disputes->reply($dispute, $request->user(), trim($data['body']), $request->file('attachment'));
        if ($dispute->status === 'open') {
            $dispute->update(['status' => 'in_review']);
        }

        return back()->with('status', 'Message sent to everyone in the case.');
    }
}
