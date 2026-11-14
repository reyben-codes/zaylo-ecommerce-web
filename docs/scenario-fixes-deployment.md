# Deploying the scenario safety fixes

These changes support both the existing deployed order tables and the normalized tables produced by migrations. They do not convert historical orders from one layout to the other.

## What changed

- Fresh-schema checkout writes minor-unit totals, seller orders and their items. Orders remain split per seller. The buyer and seller order pages display them, and sellers can confirm, prepare and request pickup from a registered partner. COD, manual GCash, vouchers, cancellation and retry paths have regression coverage.
- Checkout replay lookups are scoped to the buyer. Web checkout serializes cart checks and placement with the buyer row lock; the shared service repeats the replay lookup after acquiring that lock. A retry returns the existing order set without changing stock or voucher usage again. MySQL multi-connection concurrency still needs a staging run.
- Refund recording requires a transfer method, reference and explicit confirmation that the transfer was already sent. The seller and timestamp are stored, and the case thread includes the reference. A case cannot be refunded twice and a seller cannot reuse one reference for two cases. This is an audit record of a manual transfer, not gateway verification or movement of money.
- Production `DatabaseSeeder` refuses demo seeding, including when Artisan's `--force` confirmation is bypassed. Existing demo accounts are not automatically deleted.
- Docker runs Apache and `schedule:work` under Supervisor. Voucher pruning and unpaid GCash expiry run every minute; hourly/daily tasks remain scheduled. Tasks use overlap prevention and single-server locks through a shared cache.
- `deployment:check` rejects production containers with local/ephemeral upload disks, incomplete R2 settings, shared public/private buckets, a non-HTTPS public upload URL, unsupported scheduling cache, pending migrations or debug mode. It does not contact the buckets or prove their access policy. Upload writes now throw/report failures instead of returning a failed path silently.
- Android releases require the existing team signing key and verify its certificate fingerprint; automatic fallback to an arbitrary local debug key is removed. The release workflow already supplies and checks that key. HTTP is disabled for the main/release manifest and enabled only in the debug manifest.

## Before deploying

1. Back up the production database and uploaded files, and rehearse the two new migrations against a staging copy. Never use `migrate:fresh`, `db:wipe`, or demo seeding on production. The checkout compatibility migration is intentionally forward-only so rollback cannot discard payment/history data; roll back application code only after checking schema compatibility.
2. In the hosting environment, retain the existing database, app key, mail and URL settings. Configure all six R2 settings listed in `.env.example`: `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_ENDPOINT`, `R2_PUBLIC_BUCKET`, `R2_PRIVATE_BUCKET`, `R2_PUBLIC_URL`. Keep the private bucket private and use a separate public image bucket. Check actual credentials and permissions with synthetic public and private uploads; verify a guest cannot retrieve a private document.
3. If uploads currently live on the old server's temporary disk, copy them before replacing that server. The existing `php artisan uploads:copy-to-r2 --dry-run` previews the copy; `php artisan uploads:copy-to-r2` performs it from a machine that still has the original files and configured R2 credentials. The Docker image intentionally excludes local uploads and recovery copies. Changing settings does not recover files already lost.
4. Set `APP_ENV=production`, `APP_DEBUG=false`, and `CACHE_STORE=database` (or a shared Redis store). Use an always-on service/worker. A sleeping or stopped web service cannot run time-based cleanup; a free-tier sleeping service needs an independently running scheduler.
5. `render.yaml` targets a Free Docker web service. The image's startup script runs `php artisan migrate --force --no-interaction`, then `deployment:check` and `schedule:list` before starting Apache and the scheduler. Existing dashboard-managed services must use that Docker image startup: remove any Docker command override that bypasses the image's CMD. Pushing YAML does not prove dashboard settings were applied. Startup stops on migration/check failure, and the reason appears in Render logs. This startup migration flow targets the single-instance Free service; before introducing multiple concurrent instances/deployments, move migrations to a serialized deployment job.

On Render Free, open the service's **Logs** after deployment. Look for `[ZAYLO startup] Applying pending database migrations`, `Deployment configuration checks passed`, the task list, and Supervisor starting both `web` and `scheduler`. No Shell access is required. Free instances sleep after inactivity, so the scheduler cannot guarantee cleanup at the exact deadline while the service is asleep. It resumes on the next startup; an always-on scheduler is required for uninterrupted cleanup.

On hosts with Shell access, the same checks can also be run manually:

```bash
php artisan deployment:check
php artisan schedule:list
```

Check Supervisor logs for a running scheduler and successful pruning executions. Existing cron/worker installations can use the same scheduled events; all instances must share the configured cache. If queues are asynchronous, keep the separate queue worker running too: the added Supervisor configuration runs the web server and scheduler only.

## Android release

Keep the current signing identity and `DEBUG_KEYSTORE_BASE64` workflow secret. This patch does not rotate the key, package ID or OAuth registration. The team's current key is still the historical debug-format key; replacing it with a production signing identity needs a planned upgrade/rotation procedure, not a random new keystore.

For a local release, explicitly set `ZAYLO_KEYSTORE` to the existing team key. Unset, missing or mismatched keys fail the release gate. Debug builds still use the local debug identity and can connect to a local HTTP development API. Pushes that change `mobile_app` trigger the existing APK workflow; install its successful release on an existing installation to verify an in-place upgrade, login and scanning.

## Verification and remaining limits

Final PHP regression run: 606 tests, 572 passing, the same 34 pre-existing failures as the original audit, and 7,541 assertions. Comparing failing test identities against the baseline found no new failures. All nine added scenario tests passed. Database tests used isolated in-memory SQLite fixtures; production data was not modified. `git diff --check` passed, and `schedule:list` lists minute-by-minute voucher pruning and unpaid-payment expiry.

The targeted safety tests cover fresh web/mobile checkout and order visibility, GCash verification, two sellers with a voucher, seller preparation/pickup, replay after cart removal, buyer isolation, refund metadata/replay protection and production deployment/seeding guards. The local team keystore passes the Gradle release-key task, while a missing key fails it as intended.

Android manifest/build validation could not complete offline because required AndroidX Media3 artifacts are not cached locally. Docker is not installed in the review environment, so the Supervisor container was not built or started locally. Production R2 access/privacy, an always-on scheduler, backup restoration, a full APK upgrade and real MySQL concurrency are host/staging acceptance checks, not established by these code tests.

## References

- [Laravel scheduling and single-server tasks](https://laravel.com/framework/docs/12.x/scheduling)
- [Render Docker startup behavior](https://render.com/docs/docker)
- [Render deployment and pre-deploy commands](https://render.com/docs/deploys)
- [Render Free limits, including sleep and unavailable Shell access](https://render.com/docs/free)
- [Android signing and update certificates](https://developer.android.com/studio/publish/app-signing)
