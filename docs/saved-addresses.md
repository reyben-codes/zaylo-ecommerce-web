# Philippine saved addresses

## Scope and schema

This extends the existing Address model and addresses table. Registration, Google authentication, OTP storage, pricing, stock handling, and payment methods are unchanged.

Migration: database/migrations/2026_09_07_000002_add_psgc_fields_to_addresses_table.php.

It adds only five nullable columns: region_code, region_name, province_code, city_municipality_code, barangay_code. Code columns are nine-character strings, retaining leading zeroes. The service uses the provider's nine-digit code, not its separate psgc10DigitCode.

Existing columns are reused:

| Structured value | Existing column |
| --- | --- |
| House / unit / street | line1 |
| Additional legacy street detail | line2 |
| Province name | province |
| City / municipality name | city |
| Barangay name | barangay |

Recipient, phone, postal_code, label, country_code, user_id, and is_default already existed. No duplicate name/street columns are introduced. For province-free locations, province_code is null and the existing non-nullable province text column is an empty string.

Existing free-text rows are retained without guessing PSGC codes. They are listed and editable, with their original details displayed. Users must complete the location selectors before using those legacy rows for a new checkout. Existing order shipping snapshots do not change when a saved address is edited or deleted.

The migration was applied to the local development database during implementation. On other environments, run php artisan migrate normally; do not use migrate:fresh on existing data.

## PSGC integration

Provider: https://psgc.gitlab.io/api/ (third-party PSGC-based data, not a PSA-operated service). Geography coverage and freshness depend on that provider's published dataset.

PsgcService uses Laravel's HTTP client with a three-second connection timeout and ten-second request timeout. Successful list responses are cached for 24 hours through the configured Laravel cache store. Base URL, TTL, and timeout are in config/locations.php. Failed/malformed responses are not cached; JSON failures return 503 with Retry-After: 30.

The browser calls only ZAYLO's internal location routes. It resets all descendants on parent changes, disables unavailable dropdowns, preserves saved selections when editing, cancels older requests, ignores stale results, and offers a retry button. Saving is disabled until a complete location is selected.

NCR has no provinces in this provider. The region-level city endpoint returns only records without a provinceCode. The selector skips province automatically if all localities are province-free; in a mixed region it offers a "No province / independent locality" option. This is data-driven, not an NCR-only exception.

Saving resolves each code within its parent's returned list. Names are taken from the server response, not client-submitted text. Province-bound cities cannot be submitted without their province. Postal codes are entered manually and checked for exactly four digits; PSGC codes are not postal codes, and this implementation does not claim to validate postal-code/locality correspondence.

Existing completed addresses can be used for checkout without contacting PSGC. An upstream failure while saving preserves form input and makes no address changes.

## Routes

All location routes are GET, public/read-only, and rate limited to 60 requests per minute per IP. They are declared in routes/web.php under an api/locations prefix; no API authentication package or bootstrap change is needed.

| Endpoint | Result |
| --- | --- |
| /api/locations/regions | Regions |
| /api/locations/regions/{regionCode}/provinces | Provinces in region |
| /api/locations/regions/{regionCode}/cities-municipalities | Province-free localities in region |
| /api/locations/provinces/{provinceCode}/cities-municipalities | Cities/municipalities in province |
| /api/locations/cities-municipalities/{cityCode}/barangays | Barangays in locality |

Saved-address routes require authenticated, active, email-verified buyer/seller/courier accounts. Each mutation and edit checks ownership; user_id and display names cannot be overridden through request data. Mutations lock the account row in a transaction to serialize default-address changes.

| Method | Endpoint | Route name |
| --- | --- | --- |
| GET | /account/addresses | addresses.index |
| GET | /account/addresses/create | addresses.create |
| POST | /account/addresses | addresses.store |
| GET | /account/addresses/{address}/edit | addresses.edit |
| PUT | /account/addresses/{address} | addresses.update |
| PATCH | /account/addresses/{address}/default | addresses.default |
| DELETE | /account/addresses/{address} | addresses.destroy |

Existing POST /buyer/checkout now accepts address_id, notes, and idempotency_key. The address must belong to the buyer and be structured. Recipient, phone, and formatted address are snapshotted from the saved row. Checkout does not create duplicate addresses or change their default flag.

The first address is default automatically. Editing an existing default keeps it default; switch defaults by choosing another address. Deleting the default promotes the oldest remaining row. Labels are Home, Work, School, Other. Add/edit from checkout accepts only return_to=checkout and returns to the cart with the saved row selected; arbitrary redirects are not accepted.

## Changed files

- database/migrations/2026_09_07_000002_add_psgc_fields_to_addresses_table.php — additive schema change.
- config/locations.php — upstream and cache settings.
- app/Services/PsgcService.php — cached HTTP client and hierarchy/name validation.
- app/Http/Controllers/LocationController.php — internal JSON endpoints.
- app/Http/Requests/SaveAddressRequest.php — address validation and ownership authorization.
- app/Http/Controllers/AddressController.php — list/create/edit/delete/default behavior.
- app/Models/Address.php — PSGC fields, owner relation, formatting and completeness check.
- app/Models/User.php — addresses relationship.
- app/Http/Controllers/BuyerController.php — load saved addresses and snapshot the chosen address at checkout.
- routes/web.php — location and saved-address routes.
- resources/views/addresses/index.blade.php — saved-address management.
- resources/views/addresses/form.blade.php — reusable create/edit selector and existing ZAYLO styles.
- public/js/address-selector.js — dependent dropdowns, edit hydration, loading/error/retry and stale-response protection.
- resources/views/buyer/cart.blade.php — saved-address choices and add/edit links returning to checkout.
- resources/views/buyer/account.blade.php — saved-address management link.
- resources/views/seller/account.blade.php — saved-address management link.
- resources/views/courier/account.blade.php — saved-address management link.
- tests/Support/PsgcFixtures.php — deterministic API and address data.
- tests/Feature/SavedAddressTest.php — CRUD/defaults, permissions, hierarchy, outages, legacy rows and checkout snapshots.
- tests/Feature/LocationApiTest.php — endpoint responses, caching/expiry, malformed input and failures.
- tests/Feature/MarketplaceFlowTest.php — existing order lifecycle now uses a saved address.
- tests/Support/render-address-form.php — CLI-only, unsaved browser fixture.
- tests/Browser/address-selector.mjs — real-browser dependent-selector regression checks.
- docs/saved-addresses.md — this implementation and testing guide.

## Automated checks

Run php artisan test. Tests use an isolated in-memory SQLite database and fake HTTP responses; no real account email or upstream PSGC requests are sent.

Browser checks: serve the app at http://127.0.0.1:8765 and start an isolated headless Chrome instance with a separate user-data-dir and remote-debugging-port=9224, then run:

    node tests/Browser/address-selector.mjs

The script renders a CLI-only fixture with an unsaved user, mocks PSGC fetches, asserts dependent resets, NCR, mixed-region province-free locality behavior, edit hydration, error/retry, stale response handling, and 390px layout. It closes that isolated browser and writes an ignored screenshot to storage/app/address-selector-mobile.png.

## Manual test checklist

1. Confirm registration remains Details → Sign-in → Verify, with no delivery-address fields.
2. Sign in as a verified active buyer. Open My Account → Manage saved addresses. Add Home using CALABARZON → Cavite → City of Bacoor → Alima, a street, phone, and four-digit postal code. Confirm readable names, retained codes, and automatic default status.
3. Add Work using NCR → Quezon City → a barangay. Province should display Not applicable. If the provider supplies a mixed region with province-free localities, check its No province / independent locality option too.
4. Change region, province, and city after selecting all children. Descendants must clear immediately. Change parents rapidly while requests are pending; only the newest selection should populate.
5. Edit each address. Verify initial selections reload, street/recipient/phone/label/postal code remain, and changes save. Try blank required fields, a malformed postal code, and mismatched codes through developer tools: saving must fail.
6. Add Home/Work/School/Other labels. Set a different default, edit it, set it default again, and delete it. There should be one default while addresses remain; deleting the final address should show the empty state.
7. Add a product to cart. Confirm your usable default is selected. Choose a different address and place a COD order. Confirm the recipient and delivery snapshot match your choice, address count does not grow, and default does not change.
8. From checkout, add an address or complete a legacy one. Saving should return to cart with that address selected. Cancel must return without saving. With no usable addresses, Place order should be disabled.
9. Edit/delete a saved address after ordering. Check the old order's delivery details stay unchanged.
10. Sign in as a second user and attempt the first user's edit/update/delete/default URLs and address_id at checkout. Requests must be rejected. Check unauthenticated/unverified/inactive access is blocked.
11. Open seller/courier account pages as approved, verified users. Their management links should work, but they must not access another user's addresses.
12. Simulate failed /api/locations requests in browser developer tools. Check error text, Retry, cleared child fields, and disabled Save. Previously saved complete addresses should still work at checkout during an upstream outage.
13. Check desktop and narrow mobile views for readable labels, keyboard operation, visible errors, and no horizontal overflow.
