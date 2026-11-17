<?php

namespace App\Http\Controllers;

use App\Models\AccountReport;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountReportController extends Controller
{
    private function active(Request $request): void
    {
        abort_unless($request->user()?->isActive() && $request->user()->hasVerifiedEmail(), 403);
    }

    public function index(Request $request)
    {
        $this->active($request);
        $reports = AccountReport::where('reporter_id', $request->user()->id)->latest()->paginate(20);
        if ($request->expectsJson()) return response()->json(['reports' => $reports, 'categories' => AccountReport::CATEGORIES]);
        $shopId = $request->validate(['shop_id' => 'nullable|integer|min:1'])['shop_id'] ?? null;
        $shop = $shopId ? Shop::find($shopId) : null;
        abort_if($shopId && ! $shop, 404);
        $layout = match ($request->user()->role) {
            'seller' => 'layouts.seller', 'admin', 'logistics' => 'layouts.workspace', default => 'layouts.app',
        };

        return view('account-reports.index', compact('reports', 'shop', 'layout'));
    }

    public function store(Request $request)
    {
        $this->active($request);
        $data = $request->validate([
            'shop_id' => 'nullable|integer|min:1', 'reported_user_id' => 'nullable|integer|exists:users,id',
            'reported_name' => 'nullable|string|max:120',
            'reported_role' => ['nullable', Rule::in(['buyer', 'seller', 'rider', 'courier', 'logistics', 'admin'])],
            'category' => ['required', Rule::in(array_keys(AccountReport::CATEGORIES))],
            'details' => 'required|string|min:20|max:3000', 'order_reference' => 'nullable|string|max:100',
        ]);
        $target = null;
        if (! empty($data['shop_id'])) {
            $shop = Shop::find($data['shop_id']);
            abort_unless($shop, 404);
            $target = $shop->owner;
        } elseif (! empty($data['reported_user_id'])) {
            $target = User::findOrFail($data['reported_user_id']);
        }
        if ($target?->is($request->user())) {
            throw ValidationException::withMessages(['reported_name' => 'You cannot report your own account.']);
        }
        $name = $target?->name ?? trim($data['reported_name'] ?? '');
        if ($name === '') throw ValidationException::withMessages(['reported_name' => 'Enter the account or shop name you are reporting.']);
        $report = AccountReport::create([
            'reporter_id' => $request->user()->id, 'reported_user_id' => $target?->id,
            'reported_name' => $name, 'reported_role' => $target?->role ?? ($data['reported_role'] ?? null),
            'category' => $data['category'], 'details' => trim($data['details']),
            'order_reference' => $data['order_reference'] ?? null, 'status' => 'open',
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Report submitted to the ZAYLO admin team.', 'report' => $report], 201)
            : redirect()->route('account-reports.index')->with('status', 'Report #'.$report->id.' submitted to the ZAYLO admin team.');
    }

    public function admin(Request $request)
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys(AccountReport::STATUSES))]])['status'] ?? null;
        $reports = AccountReport::with('reporter')->when($status, fn ($query) => $query->where('status', $status))->latest()->paginate(20)->withQueryString();

        return view('account-reports.admin', compact('reports', 'status'));
    }

    public function show(AccountReport $report)
    {
        return view('account-reports.show', ['report' => $report->load(['reporter', 'reportedUser', 'reviewer'])]);
    }

    public function review(Request $request, AccountReport $report)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(AccountReport::STATUSES))],
            'resolution' => 'nullable|required_if:status,resolved,dismissed|string|max:2000',
        ]);
        abort_if($report->reporter_id === $request->user()->id || $report->reported_user_id === $request->user()->id, 403, 'Another administrator must review a report involving your account.');
        DB::transaction(function () use ($request, $report, $data) {
            $locked = AccountReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $locked->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            app(\App\Services\ActivityLogger::class)->record('admin.account-reports.review', 'users', 'Reviewed account report #'.$locked->id, $request->user(), $locked, ['status' => $data['status']]);
        });

        return back()->with('status', 'Report review saved. The reporter can see the status and response.');
    }
}
