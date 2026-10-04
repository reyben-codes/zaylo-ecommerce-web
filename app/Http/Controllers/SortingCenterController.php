<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SortingCenterController extends Controller
{
    private const STATUSES = ['unassigned', 'assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'];

    public function dashboard(Request $request)
    {
        $status = $request->string('status')->toString();
        $query = Shipment::query()->with(['order', 'sellerOrder.order', 'provider', 'rider']);

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $shipments = $query->latest()->paginate(15)->withQueryString();

        return view('sorting-center.dashboard', [
            'shipments' => $shipments,
            'status' => $status,
            'counts' => collect(self::STATUSES)->mapWithKeys(fn (string $value) => [$value => Shipment::where('status', $value)->count()]),
        ]);
    }

    public function updateStatus(Request $request, Shipment $shipment)
    {
        $data = $request->validate(['status' => ['required', 'in:' . implode(',', self::STATUSES)]]);

        DB::transaction(function () use ($shipment, $data): void {
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $data['status']) {
                return;
            }

            $locked->update(['status' => $data['status']]);
            $order = $locked->order ?: $locked->sellerOrder?->order;
            if ($order) {
                $order->statusHistory()->create([
                    'changed_by' => auth()->id(),
                    'status' => $data['status'],
                    'note' => 'Shipment status updated by sorting center.',
                ]);
            }
        });

        return back()->with('status', 'Shipment status updated.');
    }
}