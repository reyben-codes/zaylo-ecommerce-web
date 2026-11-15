# Settlement workflow revision — November 15, 2026

## Source changes

- Removed the standalone Kids shop-navigation section. Existing product classifications and historical products remain intact.
- New GCash orders use PayMongo Hosted Checkout v2. A signed `checkout_session.payment.paid` webhook checks the saved session, mode, reference, currency and amount before marking payment verified. Browser returns never mark an order paid. OTP/MPIN is entered only on the gateway's pages.
- Successful GCash orders appear in To Ship. The gateway payment is recorded as held; seller delivery preparation remains a separate action. Riders collect no cash for GCash.
- Confirming delivery releases held funds into internal seller/logistics/platform ledger balances in one database transaction. The return deadline also triggers automatic confirmation through the existing scheduler. Open disputes and recorded refunds prevent release. Recorded gateway fees reduce the platform's allocation. This internal hold/ledger is not a representation that a regulated escrow product or an external bank transfer has been provisioned.
- Seller Wallet / Payouts supports withdrawal requests to GCash or a named bank/account. Logistics cash-outs also become requests. Admin records an actual completed transfer with a unique reference. These requests do **not** automatically send external money. Test and live escrow wallet balances remain separate.
- Cash handover and payout references accept exactly 13 uppercase ASCII letters/numbers, such as `REF20261115AB`. Existing historical references are not rewritten.
- Logistics records rider salary as Released only after sending and validating the payment reference. The rider's Salary Received button changes it to completed, records receipt time and creates audit/notification events. A pending salary cannot be acknowledged and another rider cannot acknowledge it. Any positive available rider balance can be requested for a same-day payout.
- New scanned parcels save a fee quote per leg using weight and OSRM road distances. Pickup earnings credit after the pickup rider scans, confirms handover and Logistics checks the hub receipt. Delivery earnings credit after a scanned delivery and stored proof photo. Each leg credits once. Historic delivery commissions remain unchanged.
- Navigation badges poll unread updates every 15 seconds. Buyer order tabs have live counters for To Pay, To Ship, In Transit / Out for Delivery, To Receive and Completed. Payment settlement events are visible in notification feeds; rider financial notifications open the wallet.

## Deploy and configure

1. Back up the database. Rehearse the new forward-only migration on a staging copy, then run `php artisan migrate --force`. Do not use `migrate:fresh` on an existing database. The financial migration deliberately preserves its records when application code is rolled back.
2. Add server environment values from `.env.example`:

   ```dotenv
   PAYMONGO_SECRET_KEY=sk_test_...
   PAYMONGO_WEBHOOK_SECRET=...
   PAYMONGO_LIVE=false
   ESCROW_ADMIN_USER_ID=<existing administrator user ID>
   RIDER_BASE_PER_LEG=10
   RIDER_PER_KM=2
   RIDER_PER_KG=3
   RIDER_SHIPPING_BUDGET_PERCENT=80
   ROAD_ROUTING_URL=https://router.project-osrm.org
   ```

   Never commit real keys or include them in mobile assets. Clear cached configuration after changing values. Live mode requires a matching `sk_live_` key and a separately configured live webhook. Confirm merchant eligibility, settlement, marketplace/custody and payout capabilities with PayMongo before enabling real payments. No accounts or paid services are opened by this code change.
3. Register `https://<your-host>/api/webhooks/paymongo` for `checkout_session.payment.paid`. Copy that endpoint's signing secret to the server. The current implementation verifies `Paymongo-Signature` using the documented timestamp/HMAC scheme, mode-specific `te`/`li`, and a five-minute tolerance.
4. Set the seller's pickup address pin, buyer's delivery pin and product/variant weights. Logistics enters its hub latitude/longitude under Account → Company details. Missing measurements block a new scan instead of inventing a rate. Routing failures can be retried; a dedicated OSRM host can replace the public service.
5. The agreed fee formula is `PHP 10 + PHP 2 × km + PHP 3 × kg` for each leg. The combined rider earnings are capped at 80% of funded shipping; when capped, they split proportionally between the two leg quotes using integer centavos. The saved quote keeps its original rates and measurements after settings or products change.
6. Keep the scheduler running. GCash releases use the order's 24-hour return deadline, or earlier buyer confirmation, subject to dispute/refund holds. Historical orders continue to use the configured receipt grace policy.
7. Build/install the new APK together with the backend deployment. Older APKs do not implement hosted-checkout authorization or Salary Received. Use test payments first; sandbox balances do not fund real payouts.

## Verify before live use

- Complete hosted checkout in test mode and verify receipt of a genuinely signed webhook. Fake HTTP responses in automated tests do not establish that a merchant account is approved or that funds moved.
- Check duplicate callbacks, declined payment, cancellation while authorization is in flight, and webhook retries. A paid event arriving after order cancellation is held as `review_required`, never fulfilled or credited automatically; reconcile/refund it with the gateway. Sessions with an uncertain creation result are retried using the same provider idempotency key, and creation is blocked after 23 hours to avoid reusing the provider's expired 24-hour idempotency window.
- Demonstrate pickup scan → rider handover → Logistics checklist → pickup credit; then hub scan → rider handover → delivery scan with POD → delivery credit. Check first/last-mile accounts separately and verify COD still requires cash collection/remittance independently of its earnings milestone.
- Verify receipt confirmation, return-window expiry, dispute holds, refund reconciliation and wallet totals on MySQL. SQLite tests do not prove MySQL locking/concurrency behavior.
- Install the release APK on a physical device. Check background/foreground push notifications, badges, scanner permissions, GCash authorization return and Salary Received. PHP/Dart tests do not establish actual Firebase delivery or gateway availability.

## Provider references

- [Hosted Checkout v2](https://docs.paymongo.com/docs/payment-channels-hosted-checkout)
- [Webhook signature verification](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management)
- [Idempotent requests](https://docs.paymongo.com/reference/idempotent-requests)
- [Merchant test setup](https://docs.paymongo.com/docs/payment-channels-hosted-checkout-quick-start)
- [Current pricing](https://www.paymongo.com/en/pricing)
