# Android phone notifications

The buyer/rider app uses Firebase Cloud Messaging (FCM) to show notifications in Android's notification tray. Laravel remains responsible for accounts and permissions; Firebase Authentication, Firestore and web-push certificates are not required for this feature. iOS/APNs is not configured by this change.

## Configuration

The Android application is `com.example.zaylo_mobile` in Firebase project `zaylo-ecommerce`. `mobile_app/android/app/google-services.json` is client configuration and is included in the app build. It is not the server service-account key.

Render must have:

| Setting | Value |
| --- | --- |
| `FIREBASE_PROJECT_ID` | `zaylo-ecommerce` |
| `FIREBASE_CREDENTIALS` | `/etc/secrets/firebase-service-account.json` |
| Secret File filename | `firebase-service-account.json` |
| Secret File contents | The private service-account JSON from this Firebase project |

Keep the private JSON outside the repository. Git/Docker exclusions prevent its usual filenames from being copied into an image. At runtime the container copies the Render-mounted credential to `/run/zaylo/` with directory mode 0700 and file mode 0600 owned by `www-data`; PHP reads that runtime copy. The startup deployment check verifies that the credential is readable, contains a signing key and matches the configured project. It does not make a live Firebase authentication/send request.

Firebase Cloud Messaging API (V1) must be enabled. The service account must have permission to send FCM messages for this project. Server authentication uses a signed short-lived OAuth JWT exchanged at Google's token endpoint; credentials and device identifiers are never printed. The Firebase 17 client uses `register()`/`onRegistered` installation IDs, and the server targets the HTTP v1 `fid` field.

The Android application manifest must include `firebase_messaging_installation_id_enabled=true`. Firebase's `register()` API requires this opt-in: notification permission can show **Allowed** even while installation registration fails because the flag is absent. The test button waits for successful Firebase registration and server registration before requesting a test send. Permission status is displayed separately, and registration failures remain visible on the page rather than being replaced with an instruction to enable an already allowed permission.

## Deployment

1. Push the server and mobile changes together, including `google-services.json`, the dependency lockfile and the new migration. Never stage the private service-account JSON.
2. Render's Docker startup applies the migration, checks configuration, prints scheduled tasks and starts Apache plus the scheduler. The migration adds device registrations, a notification outbox and client IDs for retried rider logistics-chat messages.
3. The existing GitHub APK workflow builds changes under `mobile_app` with the team's current signing key and a new build number. Install its successful latest APK over the existing installation. A website deployment alone does not update installed apps.
4. Launch the updated app, sign in and grant notification permission. Both existing restored sessions and new sign-ins register automatically. Android notification settings control sounds, banners and lock-screen visibility.

## Test on a phone

- Buyer: open the notification bell, then its settings icon, then **Send test notification**.
- Rider: open **Account → Phone notifications → Send test notification**.

The button requests/synchronizes permission and sends a test only to the requesting signed-in phone. It does not accept a recipient from the request body. A 202 response means the test was queued, not that Android has displayed it. Check the tray for **ZAYLO notifications — Phone notifications are connected.**

Repeat a test while ZAYLO is open, and another with it in the background. Use Home to background the app; Android's Force stop can prevent delivery until the app is opened again. Verify notification taps open the right account and screen, logout clears existing tray entries, another buyer account cannot open the old notification, and password reset revokes the old phone registration. Sound/pop-up display also depends on channel settings, Do Not Disturb, connectivity and device restrictions.

Then use staging accounts to exercise:

- Buyer order status/history changes.
- Last Mile delivery assignments and First Mile pickup assignments.
- Rider work-day/shift changes.
- Seller messages, delivery chat, support replies and logistics messages.

Taps open the associated order, parcel, pickup queue, schedule or conversation. Rider logistics messages can also be opened from **Account → Logistics messages**. Message text, addresses and financial details are excluded from tray previews; the authenticated API supplies those after opening the app.

## Delivery and session behavior

Registrations require an active authenticated buyer/rider session. Installation IDs are encrypted at rest, never returned by the API and associated with the specific mobile access token. Separate buyer and rider sessions can coexist on one phone. Unregistering one session cannot unregister another phone's session. Logout/password-token revocation cascades through device registrations and pending messages, and the sender rechecks expiry and account status before sending. Offline local logout cannot guarantee that a notification already accepted by Firebase will be withdrawn; taps still require the appropriate active local account.

Domain events write to the outbox in their transaction. A rollback removes its pending notifications. Sending happens after commit, normally in the application's termination callback; `push:send-pending` retries due messages every minute. A short lease prevents concurrent senders from claiming the same row. Transient failures retry up to five attempts with backoff, stale messages expire after 12 hours, and old delivery metadata is pruned after seven days when the sender runs. Successful sends are not retried; a stable notification tag replaces duplicates if an ambiguous network timeout causes a retry. Unregistered installations are removed, with a token-hash check protecting concurrent refreshes.

Render Free can sleep, so scheduled retries pause during inactivity. Firebase can still deliver messages that it has already accepted. The server must be awake to process new events or retry pending messages.

## Troubleshooting without Render Shell

Check application logs for **Mobile push** after sending a test. Logs contain message IDs, attempt counts and sanitized provider results, not credentials, raw provider bodies or device identifiers. `retry-http-403` usually needs project/API/IAM review; `retry-auth-or-transport` needs credential validity/connectivity checks. Configuration errors appear in the startup deployment check. Actual Firebase acceptance does not establish delivery to a physical phone.

On a host with Shell access:

```bash
php artisan deployment:check
php artisan schedule:list
php artisan push:send-pending
```

The sender command performs real sends for due outbox entries; use staging or deliberately queued test messages when verifying it.

## Local verification

- Release APK builds successfully with the existing team key and Firebase Android configuration; debug Google Services processing and Kotlin compilation also pass.
- Dart analysis of `lib` and `test` reports no issues.
- PHP: 616 tests, 582 passing and the same 34 failing test identities as the earlier audit. All ten added server push tests pass.
- Flutter after the registration fix: 125 passing and the same two existing failures (buyer-selected logistics checkout expectation and rider Account sign-out test); all five notification tests pass. They cover allowed permission with failed registration, server upload failures and waiting for registration before sending a test. The corrected release APK builds successfully, and its merged release manifest contains the installation-ID opt-in flag set to true.
- Shell syntax and `git diff --check` pass. Database tests use isolated SQLite fixtures; transport tests use generated fixture credentials and mocked Google endpoints. No live Firebase push or production database mutation was performed during validation.
- Physical foreground/background delivery, notification taps on a real phone, actual service-account permissions and an installed-app upgrade remain rollout acceptance checks.

## References

- [Flutter Firebase setup](https://firebase.google.com/docs/flutter/setup)
- [Flutter FCM registration](https://firebase.google.com/docs/cloud-messaging/flutter/get-started)
- [Foreground/background messages and notification taps](https://firebase.google.com/docs/cloud-messaging/flutter/receive-messages)
- [FCM HTTP v1 message fields](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages)
- [Render secret files](https://render.com/docs/configure-environment-variables)
