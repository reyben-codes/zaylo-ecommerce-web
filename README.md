# ZAYLO Marketplace

ZAYLO is an online marketplace for the Philippines: a Laravel 12 website for buyers, sellers, logistics partners and administrators, and a Flutter Android app for buyers and delivery riders. Both use the same server and database.

- **Website:** [zaylo-zyl.shop](https://zaylo-zyl.shop)
- **Android app:** [**Download the latest APK**](https://github.com/reyben-codes/zaylo-ecommerce-web/releases/latest/download/zaylo.apk) ([all versions](https://github.com/reyben-codes/zaylo-ecommerce-web/releases))

## Download the mobile app

1. On your Android phone, open **[github.com/reyben-codes/zaylo-ecommerce-web/releases/latest/download/zaylo.apk](https://github.com/reyben-codes/zaylo-ecommerce-web/releases/latest/download/zaylo.apk)**. The download starts right away.
2. Open the downloaded `zaylo.apk`. If Android asks, allow your browser or file manager to **install unknown apps**.
3. Tap **Install**, then open **Zaylo Mobile** and sign in as a buyer or rider.

The app is not on the Play Store yet, so Google Play Protect may show a warning the first time; choose **Install anyway**. To update, download the link again and install over the old version; your sign-in is kept. Android 7.0 or newer is required.

## Implemented workflows

- Public product catalog, filtering, search, product details, reviews, and shop pages
- Persistent buyer cart and wishlist, shop following, and ID verification
- Checkout with cash on delivery or GCash (manual, confirmed by the team), address and price snapshots, idempotency, and transactional stock reduction
- Buyer order tracking, cancellation with stock restoration, and complaints with photo evidence
- Seller applications, product management with options and listing review, inventory alerts, reports, and printable waybills
- Logistics partners: pickups, sorting, rider assignment, deliveries, and monitoring; riders scan waybills in the app to start and confirm each delivery
- Administrator approvals, identity checks, compliance, disputes, commissions, reports, and editable policies
- Chat between buyers, sellers, riders and support; announcements; account activity logs
- Profile photos with cropping, light and dark mode, and built-in Terms of Service, Privacy Policy, Returns, Prohibited Items and Seller Policy pages
- Email verification, password resets, Google sign-in, login throttling, role middleware, and ownership policies

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

The demo free-shipping voucher is `ZAYLOSHIP`. On an existing development database, run `php artisan migrate` and then `php artisan db:seed --class=VoucherSeeder` to add it without re-running the sample accounts and products.

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

To create an administrator on a deployed database, run `php artisan admin:provision admin@example.com --name="Full Name"`; it behaves like the seller command below and signs in at `/login`.

Logistics partners register from `/register?role=logistics` and need administrator approval (Admin Center → Approvals), then sign in at `/logistics/login`. Create an approved partner directly with `php artisan logistics:provision dispatch@example.com --company="Company Name"`. Riders register from `/register?role=courier`, choose an approved partner, and are approved by that partner in its Logistics Center.

To create a real seller login on a deployed database, run `php artisan seller:provision seller@example.com --name="Full Name" --store="Store Name"`. The account is created verified, active, and approved, and a strong password is printed once. Re-running the command repairs the account without changing its password; add `--reset-password` to issue a new one. It refuses to convert existing buyer, courier, or admin accounts.

## Verification

```bash
composer test
npm run build
```

The feature suite covers public browsing, marketplace account approval, seller ownership isolation, checkout and stock deduction, seller fulfilment, courier delivery, COD completion, commissions, and administrator approval.

## Production notes

- Set `APP_ENV=production`, `APP_DEBUG=false`, and HTTPS-only secure session cookies.
- Configure a real mail provider before enabling registrations.
- Payments are cash on delivery or GCash confirmed by hand. Add an online gateway through signed, idempotent webhooks rather than trusting browser callbacks.
- Run queue workers for email and other asynchronous work after changing `QUEUE_CONNECTION` from `sync`.
- Do not deploy seeded credentials or the legacy root-level `zaylo` SQLite development database.

## Mobile app (development)

The Flutter app is in [`mobile_app/`](mobile_app). It talks to `https://zaylo-zyl.shop/api` by default, so `flutter run` on a phone works against the live site.

```bash
cd mobile_app
flutter pub get
flutter run                                                   # live site
flutter run --dart-define=ZAYLO_API_URL=http://10.0.2.2:8000/api   # local server from the Android emulator
flutter test
```

## Publishing a new APK

The download link above always points to the newest GitHub release that has a file named exactly `zaylo.apk`.

This happens on its own: every push to `main` that changes the app (`mobile_app/`) builds a new APK and publishes it as the next patch version (`v1.0.3` → `v1.0.4`), so the download link always gives the newest app (about 10 minutes; watch it under **Actions → Release APK**). You can also start it from that page with **Run workflow**.

To choose the version yourself (for example a bigger update), push a tag instead:

```bash
git tag v1.1.0
git push origin v1.1.0
```

The version comes from the tag, so `pubspec.yaml` needs no edit; automatic releases continue from the highest tag.

One-time setup: the build signs with the same debug key as local builds, because Google sign-in and app updates only accept that key. Copy it into a repository secret: in PowerShell run `[Convert]::ToBase64String([IO.File]::ReadAllBytes("$env:USERPROFILE\.android\debug.keystore")) | Set-Clipboard`, then on GitHub open **Settings → Secrets and variables → Actions → New repository secret**, name it `DEBUG_KEYSTORE_BASE64` and paste.

To publish by hand instead: build with `cd mobile_app && flutter build apk --release`, rename `build/app/outputs/flutter-apk/app-release.apk` to `zaylo.apk`, and attach it to a new release under **Releases → Draft a new release**.

Do not commit APK files to the repository; `mobile_app/build/` is ignored on purpose. Release builds are currently signed with the debug key, which is fine for direct downloads. Before publishing on the Play Store, create a release keystore, keep it out of git, and register its SHA-1 for Google sign-in.
