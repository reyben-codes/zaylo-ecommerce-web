{{--
    How a return goes, step by step, with when each step happened, its proof photos, and what the viewer does next
    (App\Support\ReturnProcess). Expects $case (a Dispute) and $role (buyer, seller, rider, logistics or admin).
--}}
@php $process = \App\Support\ReturnProcess::for($case, $role); @endphp

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/return-process.css') }}?v={{ filemtime(public_path('css/return-process.css')) }}">
    @endpush
@endonce

<div class="return-process">
    <p class="return-process__next{{ $process['finished'] ? ' is-finished' : '' }}" role="status">
        <i class="fas {{ $process['finished'] ? 'fa-circle-check' : 'fa-hand-point-right' }}" aria-hidden="true"></i>
        <span><strong>{{ $process['finished'] ? 'Status' : 'What you do now' }}:</strong> {{ $process['next'] }}</span>
    </p>

    <ol class="return-process__steps" aria-label="Return progress">
        @foreach($process['steps'] as $step)
            <li class="return-process__step{{ $step['done'] ? ' is-done' : '' }}{{ $step['current'] ? ' is-current' : '' }}" @if($step['current']) aria-current="step" @endif>
                <span class="return-process__dot" aria-hidden="true">
                    <i class="fas {{ $step['done'] ? 'fa-check' : ($step['current'] ? 'fa-circle-dot' : 'fa-circle') }}"></i>
                </span>
                <div class="return-process__body">
                    <strong>{{ $step['label'] }}</strong>
                    @if($step['detail'])<span>{{ $step['detail'] }}</span>@endif
                    @if($step['at'])<time datetime="{{ $step['at']->toIso8601String() }}">{{ $step['at']->format('M j, Y · g:i A') }}</time>@endif
                    <span class="sr-only">{{ $step['done'] ? '(done)' : ($step['current'] ? '(current step)' : '(to come)') }}</span>
                    @if($step['photo'])
                        <a href="{{ $step['photo'] }}" class="return-process__photo" target="_blank" rel="noopener" aria-label="Proof photo: {{ $step['label'] }} (opens in a new tab)">
                            <img src="{{ $step['photo'] }}" alt="Proof photo: {{ $step['label'] }}" loading="lazy" width="88" height="88">
                        </a>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
