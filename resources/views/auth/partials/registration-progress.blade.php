<ol class="step-indicator" aria-label="Registration progress">
                @foreach(['Details', 'Sign-in', 'Verify'] as $label)
                    <li class="step-item {{ $loop->iteration < $activeStep ? 'completed' : '' }}" data-step="{{ $loop->iteration }}" @if($loop->iteration === $activeStep) aria-current="step" @endif><span class="step-number">0{{ $loop->iteration }}</span> {{ $label }}</li>
                @endforeach
            </ol>
