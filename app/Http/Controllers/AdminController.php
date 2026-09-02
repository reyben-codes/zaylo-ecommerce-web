<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', ['user' => auth()->user()]);
    }

    public function users()
    {
        return view('admin.users');
    }

    public function registrations()
    {
        return view('admin.registrations');
    }

    public function disputes()
    {
        return view('admin.disputes');
    }

    public function compliance()
    {
        return view('admin.compliance');
    }

    public function commissions()
    {
        return view('admin.commissions');
    }

    public function reports()
    {
        return view('admin.reports');
    }

    public function settings()
    {
        return view('admin.settings');
    }

    public function account()
    {
        return view('admin.account');
    }

    public function chat()
    {
        return view('admin.chat');
    }
}
