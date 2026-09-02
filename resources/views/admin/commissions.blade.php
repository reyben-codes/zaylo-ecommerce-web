@extends('layouts.app')

@section('title', 'ZAYLO · Commissions')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active-link' : '' }}">Dashboard</a>
    <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active-link' : '' }}">Users</a>
    <a href="{{ route('admin.registrations') }}" class="{{ request()->routeIs('admin.registrations') ? 'active-link' : '' }}">Registrations</a>
    <a href="{{ route('admin.disputes') }}" class="{{ request()->routeIs('admin.disputes') ? 'active-link' : '' }}">Disputes</a>
    <a href="{{ route('admin.reports') }}" class="{{ request()->routeIs('admin.reports') ? 'active-link' : '' }}">Reports</a>
</div>
@endsection
@section('nav-icons')
<a href="{{ route('admin.chat') }}"><i class="fas fa-comment-dots"></i></a>
<a href="{{ route('admin.account') }}"><i class="far fa-user"></i></a>
@endsection


@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-percentage"></i>
        <div>
            <h1></h1>
            <p></p>
        </div>
    </div>
</div>
<div class="page-content">
    <div class="placeholder-card">
        <i class="fas fa-percentage"></i>
        <h2>Commission Management</h2>
        <p>Set and manage platform commission rates.</p>
    </div>
</div>
@endsection
