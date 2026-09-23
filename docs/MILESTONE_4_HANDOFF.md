# Milestone 4 — Schema & Accounts

## Submission files

- ERD: `docs/milestone-4-erd.pdf`
- Clean migration evidence: `docs/evidence/migrate-fresh.png`
- Schema verification: `tests/Feature/MilestoneFourSchemaTest.php`

## Board aligned contribution record

Replace each `Team member` entry with the exact display name used on your project board before submission.

| Workstream | Owner | Delivered files |
| --- | --- | --- |
| Accounts and organizations | Team member | users, roles, role_user, addresses, sellers, logistics_providers, riders; related models |
| Catalog | Team member | categories, products, product_variants, product_images; related models |
| Commerce | Team member | carts, cart_items, orders, seller_orders, order_items, payments; related models |
| Logistics and evidence | Team member | shipments, delivery_events, ERD, clean migration evidence, schema tests |

## Verification commands

```bash
php artisan migrate:fresh
php artisan test --compact tests/Feature/MilestoneFourSchemaTest.php
```

The submitted screenshot was produced against an isolated MySQL database named `zaylo_milestone4_check`; the verification helper removes that database afterward and does not touch the normal development database.
