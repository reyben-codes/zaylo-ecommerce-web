# Financial reset

Web and mobile use the same server records. Run this command against the intended database to inspect its transaction history:

```sh
php artisan finance:reset
```

The default is a preview. To clear orders, deliveries, payments, escrow, commissions, COD handovers, payouts, withdrawals and their notification records:

```sh
php artisan finance:reset --execute
```

The command writes a private JSONL backup under `storage/app/private/financial-resets/` before deleting anything. Each row includes its table name, and the final `backup_complete` marker confirms the export finished. Backups contain personal transaction details; keep them private and copy them off ephemeral hosts for retention. A backup can exist even when a reset rolls back; command success determines whether deletion committed.

Products, variants, prices, current stock, images, categories, accounts, saved GCash details and general chats are preserved. Product reviews retain their content with the old purchase link cleared. Voucher usage is reset. Audit logs remain. Auto-increment IDs are not reset, so delayed requests cannot match newly created orders by a reused ID.

The reset uses a database transaction, row/range locks, backup row-count checks and protected-data fingerprints. Unknown foreign-key dependencies or nontransactional MySQL tables abort the operation. Any failed validation or deletion rolls back the database changes. MySQL execution uses serializable isolation to protect the backup/delete interval from concurrent checkouts and credits.

This is an explicit operation, not a migration or deployment hook. Running it again clears transactions created since the previous reset. Reopen the app or refresh the website after a successful reset; no APK rebuild is needed.
