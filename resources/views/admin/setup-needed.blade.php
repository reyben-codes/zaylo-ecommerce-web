@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', $title)

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Admin Center</p>
            <h1>{{ $title }}</h1>
        </div>
    </section>

    <section class="seller-panel">
        <div class="seller-empty">
            <i class="fas fa-database" aria-hidden="true"></i>
            <strong>This module needs the latest database update</strong>
            <span>Run <code>php artisan migrate --force</code> on the server, then reload this page.</span>
        </div>
    </section>
@endsection
