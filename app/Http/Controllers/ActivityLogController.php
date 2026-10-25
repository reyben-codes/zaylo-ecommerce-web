<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Activity log pages. Admins see every account; a logistics partner sees its own team (owner and riders);
 * sellers and riders see what they did themselves.
 */
class ActivityLogController extends Controller
{
    private const TIMEZONE = 'Asia/Manila';

    public function admin(Request $request)
    {
        $filters = $this->filters($request, [
            'role' => ['nullable', Rule::in(array_keys(AdminController::ROLE_LABELS))],
            'user' => 'nullable|integer|min:1',
        ]);
        $query = ActivityLog::query()
            ->when($filters['role'], fn (Builder $query, string $role) => $query->where('actor_role', $role))
            ->when($filters['user'], fn (Builder $query, int $user) => $query->where('user_id', $user));

        return view('admin.activity', $this->page($query, $filters, [
            'roleLabels' => AdminController::ROLE_LABELS,
            'filteredUser' => $filters['user'] ? User::find($filters['user']) : null,
        ]));
    }

    public function logistics(Request $request)
    {
        $provider = $request->user()->logisticsProvider;
        $filters = $this->filters($request, ['role' => ['nullable', Rule::in(['logistics', 'rider'])]]);
        $query = ActivityLog::query()
            ->where(fn (Builder $query) => $query->where('user_id', $request->user()->id)
                ->when($provider, fn (Builder $query) => $query->orWhere('logistics_provider_id', $provider->id)))
            ->when($filters['role'], fn (Builder $query, string $role) => $query->where('actor_role', $role));

        return view('logistics.activity', $this->page($query, $filters, [
            'roleLabels' => ['logistics' => 'Company account', 'rider' => 'Riders'],
        ]));
    }

    public function seller(Request $request)
    {
        $filters = $this->filters($request);

        return view('seller.activity', $this->page(ActivityLog::where('user_id', $request->user()->id), $filters));
    }

    public function courier(Request $request)
    {
        $filters = $this->filters($request);

        return view('courier.activity', $this->page(ActivityLog::where('user_id', $request->user()->id), $filters));
    }

    private function filters(Request $request, array $extra = []): array
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'category' => ['nullable', Rule::in(array_keys(config('activity.categories')))],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ] + $extra);

        return $filters + array_fill_keys(['search', 'category', 'from', 'to', ...array_keys($extra)], null);
    }

    private function page(Builder $scope, array $filters, array $data = []): array
    {
        if (! Schema::hasTable('activity_logs')) {
            $scope = ActivityLog::query()->whereRaw('1 = 0');
        }

        // Only the categories this account's log actually has are offered as filters.
        $categories = Schema::hasTable('activity_logs')
            ? collect(config('activity.categories'))->only((clone $scope)->distinct()->pluck('category'))->all()
            : [];

        $logs = $scope
            ->when($filters['category'], fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from, self::TIMEZONE)->startOfDay()->utc()))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to, self::TIMEZONE)->endOfDay()->utc()))
            ->when(filled($filters['search']), function (Builder $query) use ($filters) {
                $term = '%'.addcslashes($filters['search'], '%_\\').'%';
                $query->where(fn (Builder $nested) => $nested->where('description', 'like', $term)
                    ->orWhere('subject_label', 'like', $term)
                    ->orWhere('actor_name', 'like', $term)
                    ->orWhere('properties', 'like', $term));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return $data + compact('logs', 'filters', 'categories');
    }
}
