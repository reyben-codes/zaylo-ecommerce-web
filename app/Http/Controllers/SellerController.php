<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function dashboard()
    {
        return view('seller.dashboard', ['user' => auth()->user()]);
    }

    public function products()
    {
        return view('seller.products');
    }

    public function orders()
    {
        return view('seller.orders');
    }

    public function inventory()
    {
        return view('seller.inventory');
    }

    public function handover()
    {
        return view('seller.handover');
    }

    public function reports()
    {
        return view('seller.reports');
    }

    public function account()
    {
        return view('seller.account');
    }

    public function chat()
    {
        return view('seller.chat');
    }
}
