# Google sign-in for ZAYLO

## Implementation

Laravel Socialite 5.31.0 was installed through Composer. Only Google identity scopes (openid, profile, email) are requested. OAuth tokens are used transiently by Socialite; no access/refresh tokens or provider payloads are persisted. Provider errors are replaced by a generic user message; the application logs only their exception type, not their body or credentials.

Routes, both under the existing stateful web/guest middleware and a 10-per-minute throttle:

- GET /auth/google — google.redirect
- GET /auth/google/callback — google.callback

The registration button and its role-preserving JavaScript were retained unchanged. Login now has a matching Continue with Google option using the existing Google SVG and auth styles.

The role is allowlisted before redirect and stored server-side in the session. Socialite generates and validates OAuth state. The callback consumes its session context, ignores any callback role parameter, and expires the flow after ten minutes. No stateless OAuth path is used. Starting a newer flow supersedes an earlier flow in the same browser session.

## Account resolution

1. Require a nonempty Google subject identifier, a valid email address, and a true verified-email flag.
2. Look up the saved google_id first, then the verified email for linking/conflict checks.
3. An existing local account is reused only for an exact email match. Its name, password, auth_provider=local, role, approval metadata, and related data remain intact. Google ID and a valid HTTPS avatar are saved, and its matching email is considered verified.
4. A returning google_id retains its original ZAYLO email, even if Google's email later changes. It is never silently merged with another account. A different Google email cannot verify an unverified local email.
5. Conflicting Google IDs/emails are rejected without replacing an existing link. Unique database constraints and transaction retry handle simultaneous first-time callbacks.
6. A new Google account has auth_provider=google, password=NULL, and email_verified_at set. Buyers are active. Sellers/couriers get the existing profile row and pending status, with no approval timestamps assigned.
7. Inactive users remain logged out. Existing administrator approval activates partners normally; suspended accounts cannot log in. Existing roles are never changed. Admin/sorting_center cannot be created through public Google role parameters.

No Google authentication sends a ZAYLO email OTP. Local email/password registration, OTP code handling, saved addresses, and marketplace behavior were not changed. No migrations were added or modified. Existing users.google_id/avatar/auth_provider columns and the unique google_id index are reused.

## Environment setup

Blank variable entries were added to the ignored local .env and to .env.example:

    GOOGLE_CLIENT_ID=
    GOOGLE_CLIENT_SECRET=
    GOOGLE_REDIRECT_URI=

Put real credentials only in the ignored .env or deployment secret store. Do not paste them into Blade, JavaScript, source control, or support messages.

When GOOGLE_REDIRECT_URI is blank, configuration derives it from APP_URL plus /auth/google/callback. For the public ZAYLO domain, use:

    APP_URL=https://zaylo-zyl.shop
    GOOGLE_REDIRECT_URI=https://zaylo-zyl.shop/auth/google/callback

Register https://zaylo-zyl.shop/auth/google/callback as an Authorized redirect URI on the Google OAuth client. Start sign-in at https://zaylo-zyl.shop/login so the callback receives the same session cookie. A localhost callback sends users to localhost, where subsequent page links also use localhost.

If running Laravel on its usual development server instead, choose one origin consistently, for example:

    APP_URL=http://127.0.0.1:8000
    GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback

Use that same redirect URI in Google and browse ZAYLO at that origin. Do not switch localhost and 127.0.0.1 mid-flow: the session cookie/state must survive the round trip. Production uses the real HTTPS domain and HTTPS-secure session cookies.

After changing environment configuration:

    php artisan config:clear

Until credentials are supplied, clicking Google returns a friendly configuration message and email/password login stays available.

## Google Cloud Console

Create/select a project. In Google Auth Platform, configure Branding with ZAYLO, support/contact email, and Audience appropriate to your users; for public marketplace testing use External and add test accounts. Request only basic sign-in scopes through Data Access. See [Google's consent setup](https://developers.google.com/workspace/guides/configure-oauth-consent).

Create an OAuth client of type Web application. Add the exact callback above under Authorized redirect URIs, then copy its client ID and secret to your environment. This server-side flow does not use a JavaScript OAuth client or need Gmail/Drive API access. The scheme, host, port, path, and trailing slash must match. See [Google's web-server OAuth instructions](https://developers.google.com/identity/protocols/oauth2/web-server).

Do not add role query parameters to the registered callback URI. Production publishing/branding requirements should be completed in Google before a public launch.

## Changed files

- composer.json — requires laravel/socialite ^5.31.
- composer.lock — pins Socialite and its newly installed dependencies.
- config/services.php — environment-backed Google client configuration and HTTP timeouts.
- .env.example — blank Google variable names, no credentials.
- .env — the same blank entries in the ignored local configuration.
- routes/web.php — Google redirect and callback routes.
- app/Http/Controllers/GoogleAuthController.php — stateful redirects, errors, role session context, authentication and approval gate.
- app/Services/GoogleAccountService.php — verified identity validation, account creation/linking, transaction/conflict handling.
- resources/views/auth/login.blade.php — matching Google sign-in option.
- tests/Feature/GoogleAuthenticationTest.php — actual Socialite state/mapping logic with mocked Guzzle transport.
- docs/google-authentication.md — this guide.

Direct package: laravel/socialite 5.31.0.

New transitive packages: firebase/php-jwt 7.1.0, league/oauth1-client 1.11.0, paragonie/constant_time_encoding 3.1.3, phpseclib/phpseclib 4.0.1, symfony/polyfill-php82 1.38.1. No existing dependency versions were updated by the install.

## Automated testing

    php artisan test

The Google tests use the real Socialite GoogleProvider but replace all its HTTP with Guzzle MockHandler. No test contacts Google's OAuth servers. Tests cover new buyer/seller/courier accounts, administrator approval, invalid roles, callback manipulation, exact verified-email linking, preserved password/role/data, returning IDs, missing/unverified emails, cancellation, transport failure, conflicting identities, suspension, missing/mismatched/expired/replayed state, session regeneration, no OTP and no persisted access token.

## Manual checklist

1. Configure the OAuth client and local environment, clear config, and verify Google appears on login and all registration role variants.
2. With a new Google account, sign up as buyer. Confirm one active user, google_id, auth_provider=google, NULL password, verified email, buyer dashboard, and no OTP.
3. Repeat for seller and courier. Confirm pending status, correct profile row, no OTP, and no dashboard access until an administrator approves. Approve using ZAYLO's existing admin page, then retry Google login.
4. Use a manually registered account with the same exact verified Google email. Confirm it reuses the same user ID, keeps its existing password and role, and retains its orders/cart/addresses/profile. Sign out and confirm email/password login still works.
5. Sign out and use Google again. Confirm no duplicate account/profile and the original role/dashboard are retained, regardless of the registration role selected.
6. Try /auth/google?role=admin and role=sorting_center. Both must be rejected without an account. Callback role parameters must not change the role stored at initiation.
7. Cancel Google's consent/account selection. Confirm a friendly login error, no new account, and no authenticated session.
8. Open a callback without initiating a flow, alter state, wait over ten minutes, or replay a used callback after logout. Confirm rejection and restart with Continue with Google.
9. Suspend an existing user, then retry Google. Confirm it remains suspended and logged out.
10. Use fixtures in a test environment for an email already linked to a different google_id; confirm rejection instead of merging or overwriting identities.
11. Confirm manual registration still follows Details → Sign-in → Verify and that email/password, OTP resend/expiry, and saved-address checkout still work.
12. Check the login button and errors on desktop/mobile. Test with one consistent host/port so session cookies are preserved.

Real Google consent/callback testing remains dependent on your own configured OAuth credentials.

## Mobile app

The buyer app offers **Continue with Google** on its sign-in screen and on the first step of sign-up. It uses the phone's own Google sign-in (Android Credential Manager, Google Sign-In on iOS) through the `google_sign_in` Flutter package, not the website's redirect flow.

1. The app shows Google's account picker and receives a Google **ID token**.
2. It sends the token to `POST /api/mobile/google` (6 per minute, like `/api/mobile/login`).
3. `GoogleIdTokenVerifier` checks the RS256 signature against Google's published keys (`https://www.googleapis.com/oauth2/v3/certs`, cached as long as Google's `Cache-Control` allows and fetched again once if Google has rotated keys). It also checks the issuer, the expiry (60 seconds of clock tolerance), and that the token was issued to one of ZAYLO's OAuth clients.
4. The account is found, linked, or created by the same `GoogleAccountService` rules as the website, always as a **buyer**. Existing seller, rider or partner accounts with that Google ID or email are refused *before* anything is linked, so the buyer app never changes them. Suspended accounts are refused.
5. The app receives a normal Sanctum token, exactly as after email sign-in. Signing out of the app also forgets the chosen Google account, so the next sign-in asks again.

As on the website, no ID token, access token or Google profile payload is stored or logged; failures log only the exception type.

Riders keep signing in with email and password, matching the website, where Google sign-up is offered to buyers and sellers only.

### Server settings

| Variable | Value |
|---|---|
| `GOOGLE_CLIENT_ID` | The existing **Web application** client ID (already used by the website). The app passes it to Google as `serverClientId`, so Android ID tokens are issued to it. |
| `GOOGLE_MOBILE_CLIENT_IDS` | The **iOS** client ID (comma-separate several). Leave blank if you only ship Android. |

Run `php artisan config:clear` after changing them. Until `GOOGLE_CLIENT_ID` is set, the endpoint answers 503 with `code: google_unconfigured`.

### Google Cloud Console

Use the same project as the website's OAuth client. On **Google Auth Platform → Clients**:

1. **Web application**: the existing client. Copy its client ID; it is the app's `GOOGLE_SERVER_CLIENT_ID`. Nothing else changes.
2. **Android**: create a client with
   - package name `com.example.zaylo_mobile` (from `mobile_app/android/app/build.gradle.kts`);
   - the SHA-1 fingerprint of each key that signs the app. For local debug builds:

         keytool -list -v -keystore "%USERPROFILE%\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android

     Add one Android client per fingerprint: debug, your release/upload key, and the **App signing key** shown in Play Console if Google Play signs the app.

   No file goes into the app. Most "Google sign-in is not set up correctly" errors (`clientConfigurationError`, or a cancel right after choosing an account) are a missing or wrong SHA-1 or package name.
3. **iOS** (only if you ship iOS): create a client with bundle ID `com.example.zayloMobile` (from the Xcode project). Then:
   - add its client ID to `GOOGLE_MOBILE_CLIENT_IDS` on the server;
   - put its **reversed** client ID (`com.googleusercontent.apps.…`, shown on the client's page) in `mobile_app/ios/Flutter/GoogleSignIn.xcconfig`. `Info.plist` registers it as the URL scheme Google returns to.

Before publishing, replace the `com.example` package name and bundle ID with ZAYLO's own: Google Play rejects `com.example` apps. Then update the Android and iOS clients to match.

Apple's App Review Guideline 4.8 asks apps offering third-party sign-in such as Google to also offer an equivalent privacy-focused option (for example Sign in with Apple), with some exceptions; check this before an App Store release.

### Building the app

The client IDs are build settings, not secrets:

    flutter run --dart-define=ZAYLO_API_URL=https://zaylo-zyl.shop/api ^
                --dart-define=GOOGLE_SERVER_CLIENT_ID=<web client ID> ^
                --dart-define=GOOGLE_IOS_CLIENT_ID=<iOS client ID>

`GOOGLE_IOS_CLIENT_ID` is needed for iOS only. Without `GOOGLE_SERVER_CLIENT_ID` (or, on iOS, without `GOOGLE_IOS_CLIENT_ID`) the Google button is hidden and the app works with email sign-in only.

### Tests

- `tests/Feature/MobileGoogleSignInTest.php` signs real RS256 ID tokens with the test-only keys in `tests/Fixtures/google/` and serves their public halves as a faked Google key set. It covers sign-up, linking by exact email, iOS audiences, wrong audience or issuer, expiry, unverified email, forged signatures, HS256 and malformed tokens, refusing seller accounts without linking them, suspension, key rotation, and missing configuration or unreachable Google.
- `mobile_app/test/app_test.dart` ("continue with Google") replaces the native sign-in with a fake and covers signing in, closing the account picker, a refused account, and the hidden button.

Signing in with a real Google account needs the Android or iOS client from the steps above and a phone or emulator with a Google account.
