<?php

namespace QueryDebugger;

use QueryDebugger\Storage\JsonFileStorage;
use QueryDebugger\Support\QueryFormatter;

class QueryLogger
{
    protected JsonFileStorage $storage;

    protected QueryFormatter $formatter;

    protected array $requestQueries = [];

    public function __construct(JsonFileStorage $storage, QueryFormatter $formatter)
    {
        $this->storage = $storage;
        $this->formatter = $formatter;
    }

    /**
     * Log a query to storage.
     */
    public function log(array $queryData): void
    {
        // Apply exclude patterns
        if ($this->shouldExcludeQuery($queryData['sql'])) {
            return;
        }

        // Add query hash for deduplication
        $queryData['query_hash'] = $this->formatter->hash($queryData['sql']);

        // Add formatted SQL
        $queryData['formatted_sql'] = $this->formatter->format(
            $queryData['sql'],
            $queryData['bindings']
        );

        // Store in file
        $this->storage->store($queryData);

        // Keep in memory for current request
        $this->requestQueries[] = $queryData;
    }

    /**
     * Check if query should be excluded based on patterns.
     */
    protected function shouldExcludeQuery(string $sql): bool
    {
        // Exclude queries that start with EXPLAIN (meta-queries used for analysis)
        $sqlUpper = strtoupper(trim($sql));
        if (str_starts_with($sqlUpper, 'EXPLAIN')) {
            return true;
        }

        $excludePatterns = config('query-debugger.exclude_patterns', []);

        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all queries logged for current request.
     */
    public function getRequestQueries(): array
    {
        return $this->requestQueries;
    }

    /**
     * Get query count for current request.
     */
    public function getRequestQueryCount(): int
    {
        return count($this->requestQueries);
    }

    /**
     * Get total time for all queries in current request.
     */
    public function getRequestTotalTime(): float
    {
        return array_sum(array_column($this->requestQueries, 'time_ms'));
    }

    /**
     * Get slow queries from current request.
     */
    public function getRequestSlowQueries(): array
    {
        $threshold = config('query-debugger.slow_query_threshold', 100);

        return array_filter($this->requestQueries, function ($query) use ($threshold) {
            return $query['time_ms'] >= $threshold;
        });
    }

    /**
     * Get queries with N+1 issues from current request.
     */
    public function getRequestNPlusOneQueries(): array
    {
        return array_filter($this->requestQueries, function ($query) {
            return isset($query['n_plus_one']);
        });
    }

    /**
     * Clear request queries (call at end of request).
     */
    public function clearRequest(): void
    {
        $this->requestQueries = [];
    }

    /**
     * Get summary of current request queries.
     */
    public function getRequestSummary(): array
    {
        $slowQueries = $this->getRequestSlowQueries();
        $nPlusOneQueries = $this->getRequestNPlusOneQueries();

        return [
            'total_queries' => $this->getRequestQueryCount(),
            'total_time_ms' => round($this->getRequestTotalTime(), 2),
            'slow_queries_count' => count($slowQueries),
            'n_plus_one_count' => count($nPlusOneQueries),
            'slow_queries' => array_map(function ($query) {
                return [
                    'sql' => $query['sql'],
                    'time_ms' => $query['time_ms'],
                    'formatted_sql' => $query['formatted_sql'] ?? null,
                    'explain' => $query['explain'] ?? null,
                ];
            }, array_values($slowQueries)),
            'n_plus_one_patterns' => array_map(function ($query) {
                return $query['n_plus_one'];
            }, array_values($nPlusOneQueries)),
            'queries' => $this->requestQueries,
        ];
    }
}
