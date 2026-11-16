<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ResetFinancialRecords extends Command
{
    protected $signature = 'finance:reset {--execute : Clear financial, order and delivery records after creating a private backup}';

    protected $description = 'Preview or reset transaction history while preserving products, stock and accounts';

    // Delete children first, with foreign key enforcement left enabled. Never truncate or reuse IDs.
    private const TABLES = [
        'settlement_wallet_entries', 'seller_withdrawals', 'escrow_payments',
        'rider_leg_commissions', 'rider_commissions', 'rider_withdrawals',
        'cod_financial_transactions', 'logistics_cod_settlements',
        'wallet_cashouts', 'logistics_payouts', 'rider_cash_handovers', 'commissions',
        'dispute_messages', 'disputes', 'delivery_messages', 'delivery_events',
        'rider_delivery_logs', 'order_status_histories', 'payments', 'shipments',
        'order_items', 'seller_orders', 'orders',
    ];

    private const PRESERVE = ['products', 'product_variants', 'product_images', 'categories',
        'product_categories', 'product_shop_section', 'shop_sections', 'users', 'sellers',
        'seller_profiles', 'logistics_providers', 'riders', 'reviews'];

    private const SECTIONS = ['orders', 'wallet', 'payouts', 'settlement', 'earnings', 'pickups', 'deliveries', 'returns'];

    public function handle(): int
    {
        $connection = DB::connection();
        $host = $connection->getConfig('host');
        $local = $connection->getDriverName() === 'sqlite' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $this->info('Database: '.$connection->getName().' ('.($local ? 'local' : 'remote').'); environment: '.app()->environment());
        $tables = array_values(array_filter(self::TABLES, fn ($table) => Schema::hasTable($table)));
        $queries = [];
        foreach ($tables as $table) $queries[$table] = DB::table($table);

        if (Schema::hasTable('account_updates')) {
            $queries['account_updates'] = DB::table('account_updates')->whereIn('section', self::SECTIONS);
        }
        if (Schema::hasTable('mobile_push_messages')) {
            $queries['mobile_push_messages'] = DB::table('mobile_push_messages')->where(function ($q) {
                $q->whereIn('data->kind', ['order', 'shipment', 'wallet', 'payout', 'delivery', 'pickup', 'return', 'rider_pickups', 'rider_deliveries', 'delivery_chat']);
                if (Schema::hasTable('account_updates')) {
                    $q->orWhereIn('event_key', DB::table('account_updates')->whereIn('section', self::SECTIONS)->select('event_key'));
                }
            });
        }
        if (Schema::hasTable('buyer_notification_reads')) {
            $queries['buyer_notification_reads'] = DB::table('buyer_notification_reads')->where(function ($q) {
                $q->where('notification_key', 'like', 'order:%')->orWhere('notification_key', 'like', 'history:%');
                if (Schema::hasTable('account_updates')) {
                    $q->orWhereIn('notification_key', DB::table('account_updates')->whereIn('section', self::SECTIONS)
                        ->selectRaw("CONCAT('account:', id)"));
                }
            });
        }
        // SQLite does not implement CONCAT on older installations.
        if (isset($queries['buyer_notification_reads']) && $connection->getDriverName() === 'sqlite') {
            $queries['buyer_notification_reads'] = DB::table('buyer_notification_reads')->where(function ($q) {
                $q->where('notification_key', 'like', 'order:%')->orWhere('notification_key', 'like', 'history:%');
                if (Schema::hasTable('account_updates')) $q->orWhereIn('notification_key', DB::table('account_updates')
                    ->whereIn('section', self::SECTIONS)->selectRaw("'account:' || id"));
            });
        }
        $updates = [];
        if (Schema::hasColumn('product_reviews', 'order_item_id')) {
            $updates['product_reviews'] = [DB::table('product_reviews')->whereNotNull('order_item_id'), ['order_item_id' => null]];
        }
        if (Schema::hasColumn('vouchers', 'used_count')) {
            $updates['vouchers'] = [DB::table('vouchers')->where('used_count', '!=', 0), ['used_count' => 0]];
        }
        if (Schema::hasColumn('voucher_claims', 'used_at')) {
            $updates['voucher_claims'] = [DB::table('voucher_claims')->whereNotNull('used_at'), ['used_at' => null]];
        }
        $this->table(['Table', 'Action', 'Rows'], array_merge(
            array_map(fn ($table, $q) => [$table, 'delete', (clone $q)->count()], array_keys($queries), array_values($queries)),
            array_map(fn ($table, $op) => [$table, 'update links / usage', (clone $op[0])->count()], array_keys($updates), array_values($updates)),
        ));
        $this->line('Products, images, prices, stock, accounts, saved GCash details and general chats are preserved.');
        if (! $this->option('execute')) {
            $this->info('Preview only. No records changed. Run with --execute to back up and reset.');
            return self::SUCCESS;
        }
        $backup = storage_path('app/private/financial-resets/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.jsonl');
        $handle = null;
        try {
            $this->checkSchema($tables, array_keys($updates));
            if (! is_dir(dirname($backup)) && ! mkdir(dirname($backup), 0700, true)) throw new RuntimeException('Cannot create backup directory.');
            $handle = fopen($backup, 'x');
            if (! $handle) throw new RuntimeException('Cannot create backup file.');
            chmod($backup, 0600);
            // Range locks prevent a checkout or a scheduled credit from entering the backup/delete gap.
            if ($connection->getDriverName() === 'mysql' && $connection->transactionLevel() === 0) {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            }
            DB::transaction(function () use ($tables, $queries, $updates, $handle) {
                $before = $this->preservedState();
                $this->write($handle, ['format' => 1, 'created_at' => now()->toIso8601String(), 'database' => DB::connection()->getDatabaseName(),
                    'preserved' => $before]);
                $backedUp = [];
                foreach ($queries + array_map(fn ($op) => $op[0], $updates) as $table => $query) {
                    $backedUp[$table] = 0;
                    $this->write($handle, ['table' => $table, 'columns' => Schema::getColumnListing($table)]);
                    $ordered = clone $query;
                    foreach (Schema::getColumnListing($table) as $column) $ordered->orderBy($column);
                    foreach ($ordered->lockForUpdate()->cursor() as $row) {
                        $this->write($handle, ['table' => $table, 'row' => (array) $row]);
                        $backedUp[$table]++;
                    }
                }
                $this->write($handle, ['backup_complete' => true]);
                if (! fflush($handle)) throw new RuntimeException('Cannot flush backup.');
                if (function_exists('fsync') && ! fsync($handle)) throw new RuntimeException('Cannot persist backup.');
                foreach ($updates as [$query, $values]) (clone $query)->update($values);
                // Delete notification children before their source updates disappear from subqueries.
                foreach (['mobile_push_messages', 'buyer_notification_reads', 'account_updates'] as $table) {
                    if (isset($queries[$table]) && (clone $queries[$table])->delete() !== $backedUp[$table]) {
                        throw new RuntimeException('Concurrent notification changes detected. Reset rolled back.');
                    }
                }
                foreach ($tables as $table) {
                    if (DB::table($table)->delete() !== $backedUp[$table]) {
                        throw new RuntimeException('Unbacked records detected in '.$table.'. Reset rolled back.');
                    }
                }
                foreach ($tables as $table) {
                    if (DB::table($table)->exists()) throw new RuntimeException('Reset verification failed: '.$table);
                }
                if ($before !== $this->preservedState()) throw new RuntimeException('Protected data changed. Reset rolled back.');
            });
            fclose($handle);
            $this->info('Reset complete. Financial, order and delivery records cleared; protected data unchanged.');
            $this->line('Private backup: '.$backup);
            $this->line('Refresh the website and reopen the mobile app to reload the shared server data.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (is_resource($handle)) fclose($handle);
            $this->error('Reset failed; database changes rolled back. No further reset attempted.');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function preservedState(): array
    {
        $state = [];
        foreach (self::PRESERVE as $table) {
            if (! Schema::hasTable($table)) continue;
            $hash = hash_init('sha256');
            $columns = Schema::getColumnListing($table);
            $query = DB::table($table);
            foreach ($columns as $column) $query->orderBy($column);
            foreach ($query->sharedLock()->cursor() as $row) hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
            $state[$table] = hash_final($hash);
        }
        return $state;
    }

    private function checkSchema(array $tables, array $updated): void
    {
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, $tables, true)) continue;
            foreach (Schema::getForeignKeys($table) as $key) {
                if (! in_array($key['foreign_table'], $tables, true)) continue;
                if ($table === 'product_reviews' && $key['columns'] === ['order_item_id']) continue;
                // The deployed legacy review table has a non-null purchase FK, but is unused.
                // Protect its empty state in the transaction; refuse to discard any future reviews.
                if ($table === 'reviews' && $key['columns'] === ['order_item_id'] && ! DB::table($table)->exists()) continue;
                throw new RuntimeException('Unrecognized reference from '.$table.' to '.$key['foreign_table'].'. Nothing was deleted.');
            }
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (DB::select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.tables WHERE table_schema = DATABASE()') as $table) {
                if (in_array($table->name, array_merge($tables, $updated, ['account_updates', 'mobile_push_messages', 'buyer_notification_reads']), true)
                    && strtolower($table->engine ?? '') !== 'innodb') {
                    throw new RuntimeException('Reset requires transactional InnoDB tables: '.$table->name);
                }
            }
        }
    }

    private function write($handle, array $record): void
    {
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        if (fwrite($handle, $line) !== strlen($line)) throw new RuntimeException('Backup write failed.');
    }
}
