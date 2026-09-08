# ZAYLO Marketplace

ZAYLO is a Laravel 12 ecommerce marketplace with buyer, seller, courier, and administrator workflows.

## Implemented workflows

- Public product catalog, filtering, search, product details, and stock visibility
- Persistent buyer cart and wishlist
- Cash-on-delivery checkout with address snapshots, price snapshots, idempotency, and transactional stock reduction
- Buyer order tracking and safe cancellation with stock restoration
- Seller product management, inventory alerts, and controlled fulfilment states
- Courier parcel claiming and delivery-state tracking
- Administrator account approval and suspension
- Email verification, password resets, login throttling, role middleware, and ownership policies

## Local setup

Requirements: PHP 8.2+, Composer, Node.js, and MySQL (the local `.env` currently targets port `3307`).

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

For local development, file-backed sessions/cache and synchronous queues are recommended. These are the defaults in `.env.example`, so public pages still render when queue or cache infrastructure is unavailable.

After changing `.env`, clear stale cached values:

```bash
php artisan config:clear
```

## Registration and email codes

Registration follows Personal details → Address → Sign-in details → Email verification. The first three steps save an unverified account; the last step accepts the six-digit code sent to its email. Seller and courier accounts still require administrator approval after verification.

Run `php artisan migrate` to apply the pending marketplace migrations and the email verification code table (do not use `migrate:fresh` on an existing database). Codes expire after 10 minutes, allow five incorrect attempts, and can be resent after one minute. Only a hash is stored in the challenge table; successful verification removes it. Resending replaces the previous challenge. Existing unverified users can sign in and choose **Resend code**; old verification links are no longer used.

With `MAIL_MAILER=log`, messages are written to the configured application log, usually `storage/logs/laravel.log`, instead of delivered to an inbox. For delivery, configure `MAIL_MAILER=smtp`, your provider's `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and an authorized `MAIL_FROM_ADDRESS`, then run `php artisan config:clear`. Do not commit real mail credentials. Code emails are sent synchronously; a failed send keeps the unverified account and allows retrying from step 4.

## Test accounts

`php artisan migrate --seed` creates development-only accounts for each role. Their password is `password`. Never run `DatabaseSeeder` against production data.

## Verification

```bash
composer test
npm run build
```

The feature suite covers public browsing, marketplace account approval, seller ownership isolation, checkout and stock deduction, seller fulfilment, courier delivery, COD completion, commissions, and administrator approval.

## Production notes

- Set `APP_ENV=production`, `APP_DEBUG=false`, and HTTPS-only secure session cookies.
- Configure a real mail provider before enabling registrations.
- COD is the only payment method in this MVP. Add a gateway through signed, idempotent webhooks rather than trusting browser callbacks.
- Run queue workers for email and other asynchronous work after changing `QUEUE_CONNECTION` from `sync`.
- Do not deploy seeded credentials or the legacy root-level `zaylo` SQLite development database.
