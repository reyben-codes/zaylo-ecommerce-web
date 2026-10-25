@extends('layouts.workspace', ['workspace' => 'admin'])

@section('title', 'Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-modules.css') }}?v={{ filemtime(public_path('css/admin-modules.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Platform</p>
            <h1>Platform settings</h1>
            <p>Post announcements to the people who use ZAYLO, and keep the policy pages up to date.</p>
        </div>
    </section>

    <div class="seller-panel-grid">
        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Announcements</h2><p>Live announcements appear at the top of every page for their audience.</p></div></header>
            <ul class="announcement-list">
                @forelse($announcements as $announcement)
                    <li class="announcement-item">
                        <div class="announcement-item__head">
                            <strong>{{ $announcement->title }}</strong>
                            <span class="announcement-state announcement-state--{{ $announcement->state }}">{{ Str::headline($announcement->state) }}</span>
                        </div>
                        <p>{{ $announcement->body }}</p>
                        <span class="seller-cell-note">
                            <span class="announcement-audience announcement-audience--{{ $announcement->audience }}"><i class="fas {{ \App\Models\Announcement::AUDIENCE_ICONS[$announcement->audience] ?? 'fa-users' }}" aria-hidden="true"></i> {{ $audiences[$announcement->audience] ?? $announcement->audience }}</span>
                            @if($announcement->starts_at) · from {{ $announcement->starts_at->timezone('Asia/Manila')->format('M j, g:i A') }}@endif
                            @if($announcement->ends_at) · until {{ $announcement->ends_at->timezone('Asia/Manila')->format('M j, g:i A') }}@endif
                            · by {{ $announcement->author?->name }}
                        </span>
                        <div class="review-actions">
                            <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="toggle" value="1">
                                <button type="submit" class="seller-button seller-button--small seller-button--secondary">{{ $announcement->is_published ? 'Unpublish' : 'Publish' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" data-confirm="Delete the announcement &quot;{{ $announcement->title }}&quot;? Users will no longer see it." data-confirm-title="Delete announcement?" data-confirm-button="Delete" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="seller-button seller-button--small seller-button--danger">Delete</button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="admin-empty-inline">No announcements yet. Write the first one below.</li>
                @endforelse
            </ul>
            @if($announcements->hasPages())
                <div class="seller-pagination">{{ $announcements->links() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.announcements.store') }}" class="admin-form announcement-form">
                @csrf
                <h3>New announcement</h3>
                <div class="seller-field">
                    <label class="seller-field__label" for="announcement-title">Title</label>
                    <input type="text" id="announcement-title" name="title" value="{{ old('title') }}" maxlength="120" required placeholder="e.g. Holiday delivery schedule">
                    @error('title')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="seller-field">
                    <label class="seller-field__label" for="announcement-body">Message</label>
                    <textarea id="announcement-body" name="body" rows="3" maxlength="1000" required placeholder="What people need to know.">{{ old('body') }}</textarea>
                    @error('body')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </div>
                <fieldset class="seller-field announcement-audiences">
                    <legend class="seller-field__label">Who should see it?</legend>
                    <div class="announcement-audiences__options">
                        @foreach($audiences as $key => $label)
                            <label class="announcement-audience-option{{ $key === 'all' ? ' announcement-audience-option--public' : '' }}">
                                <input type="radio" name="audience" value="{{ $key }}" @checked(old('audience', 'all') === $key) required>
                                <span class="announcement-audience-option__icon" aria-hidden="true"><i class="fas {{ \App\Models\Announcement::AUDIENCE_ICONS[$key] }}"></i></span>
                                <span class="announcement-audience-option__text">
                                    <strong>{{ $label }}</strong>
                                    <small>{{ \App\Models\Announcement::AUDIENCE_HINTS[$key] }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('audience')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <div class="admin-form__row">
                    <div class="seller-field">
                        <label class="seller-field__label" for="announcement-starts">Starts (optional)</label>
                        <input type="datetime-local" id="announcement-starts" name="starts_at" value="{{ old('starts_at') }}">
                    </div>
                    <div class="seller-field">
                        <label class="seller-field__label" for="announcement-ends">Ends (optional)</label>
                        <input type="datetime-local" id="announcement-ends" name="ends_at" value="{{ old('ends_at') }}">
                        @error('ends_at')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
                <label class="admin-check"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', true))> Publish now</label>
                <button type="submit" class="seller-button"><i class="fas fa-bullhorn" aria-hidden="true"></i> Save announcement</button>
            </form>
        </section>

        <section class="seller-panel">
            <header class="seller-panel__header"><div><h2>Policies</h2><p>Public pages everyone agrees to. Changes show immediately.</p></div></header>
            <ul class="workspace-list">
                @foreach($policies as $policy)
                    <li class="workspace-list__item">
                        <div class="workspace-list__main">
                            <strong>{{ $policy['record']?->title ?? $policy['title'] }}</strong>
                            <span>
                                @if($policy['record'])
                                    Updated {{ $policy['record']->updated_at?->timezone('Asia/Manila')->format('M j, Y') }}{{ $policy['record']->editor ? ' by '.$policy['record']->editor->name : '' }}
                                    · <a href="{{ route('policies.show', $policy['slug']) }}" target="_blank" rel="noopener" class="workspace-link">View page</a>
                                @else
                                    {{ $policy['slug'] === 'terms' ? 'Using the built-in Terms page' : 'Not written yet' }}
                                @endif
                            </span>
                        </div>
                        <a href="{{ route('admin.policies.edit', $policy['slug']) }}" class="seller-button seller-button--small seller-button--secondary"><i class="fas fa-pen" aria-hidden="true"></i> Edit</a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
