<?php

namespace Tests\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Real, independent PostgreSQL connections for concurrency tests (§16.11).
 *
 * RefreshDatabase wraps each test in a transaction on the DEFAULT connection
 * only, so these connections AUTOCOMMIT: whatever they write survives the
 * test and must be cleaned up explicitly.
 *
 * Just as important, they must be fully released. disconnect() closes the PDO
 * but leaves the connection registered in the DatabaseManager; a lingering
 * session can hold locks that make a later migrate:fresh fail to DROP tables,
 * which surfaces much later as "relation ... does not exist" in unrelated
 * tests. purge() is what actually removes it.
 */
trait UsesRealPostgresConnections
{
    /** @var list<string> */
    private array $realConnections = [];

    private function realConnection(string $name): ConnectionInterface
    {
        config(["database.connections.{$name}" => config('database.connections.pgsql')]);

        $this->realConnections[] = $name;

        return DB::connection($name);
    }

    /**
     * Rolls back what the autocommitting connections wrote and releases them.
     * Call from a finally block so a failing assertion still cleans up.
     *
     * @param  list<string>  $sqlCleanup  statements run on the default connection
     */
    private function releaseRealConnections(array $sqlCleanup = []): void
    {
        foreach ($sqlCleanup as $sql) {
            DB::connection($this->realConnections[0] ?? null)->statement($sql);
        }

        foreach ($this->realConnections as $name) {
            DB::purge($name);
        }

        $this->realConnections = [];
    }
}
