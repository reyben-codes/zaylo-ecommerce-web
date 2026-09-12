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

When GOOGLE_REDIRECT_URI is blank, configuration derives it from APP_URL plus /auth/google/callback. Current local APP_URL is http://localhost, making the exact callback:

    http://localhost/auth/google/callback

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
