{{-- A user's profile photo, or their initials when there is none. Expects $user; optional $class. --}}
@if($user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="" class="user-avatar {{ $class ?? '' }}" referrerpolicy="no-referrer" loading="lazy">
@else
    <span class="user-avatar user-avatar--initials {{ $class ?? '' }}" aria-hidden="true">{{ $user->initials() }}</span>
@endif
