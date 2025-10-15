<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait RecordsDatabaseQueries
{
    /**
     * @param  callable():void  $callback
     * @return array<int, array<string, mixed>>
     */
    protected function recordDatabaseQueries(callable $callback): array
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $callback();

            /** @var array<int, array<string, mixed>> $queries */
            $queries = $connection->getQueryLog();
        } finally {
            $connection->flushQueryLog();
            $connection->disableQueryLog();
        }

        return $queries;
    }
}
