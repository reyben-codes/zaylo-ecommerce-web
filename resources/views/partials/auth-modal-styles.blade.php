{{-- Stylesheets for the sign-in modal (partials/auth-modal). Only signed-out or unverified visitors need them. --}}
@if(isset($authModal) || ! auth()->check() || ! auth()->user()->hasVerifiedEmail())
    @foreach(array_filter(['auth-modal', 'email-verification', 'registration-terms', 'seller-application', 'rider-application', ($withConfirmDialog ?? false) ? 'confirm-dialog' : null]) as $sheet)
        <link rel="stylesheet" href="{{ asset("css/{$sheet}.css") }}?v={{ filemtime(public_path("css/{$sheet}.css")) }}">
    @endforeach
@endif
