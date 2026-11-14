# ZAYLO web and mobile readiness review

Reviewed: November 14, 2026 (Asia/Manila).

## Verdict

ZAYLO has substantial working e-commerce functionality and is suitable for a controlled pilot after the high-priority issues below are fixed. It should not yet be described as fully tested or production-ready for unrestricted use with real customer money and personal documents.

This assessment separates implemented features, reproducible defects, test maintenance, and deployment settings that could not be inspected. Passing a build or a subset of tests is not a substitute for verifying checkout, payment, fulfillment, cancellation, refunds, and access control together.

## Priority safety fixes after the initial review

Implemented in source on November 14, 2026:

- Payment verification, reference submission and expiry now lock the order before the payment. Expiry rechecks the payment after locking and skips received payments, including records with a receipt timestamp but an inconsistent status. Buyer and seller cancellation paths reject received payments and direct operators to the refund process. Repeated unpaid expiry does not restore stock twice, and the expiry count reflects actual cancellations.
- Web password changes and password resets revoke mobile tokens. Normal mobile password changes retain the current phone's token and revoke other phones. Browser sessions are bound to the signed-in password snapshot, so password changes also invalidate other browsers. Successful web sign-in establishes the snapshot; the browser performing an authenticated password change receives the new snapshot. Credential writes and mobile-token revocation are transactional.
- Added regression coverage for stale expiry candidates, paid cancellation attempts, once-only stock restoration, invalidation after password reset, old browser/mobile sessions, current-device retention, unrelated accounts and incorrect current-password submissions. Test authentication helpers now initialize the same snapshot as real sign-in when switching simulated accounts.

Validation after these fixes: the complete PHP suite ran 597 tests with 563 passing and the same 34 failures recorded in the initial review (7,476 assertions). All 10 added regression tests passed; comparing failing test identities against the baseline found no new failures. SQLite checks do not establish MySQL concurrency behavior.

Deployment: these are server-side changes and require no database migration or mobile rebuild. Existing browser sessions created before password snapshots were introduced require one fresh sign-in. The initial findings and audit counts below remain a historical baseline; database compatibility, dependency advisories and the other readiness recommendations remain open.

## Verification performed

| Check | Result | Scope |
| --- | --- | --- |
| Complete PHP test suite | 553 passed, 34 failed; 7,416 assertions; 587 tests total | In-memory SQLite; approximately 285 seconds |
| Complete Flutter test suite | 120 passed, 2 failed | Existing unit/widget tests; not a physical-device acceptance run |
| Dart analysis of `lib` and `test` | Passed | No analyzer findings |
| Vite production build | Passed | 58 modules transformed |
| Composer locked dependency audit | 18 advisories across 10 packages | Advisory matches, not proof that every affected API is reachable |
| npm dependency audit | 5 affected package entries: 2 critical, 3 high | Predominantly development/build dependencies; severities include transitive effects |
| Public deployed health endpoint | HTTP 200 | `/up`; does not prove scheduler, payments, storage or DB recovery work |
| Public deployed mobile catalog endpoint | HTTP 200 | `/api/mobile/home` |
| Guest logistics dashboard request | Redirected to logistics login | No authenticated production data inspected |
| Independent local probes | Three defects reproduced | Fresh-schema checkout, paid-order expiry cancellation, web password-change token invalidation |

All database probes used generated fixtures in a separate in-memory database. No production account, order, payment or delivery was modified. MySQL locking/concurrency, production settings, email delivery, backup restoration, real GCash transfers, a release APK installation, iOS builds, and physical-device scanning were not verified.

Raw results and disposable probes are in ignored `storage/app/platform-audit/`. Do not publish database dumps or runtime logs as audit evidence.

## Features with meaningful implementation and test coverage

- Product browsing, variants, search, cart selection, vouchers and saved addresses.
- Shared server-side totals and stock checks for web and mobile checkout.
- Role-based accounts, ownership checks, verification, hashed mobile tokens and secure mobile token storage.
- Manual GCash confirmation and COD collection/settlement records.
- Seller-selected logistics, pickup assignment, hub receipt, sorting, rider assignment and delivery evidence.
- Buyer tracking, reviews, disputes, seller refund records, support and private document storage paths.

These are real implemented workflows. The remaining issues affect confidence in their safety and consistency rather than implying the platform is only a visual mockup.

## High-priority defects and fixes

### 1. Checkout does not work against the fresh migration schema

Evidence: `app/Services/CheckoutService.php::place()` inserts legacy fields such as `order_number`, `user_id`, `subtotal` and `total`. Fresh migrations create the newer `buyer_id` / `reference` / `total_minor` schema with separate seller-order records. An independent checkout probe after ordinary migrations failed with:

```text
SQLSTATE[HY000]: General error: 1 table orders has no column named order_number
```

The full suite also has missing `product_id`, `order_number`, and `payload` column errors in several fixtures. Some are fixture problems, but the independent checkout probe establishes that fresh-install compatibility is an application defect too. This does not establish that the deployed legacy database currently rejects checkout.

Fix: choose one canonical production schema. Either implement the normalized checkout/order/payment writes throughout, or provide an explicit, supported migration path to the schema the deployed application actually uses. Do not rely on manually importing a historical dump as the installation procedure. Avoid rewriting live schema/data without a backup and migration rehearsal.

Acceptance: a brand-new MySQL database can run normal migrations and complete both web and mobile checkout, including multiple sellers, variants, vouchers, seller preparation and fulfillment. Existing production-shaped data must pass an upgrade test too.

### 2. The unpaid-order expiry path can cancel a paid GCash order

Evidence: `CheckoutService::cancelUnpaid()` locks the order and checks its order status, but does not recheck its payment status. `cancelLocked()` then changes the payment status to `cancelled`. `GcashPayments::expireDue()` selects unpaid candidates before this lock. A payment can be verified between selection and cancellation while the order remains `placed`.

An independent probe passed a `placed` order with a payment already marked `paid` to the expiry cancellation method. Result:

```text
order=cancelled, payment=cancelled, paid_at_preserved=yes
```

Fix: lock order and payment in a consistent order in every competing path; recheck payment state inside the transaction immediately before expiry. Verified payments must never be treated as unpaid. Route paid cancellations through an explicit refund workflow rather than replacing a paid financial event with an unpaid/cancelled status. Give references pending manual verification a clear review/escalation policy.

Acceptance: a payment verified just before expiry stays paid, stock is not incorrectly restored, simultaneous verification/expiry is safe on MySQL, and paid cancellations retain payment history and a traceable refund state.

### 3. Web password changes do not invalidate existing mobile sessions

Evidence: `app/Http/Controllers/Concerns/UpdatesAccountSettings.php::updatePassword()` changes the password and remember token, but does not revoke `personal_access_tokens`. The password-reset callback in `AuthController` also has no token revocation; its `PasswordReset` listener only records activity. The independent web password-change probe showed the previously issued mobile bearer token still authenticates.

Fix: centralize credential-change session invalidation. Revoke all mobile tokens on password reset and revoke other devices on authenticated password changes. Define and enforce the corresponding policy for other browser sessions. Keep the current mobile token only where that is an explicit authenticated-change policy.

Acceptance: an old mobile token receives HTTP 401 after password reset or web password change; the intended current session behaves consistently; unrelated users' sessions are unaffected.

### 4. Database copies containing account-related records are tracked in Git

Evidence: `git ls-files` includes `zaylo` and `zaylo_backup.sql`. The SQLite file has four user records. The SQL dump contains INSERT statements for users, addresses, payments, personal-access-token, verification-code and session tables, among others. No row values are included in this report. `.dockerignore` also does not exclude these root-level database files, so Docker's `COPY . .` can include them in the image.

Fix: stop tracking database copies, exclude them from Git and Docker build contexts, and store backups separately with access controls. Determine whether the contents are exclusively disposable development fixtures. If real account/session data or reused credentials entered a shared repository/history/image, investigate exposure and invalidate affected credentials/sessions. Adding an ignore rule does not erase old commits. Review any history cleanup with collaborators before performing it.

Also add a production guard to `DatabaseSeeder`: it currently creates known-password demo accounts without an environment check. Documentation warnings alone do not prevent an accidental production seed.

Acceptance: no database dumps, signing keys, live credentials or personal documents are tracked or shipped in application images; production seeding cannot silently create demo logins; any necessary account cleanup is completed.

### 5. Locked dependencies have outstanding security advisories

Composer reported advisories affecting Laravel, CommonMark, phpseclib and several Symfony components. npm reported affected entries for `concurrently`, `shell-quote`, `follow-redirects`, `source-map-js` and `vite`. These findings must be assessed by reachable functionality and runtime versus build-only usage; the advisory count is not a count of confirmed exploitable production vulnerabilities.

Fix: update compatible dependency versions on a branch, review the lockfile, repeat audits, and run the entire test suite plus release builds. Do not blindly apply forced major-version upgrades. Keep development servers private. Some advisories concern debug/development features, but that does not justify leaving them unreviewed.

Acceptance: no unreviewed advisories remain in the release dependency set, and any accepted residual risk is documented with exposure analysis.

## Reliability and release recommendations

### Restore a reliable release gate

The full web suite has 25 assertion failures and 9 errors. Failures span schema fixtures, registration requirements, seeded catalog data, notifications, seller pages, payment expectations, rider wallet/report expectations and waybills. Do not assume all failures are application bugs, and do not delete failing tests merely to obtain a green result.

Examples of stale expectations: `MobileApiTest` still expects buyers to select couriers, and a Flutter test still expects buyer-selected partner rates. These conflict with the current seller-selection requirement. The rider account Flutter test fails to find `SIGN OUT`; investigate scrolling/lazy rendering and verify that a real user can reach and operate it.

The only GitHub workflow is `release-apk.yml`; it builds/releases the APK without first running the web suite, Flutter tests or static analysis. Add a separate CI workflow, test on MySQL as well as isolated fixtures, and require green checks before deployment and APK publication.

### Strengthen checkout idempotency under concurrency

`CheckoutService::alreadyPlaced()` checks globally by a payment-key prefix before the placement transaction. The actual placement locks the buyer but does not repeat that check after obtaining the lock. A concurrent retry can pass the initial check and later fail because the first request already emptied the cart. The global check can also conflate the same key used by different buyers; the mobile replay lookup itself is buyer-scoped, so this is not evidence of cross-buyer order disclosure.

Fix: store checkout attempts with a unique buyer/key constraint, check and resolve the attempt inside the transaction, and return the original result for retries. Test concurrent repeated requests, different buyers using the same key, price changes and competing purchases of the last item using MySQL connections.

### Harden Android release configuration

`mobile_app/android/app/build.gradle.kts` uses the team debug key or falls back to debug signing. The main manifest allows cleartext traffic. The default API URL is HTTPS, so this finding does not establish that current production requests are being sent without TLS.

Fix: plan a migration to an appropriately protected production signing key, preserving update compatibility and Google sign-in registration for existing installations. Do not casually replace the installed app's signing identity or package ID. Remove the debug-signing fallback from production release builds and restrict HTTP allowances to development configuration. Validate a signed release on physical devices, including upgrade, login, secure storage, checkout, GPS, camera permission denial and pickup/delivery scanning.

### Verify background jobs, storage and recovery on the host

`routes/console.php` schedules registration cleanup, delivered-order confirmation, activity pruning and voucher pruning. The repository's `render.yaml` declares only a web service; it does not prove that a scheduler/worker was configured separately in the dashboard. GCash expiry currently runs when order lists are opened, rather than through an explicit scheduled task.

Verify the production scheduler runs every minute, queue workers run where needed, uploads use durable storage, private ID/document buckets remain private, failed writes are surfaced, and database/upload backups can actually be restored. Schedule unpaid-order expiry only after fixing its payment-state race. Add alerting for failed jobs, payment/settlement mismatches, storage failures and abnormal error rates.

### Improve manual payment/refund auditability

Manual GCash verification is a valid operational approach for a small pilot, but it is not an automated payment gateway. Payout and refund status updates do not move money. `SellerReturnController::refund()` accepts an amount and optional note without requiring a transfer reference.

Require traceable payment/refund references and operator records, reconcile transfers, and handle duplicate/disputed references. If adding automated payment processing, verify signed webhooks server-side and make every financial transition idempotent. Do not mark a refund paid solely because a button was pressed.

### Add deployment security headers and monitoring

The sampled public health/catalog/login responses did not include HSTS, CSP or `X-Content-Type-Options`. Missing headers alone do not establish a successful exploit. Add appropriate headers at the app or proxy layer; introduce CSP in report-only mode first because existing templates use inline styles/scripts. Verify production `APP_DEBUG=false`, secure cookies, trusted-proxy/origin restrictions and private error reporting. Those live environment settings were not inspected.

## Recommended order of work

1. Protect account/data exposure and fix paid-order expiry plus credential-change session invalidation.
2. Reconcile the database schema and prove fresh installation and production upgrades on MySQL.
3. Update dependencies; classify and repair the failing web/mobile tests against the approved requirements.
4. Add CI gates and strengthen idempotency/concurrency tests.
5. Harden release signing/network policy and verify host scheduling, durable storage, backups and monitoring.
6. Complete a staging acceptance cycle: two sellers, COD and GCash, a voucher, last-stock purchase, seller-selected partners, First Mile pickup, hub receipt, Last Mile delivery, buyer confirmation, paid cancellation/refund, rider payout and an app upgrade.

Only after those checks pass should the project move from a controlled pilot to general customer use.

## References

- [Laravel production deployment guidance](https://laravel.com/framework/docs/12.x/deployment)
- [Laravel scheduler setup](https://laravel.com/framework/docs/12.x/scheduling)
- [Android security guidance](https://developer.android.com/privacy-and-security/security-best-practices)
- Dependency-specific advisory URLs and affected ranges are recorded in the local Composer/npm audit artifacts; examples include [Symfony HeaderUtils advisory](https://symfony.com/cve-2026-108082) and [shell-quote advisory](https://github.com/advisories/GHSA-pqg4-j6r4-53mv).
