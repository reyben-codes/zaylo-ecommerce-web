# Seller proceeds and rider delivery pay

## Confirmed policy

- Admin and Logistics each receive 10% of the product value after item discounts.
- Buyers pay shipping unless they use a free-shipping voucher.
- Sellers choose an approved, active ZAYLO logistics partner when requesting pickup. Buyers cannot select or override a partner or courier at checkout, including through older mobile clients. Each seller's parcel is routed to that seller's chosen partner.
- The buyer's shipping charge and confirmed order total remain fixed when the seller chooses a partner. The selected partner's rider rate is saved at the seller's first pickup request for new orders; changing the company rate later does not change that agreement. Sellers arrange partner payment using the recorded shipping and commission allocations.
- A seller-funded shipping voucher deducts shipping from that seller's proceeds only.
- An Admin-funded shipping voucher is an Admin expense. It does not reduce seller proceeds.
- Logistics receives shipping revenue and pays its riders from its receipts.
- Each partner sets its delivery rate in Logistics Center > Account > Rider delivery pay. It starts at PHP 40, accepts centavos, and is limited to the current PHP 120 shipping budget.
- Seller proceeds are before product and business costs, not salary or profit. Rider earnings are payment for eligible deliveries, not attendance payroll.

## Implemented behavior

- Checkout snapshots both product commissions, the shipping funder and subsidy, and the rider rate. Editing a voucher or company rate later does not recalculate existing orders.
- The snapshot is stored with new orders and in the trusted payment payload for schema compatibility. New seller-order records also snapshot their delivery terms.
- Seller COD earnings show both commissions, seller-funded shipping, final net proceeds, and pending/recorded transfers. Seller reports no longer label sales minus only Admin commission as final net proceeds.
- Rider API earnings and withdrawal information show the partner's current rate and explain that existing orders keep their saved rate. Delivery credits are unique per parcel; new COD credits require collection confirmation.
- New COD orders require the full cash collection to be handed over. Rider earnings remain separate and are paid through the partner's withdrawal flow, so they are not both retained in cash and withdrawn.
- Logistics gross allocations include rider funds. Company earnings are shown after credited rider earnings and before other expenses.
- New COD allocations are excluded from Admin payout batches because Logistics already receives the COD collection. Prepaid payout batches include rider funds once, with the existing company wallet reserving rider amounts.
- For verified COD allocations, Admin's commission is offset against Admin-funded shipping. Logistics records a positive net payment to Admin; Admin records a negative net payment to Logistics. Both require actual transfer references and reject repeated recording. This transfer is distinct from the seller transfer.
- Existing orders without the new snapshot keep the original distribution and cash-retention policy. Existing payment amounts and recorded financial events are not backfilled or rewritten.
- The existing GCash flow still sends the buyer's payment directly to the seller. The Sales proceeds page shows completed GCash sales, the saved deductions, net proceeds, and fees to remit. It does not mark those fees paid. Payout and transfer records do not themselves send real GCash payments.

## Example: PHP 1,000 product, PHP 120 shipping, PHP 40 rider pay

| Shipping funder | Buyer pays | Seller proceeds | Admin product commission | Admin shipping expense | Admin net | Logistics gross | Rider earning | Logistics after rider |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Buyer | 1,120 | 800 | 100 | 0 | 100 | 220 | 40 | 180 |
| Seller voucher | 1,000 | 680 | 100 | 0 | 100 | 220 | 40 | 180 |
| Admin voucher | 1,000 | 800 | 100 | 120 | -20 | 220 | 40 | 180 |

The rider amount is included in Logistics gross; do not add it a second time. For an Admin shipping voucher in this COD example, Admin sends Logistics the PHP 20 net difference after offsetting its PHP 100 commission against the PHP 120 shipping expense.

If a seller's free-shipping voucher would make that order's proceeds negative, checkout requires more eligible items or removal of the voucher. The system does not invent a seller payout with a negative amount.

## Validation

Focused automated tests cover buyer/seller/Admin shipping funding, centavo rounding, order snapshots, historical-policy preservation, company rate ownership and validation, single rider credits, full COD handover, Admin transfer authorization and duplicate prevention, and prepaid payout funding. Checkout and seller-voucher regression tests cover cart totals and voucher use.

## Deployment

Run the normal database migration after deploying the code: php artisan migrate --force. Then rebuild compiled views using the project's deployment workflow. The migration adds the partner rate, nullable policy snapshots, and COD Admin-transfer references. No production migration or payment transfer is performed by local tests.
