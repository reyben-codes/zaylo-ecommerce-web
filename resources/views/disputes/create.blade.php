@extends($layout)

@section('title', 'Report a problem')

@push('styles')
    @include('disputes.partials.styles', ['layout' => $layout])
@endpush

@section('content')
    <div class="case-page">
        <section class="seller-page-heading">
            <div>
                <p class="seller-eyebrow"><a href="{{ route('buyer.orders') }}" class="workspace-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> My Orders</a></p>
                <h1>Report a problem</h1>
                <p>Order {{ $order->order_number }}. Tell us what went wrong and attach photos if you have them. The ZAYLO team reviews every complaint with the seller and the courier.</p>
            </div>
        </section>

        <section class="seller-panel">
            <form method="POST" action="{{ route('buyer.disputes.store', $order) }}" enctype="multipart/form-data" class="admin-form case-form">
                @csrf
                <fieldset class="case-choices">
                    <legend class="seller-field__label">What went wrong?</legend>
                    @foreach($categories as $key => $label)
                        <label class="case-choice">
                            <input type="radio" name="category" value="{{ $key }}" @checked(old('category') === $key) required>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                    @error('category')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </fieldset>

                <div class="seller-field">
                    <label class="seller-field__label" for="details">Details</label>
                    <textarea id="details" name="details" rows="6" minlength="20" maxlength="2000" required placeholder="What happened, which item, and what you would like: a replacement, a refund, or something else.">{{ old('details') }}</textarea>
                    @error('details')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="seller-field">
                    <label class="seller-field__label" for="evidence">Photos or documents (optional)</label>
                    <input type="file" id="evidence" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                    <span class="seller-cell-note">Up to {{ \App\Models\Dispute::MAX_EVIDENCE }} files, JPG, PNG, WebP or PDF, 5 MB each. Only the ZAYLO team, the seller and the courier can see them.</span>
                    @error('evidence')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                    @error('evidence.*')<p class="seller-field__error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="case-order">
                    <strong>Items in this order</strong>
                    <ul>
                        @foreach($order->items as $item)
                            <li>{{ $item->quantity }} × {{ $item->product_name }}</li>
                        @endforeach
                    </ul>
                </div>

                <button type="submit" class="seller-button"><i class="fas fa-flag" aria-hidden="true"></i> Submit complaint</button>
            </form>
        </section>
    </div>
@endsection
