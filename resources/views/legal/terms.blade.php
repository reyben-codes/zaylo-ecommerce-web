@extends('layouts.app')

@section('title', 'Terms and Privacy Policy · ZAYLO')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/legal.css') }}?v={{ filemtime(public_path('css/legal.css')) }}">
@endpush

@section('content')
<main class="legal-page">
    <header class="legal-page__header">
        <p class="legal-page__eyebrow">ZAYLO marketplace</p>
        <h1>Terms and Privacy Policy</h1>
        <p>Last updated October 12, 2026. By creating a ZAYLO account you agree to these terms.</p>
    </header>

    <section aria-labelledby="terms-accounts">
        <h2 id="terms-accounts">1. Your account</h2>
        <ul>
            <li>Give accurate details and keep your password private. One email address and one mobile number can belong to only one account.</li>
            <li>Buyers must be at least 18 years old.</li>
            <li>Seller and logistics accounts are reviewed by ZAYLO before they can be used. Rider applications are reviewed by the logistics partner the rider applies to.</li>
            <li>We may suspend an account that breaks these terms or is used for fraud.</li>
        </ul>
    </section>

    <section aria-labelledby="terms-buying">
        <h2 id="terms-buying">2. Buying and selling</h2>
        <ul>
            <li>Sellers are responsible for their listings, prices, stock and the condition of the items they ship.</li>
            <li>Orders are paid by cash on delivery. Buyers may be asked to verify their ID before checking out.</li>
            <li>Buyers can cancel an order while it is placed or confirmed, before the seller prepares it for delivery. Deliveries are handled by the seller or a ZAYLO logistics partner.</li>
        </ul>
    </section>

    <section aria-labelledby="terms-privacy">
        <h2 id="terms-privacy">3. What we collect and why</h2>
        <ul>
            <li><strong>Account details:</strong> your name, email, mobile number and, for buyers, your birthday, to create your account and check your age.</li>
            <li><strong>Addresses and orders:</strong> to deliver your orders and show your order history.</li>
            <li><strong>ID documents:</strong> uploaded IDs are stored privately and seen only by ZAYLO administrators for verification.</li>
            <li><strong>Messages:</strong> chats between buyers and sellers are stored so both sides can see the conversation.</li>
        </ul>
        <p>We share delivery details only with the seller, logistics partner and rider handling your order. We do not sell your personal information.</p>
    </section>

    <section aria-labelledby="terms-rights">
        <h2 id="terms-rights">4. Your choices</h2>
        <p>You can update your details from your account page. To ask about or delete your data, contact the ZAYLO team.</p>
    </section>
</main>
@endsection
