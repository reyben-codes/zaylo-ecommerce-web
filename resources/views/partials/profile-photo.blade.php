{{-- Profile photo with Change and Remove, for every account page. Expects $user.
     Choosing a file opens the crop dialog, where the user drags and zooms the photo inside the circle;
     saving fills the crop fields and uploads (js/profile-photo.js). Without JavaScript, the Upload button
     sends the photo and the server keeps a centered square. --}}
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/profile-photo.css') }}?v={{ filemtime(public_path('css/profile-photo.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('js/profile-photo.js') }}?v={{ filemtime(public_path('js/profile-photo.js')) }}" defer></script>
    @endpush
@endonce

@php $photoError = $errors->first('photo') ?: $errors->first('crop_*'); @endphp
<div class="profile-photo" data-profile-photo>
    @include('partials.user-avatar', ['user' => $user, 'class' => 'profile-photo__avatar'])
    <div class="profile-photo__body">
        <div class="profile-photo__actions">
            <form method="POST" action="{{ route('account.photo.update') }}" enctype="multipart/form-data" data-profile-photo-form>
                @csrf
                @foreach(['x', 'y', 'width', 'height'] as $part)
                    <input type="hidden" name="crop_{{ $part }}" data-crop-{{ $part }} disabled>
                @endforeach
                <label class="profile-photo__button">
                    <i class="fas fa-camera" aria-hidden="true"></i>
                    <span data-profile-photo-label>{{ $user->avatar_url ? 'Change photo' : 'Upload photo' }}</span>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="profile-photo__input" aria-describedby="profile-photo-hint" required data-profile-photo-input @if($photoError) aria-invalid="true" @endif>
                </label>
                <button type="submit" class="profile-photo__submit" data-profile-photo-submit>Upload</button>
            </form>
            @if($user->avatar_url)
                <form method="POST" action="{{ route('account.photo.destroy') }}"
                      data-confirm="Your initials will show instead of a photo."
                      data-confirm-title="Remove profile photo?" data-confirm-button="Remove" data-confirm-tone="danger">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="profile-photo__remove">Remove</button>
                </form>
            @endif
        </div>
        <p id="profile-photo-hint" class="profile-photo__hint">JPG, PNG or WebP, up to 5 MB. You can crop it before saving.</p>
        <p class="profile-photo__error" role="alert" data-profile-photo-error @unless($photoError) hidden @endunless>{{ $photoError }}</p>
    </div>

    <dialog class="photo-cropper" aria-labelledby="photo-cropper-title" aria-describedby="photo-cropper-help" data-photo-cropper>
        <div class="photo-cropper__panel">
            <h2 id="photo-cropper-title" class="photo-cropper__title">Crop your photo</h2>
            <p id="photo-cropper-help" class="photo-cropper__help">Drag to move it and zoom to fit. The circle shows how your photo will look. With a keyboard, use the arrow keys to move and + or − to zoom.</p>
            <div class="photo-cropper__stage" tabindex="0" role="group" aria-label="Photo position" data-cropper-stage>
                <img alt="" draggable="false" class="photo-cropper__image" data-cropper-image>
            </div>
            <div class="photo-cropper__zoom">
                <button type="button" class="photo-cropper__zoom-step" aria-label="Zoom out" data-cropper-zoom-out><i class="fas fa-minus" aria-hidden="true"></i></button>
                <input type="range" min="1" max="4" step="0.01" value="1" aria-label="Zoom" data-cropper-zoom>
                <button type="button" class="photo-cropper__zoom-step" aria-label="Zoom in" data-cropper-zoom-in><i class="fas fa-plus" aria-hidden="true"></i></button>
            </div>
            <div class="photo-cropper__actions">
                <button type="button" class="photo-cropper__button" data-cropper-cancel>Cancel</button>
                <button type="button" class="photo-cropper__button photo-cropper__button--primary" data-cropper-save>Save photo</button>
            </div>
        </div>
    </dialog>
</div>
