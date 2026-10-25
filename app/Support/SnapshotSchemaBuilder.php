<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;

/**
 * Answers Schema::hasTable / hasColumn / hasColumns / getColumnListing from one
 * snapshot of the database's table and column names, loaded with a single
 * query the first time a request asks.
 *
 * The models check the schema constantly (to support both the legacy and the
 * current table layout), and each check is a round trip to the database. On a
 * remote database such as Aiven that turned ~95 checks into seconds per request.
 *
 * Bound for web requests only (see AppServiceProvider): migrations and other
 * console commands change the schema while they run, so they keep the stock
 * builder that always asks the database. Nothing outlives the request, so a
 * migration or an imported dump is picked up by the next request.
 */
class SnapshotSchemaBuilder extends MySqlBuilder
{
    /** @var array<string, array<int, string>>|null table name => column names in table order */
    private static ?array $snapshot = null;

    /** Whether the server compares table names case-insensitively (lower_case_table_names != 0). */
    private static bool $caseInsensitive = false;

    public function hasTable($table)
    {
        $tables = $this->snapshot($table);

        return $tables === null ? parent::hasTable($table) : isset($tables[$this->key($table)]);
    }

    public function getColumnListing($table)
    {
        $tables = $this->snapshot($table);

        return $tables === null ? parent::getColumnListing($table) : ($tables[$this->key($table)] ?? []);
    }

    public function hasColumn($table, $column)
    {
        return in_array(strtolower($column), array_map(strtolower(...), $this->getColumnListing($table)), true);
    }

    public function hasColumns($table, array $columns)
    {
        $tableColumns = array_map(strtolower(...), $this->getColumnListing($table));

        foreach ($columns as $column) {
            if (! in_array(strtolower($column), $tableColumns, true)) {
                return false;
            }
        }

        return true;
    }

    /** Schema changes made during the request (rare outside migrations) drop the snapshot. */
    protected function build(Blueprint $blueprint)
    {
        parent::build($blueprint);
        self::flush();
    }

    public static function flush(): void
    {
        self::$snapshot = null;
    }

    /** The snapshot, or null when the table names another schema, which falls back to a live check. */
    private function snapshot(string $table): ?array
    {
        [$schema] = $this->parseSchemaAndTable($table);
        if ($schema !== null && $schema !== $this->connection->getDatabaseName()) {
            return null;
        }

        if (self::$snapshot === null) {
            $rows = $this->connection->selectFromWriteConnection(
                "select c.table_name as table_name, c.column_name as column_name, @@lower_case_table_names as lctn
                 from information_schema.columns c
                 join information_schema.tables t on t.table_schema = c.table_schema and t.table_name = c.table_name
                 where c.table_schema = database() and t.table_type in ('BASE TABLE', 'SYSTEM VERSIONED')
                 order by c.table_name, c.ordinal_position"
            );

            $tables = [];
            foreach ($rows as $row) {
                $row = array_change_key_case((array) $row);
                self::$caseInsensitive = (int) $row['lctn'] !== 0;
                $tables[$this->normalize($row['table_name'])][] = $row['column_name'];
            }
            self::$snapshot = $tables;
        }

        return self::$snapshot;
    }

    private function key(string $table): string
    {
        [, $table] = $this->parseSchemaAndTable($table);

        return $this->normalize($this->connection->getTablePrefix().$table);
    }

    private function normalize(string $table): string
    {
        return self::$caseInsensitive ? strtolower($table) : $table;
    }
}
