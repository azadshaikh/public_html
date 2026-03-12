<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPostgresSequencesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'astero:sync-postgres-sequences
                            {--connection= : Database connection name (defaults to database.default)}
                            {--dry-run : Print SQL statements without executing them}';

    /**
     * @var string
     */
    protected $description = 'Sync PostgreSQL sequences/identity counters to the current MAX(id) for each table.';

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?: config('database.default');
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'pgsql') {
            $this->warn(sprintf('Skipping: connection [%s] is not PostgreSQL.', $connectionName));

            return self::SUCCESS;
        }

        $rows = $connection->select(<<<'SQL'
            SELECT
                table_schema,
                table_name,
                column_name,
                pg_get_serial_sequence(format('%I.%I', table_schema, table_name), column_name) AS sequence_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND (
                column_default LIKE 'nextval(%'
                OR is_identity = 'YES'
              )
            ORDER BY table_name, column_name
            SQL);

        $dryRun = (bool) $this->option('dry-run');
        $synced = 0;

        foreach ($rows as $row) {
            if (empty($row->sequence_name)) {
                continue;
            }

            $table = $this->quoteIdentifier($row->table_schema).'.'.$this->quoteIdentifier($row->table_name);
            $column = $this->quoteIdentifier($row->column_name);

            $sql = sprintf('SELECT setval(?::regclass, GREATEST((SELECT COALESCE(MAX(%s), 0) FROM %s), 1), true)', $column, $table);

            if ($dryRun) {
                $this->line($sql.'; -- '.$row->sequence_name);

                continue;
            }

            $connection->select($sql, [$row->sequence_name]);
            $synced++;
        }

        if (! $dryRun) {
            $this->info(sprintf('Synced %d sequences.', $synced));
        }

        return self::SUCCESS;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
