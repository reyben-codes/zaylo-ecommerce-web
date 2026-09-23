<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'user' => auth()->user(),
            'userCount' => User::count(),
            'pendingCount' => User::where('status', 'pending')->count(),
            'productCount' => Product::where('is_active', true)->count(),
            'orderCount' => Order::count(),
        ]);
    }

    public function users(Request $request)
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $request->role)))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%')))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.users', compact('users'));
    }

    public function updateUserStatus(Request $request, User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot change your own account status.');
        $data = $request->validate(['status' => 'required|in:active,pending,suspended']);
        $user->update([
            'status' => $data['status'],
            'approved_at' => $data['status'] === 'active' ? now() : null,
            'approved_by' => $data['status'] === 'active' ? auth()->id() : null,
        ]);
        return back()->with('status', 'Account status updated.');
    }

    public function registrations()
    {
        request()->merge(['status' => 'pending']);
        return $this->users(request());
    }

    public function disputes() { return view('admin.disputes'); }
    public function compliance() { return view('admin.compliance'); }
    public function commissions() { return view('admin.commissions'); }
    public function reports() { return view('admin.reports'); }
    public function settings() { return view('admin.settings'); }
    public function account() { return view('admin.account'); }
    public function chat() { return view('admin.chat'); }
}
