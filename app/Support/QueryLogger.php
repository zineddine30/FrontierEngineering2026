<?php

namespace App\Support;

class QueryLogger
{
    protected static array $queries = [];

    public static function record(string $sql, array $bindings, float $time): void
    {
        static::$queries[] = compact('sql', 'bindings', 'time');
    }

    public static function reset(): void { static::$queries = []; }

    public static function summary(): array
    {
        return [
            'query_count'   => count(static::$queries),
            'total_time_ms' => round(array_sum(array_column(static::$queries, 'time')), 2),
        ];
    }
}
