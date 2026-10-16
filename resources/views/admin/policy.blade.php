@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Edit '.$title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow"><a href="{{ route('admin.settings') }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Platform settings</a></p>
            <h1>{{ $policy?->title ?? $title }}</h1>
            <p>Write in plain text. Start a line with <code>## </code> for a heading and <code>- </code> for a bullet point; leave a blank line between paragraphs.</p>
        </div>
    </section>

    <section class="seller-panel">
        <form method="POST" action="{{ route('admin.policies.update', $slug) }}" class="admin-form">
            @csrf
            @method('PUT')
            <div class="seller-field">
                <label class="seller-field__label" for="policy-title">Page title</label>
                <input type="text" id="policy-title" name="title" value="{{ old('title', $policy?->title ?? $title) }}" maxlength="120" required>
                @error('title')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="seller-field">
                <label class="seller-field__label" for="policy-body">Text</label>
                <textarea id="policy-body" name="body" rows="22" class="policy-editor" required>{{ old('body', $policy?->body) }}</textarea>
                @error('body')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="admin-form-footer">
                <a href="{{ route('admin.settings') }}" class="seller-button seller-button--secondary">Cancel</a>
                <button type="submit" class="seller-button"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Publish changes</button>
            </div>
        </form>
    </section>
@endsection
