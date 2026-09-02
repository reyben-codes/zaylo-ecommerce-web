<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function dashboard()
    {
        return view('courier.dashboard', ['user' => auth()->user()]);
    }

    public function deliveries()
    {
        return view('courier.deliveries');
    }

    public function earnings()
    {
        return view('courier.earnings');
    }

    public function history()
    {
        return view('courier.history');
    }

    public function account()
    {
        return view('courier.account');
    }

    public function chat()
    {
        return view('courier.chat');
    }
}
