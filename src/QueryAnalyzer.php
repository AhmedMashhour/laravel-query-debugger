<?php

namespace QueryDebugger;

use Illuminate\Support\Facades\DB;
use QueryDebugger\Support\AlertManager;
use QueryDebugger\Support\QueryFormatter;

class QueryAnalyzer
{
    protected QueryFormatter $formatter;

    protected AlertManager $alertManager;

    protected array $queryPatterns = [];

    protected float $requestStartTime;

    public function __construct(QueryFormatter $formatter, AlertManager $alertManager)
    {
        $this->formatter = $formatter;
        $this->alertManager = $alertManager;
        $this->requestStartTime = microtime(true);
    }

    /**
     * Analyze a query for issues (slow, N+1, etc.).
     */
    public function analyze(array $queryData): array
    {
        $issues = [];

        // Check for slow query
        $isSlowQuery = $this->isSlowQuery($queryData['time_ms']);
        if ($isSlowQuery) {
            $issues[] = 'slow_query';
            $queryData['slow_query'] = true;
            $this->alertManager->alertSlowQuery($queryData);
        } else {
            $queryData['slow_query'] = false;
        }

        // Auto EXPLAIN if configured (for slow queries or all queries)
        $shouldExplain = ($isSlowQuery && config('query-debugger.analyze_queries', false))
            || config('query-debugger.analyze_all_queries', false);

        if ($shouldExplain) {
            $queryData['explain'] = $this->explainQuery(
                $queryData['sql'],
                $queryData['bindings'],
                $queryData['connection']
            );
        }

        // Auto EXPLAIN ANALYZE if configured (for slow queries or all queries)
        $shouldExplainAnalyze = ($isSlowQuery && config('query-debugger.explain_analyze', false))
            || config('query-debugger.explain_analyze_all_queries', false);

        if ($shouldExplainAnalyze) {
            $queryData['explain_analyze'] = $this->explainAnalyzeQuery(
                $queryData['sql'],
                $queryData['bindings'],
                $queryData['connection']
            );
        }

        // Check for N+1 pattern
        $nPlusOneResult = $this->detectNPlusOne($queryData);
        if ($nPlusOneResult) {
            $issues[] = 'n_plus_one';
            $queryData['n_plus_one'] = $nPlusOneResult;
        }

        $queryData['issues'] = $issues;

        return $queryData;
    }

    /**
     * Check if query is slow based on threshold.
     */
    protected function isSlowQuery(float $timeMs): bool
    {
        $threshold = config('query-debugger.slow_query_threshold', 100);

        return $timeMs >= $threshold;
    }

    /**
     * Detect N+1 query pattern.
     */
    protected function detectNPlusOne(array $queryData): ?array
    {
        if (! config('query-debugger.n_plus_one_detection.enabled', true)) {
            return null;
        }

        $hash = $this->formatter->hash($queryData['sql']);

        if (! isset($this->queryPatterns[$hash])) {
            $this->queryPatterns[$hash] = [
                'sql' => $queryData['sql'],
                'normalized' => $this->formatter->normalize($queryData['sql']),
                'executions' => [],
                'count' => 0,
            ];
        }

        $pattern = &$this->queryPatterns[$hash];
        $pattern['executions'][] = [
            'bindings' => $queryData['bindings'],
            'time' => microtime(true),
            'backtrace' => $queryData['backtrace'] ?? [],
        ];
        $pattern['count']++;

        // Check N+1 threshold
        $threshold = config('query-debugger.n_plus_one_detection.threshold', 3);
        $timeWindow = config('query-debugger.n_plus_one_detection.time_window_ms', 100) / 1000;

        if ($pattern['count'] >= $threshold) {
            $firstTime = $pattern['executions'][0]['time'];
            $lastTime = end($pattern['executions'])['time'];

            // Check if all executions within time window
            if (($lastTime - $firstTime) <= $timeWindow) {
                // Check if bindings differ (different parameter values)
                $uniqueBindings = $this->getUniqueBindingSets($pattern['executions']);

                if (count($uniqueBindings) >= 2) {
                    $nPlusOneData = [
                        'query_pattern' => $pattern['normalized'],
                        'count' => $pattern['count'],
                        'route' => $queryData['route'] ?? 'unknown',
                        'location' => $this->findCommonCodePath($pattern['executions']),
                        'suggestion' => $this->suggestEagerLoading($queryData),
                    ];

                    // Alert only once per pattern
                    if ($pattern['count'] === $threshold) {
                        $this->alertManager->alertNPlusOne($nPlusOneData);
                    }

                    return $nPlusOneData;
                }
            }
        }

        return null;
    }

    /**
     * Get unique binding sets from executions.
     */
    protected function getUniqueBindingSets(array $executions): array
    {
        $unique = [];

        foreach ($executions as $execution) {
            $key = json_encode($execution['bindings']);
            $unique[$key] = true;
        }

        return array_keys($unique);
    }

    /**
     * Find common code path from backtraces.
     */
    protected function findCommonCodePath(array $executions): ?string
    {
        if (empty($executions) || empty($executions[0]['backtrace'])) {
            return null;
        }

        // Find first Repository or Service in backtrace
        foreach ($executions[0]['backtrace'] as $frame) {
            if (isset($frame['class']) && (
                str_contains($frame['class'], '\\Repositories\\') ||
                str_contains($frame['class'], '\\Services\\')
            )) {
                return $frame['class'].'::'.$frame['function'].' ('.$frame['file'].':'.$frame['line'].')';
            }
        }

        return null;
    }

    /**
     * Suggest eager loading solution for N+1.
     */
    protected function suggestEagerLoading(array $queryData): string
    {
        // Try to detect relationship from SQL
        if (preg_match('/FROM `(\w+)`/i', $queryData['sql'], $matches)) {
            $table = $matches[1];

            return "Consider using eager loading: Model::with('{$table}')->get()";
        }

        return 'Consider using eager loading to reduce queries';
    }

    /**
     * Execute EXPLAIN ANALYZE on query.
     */
    protected function explainQuery(string $sql, array $bindings, string $connection): ?array
    {
        // Skip if SQL already starts with EXPLAIN to avoid double EXPLAIN
        $sqlUpper = strtoupper(trim($sql));
        if (str_starts_with($sqlUpper, 'EXPLAIN')) {
            return null;
        }

        try {
            $explainSql = 'EXPLAIN '.$sql;
            $result = DB::connection($connection)->select($explainSql, $bindings);

            return json_decode(json_encode($result), true);
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run EXPLAIN ANALYZE to get detailed execution statistics.
     * This actually executes the query and provides real timing/cost data.
     */
    protected function explainAnalyzeQuery(string $sql, array $bindings, string $connection): ?array
    {
        // Skip if SQL already starts with EXPLAIN to avoid double EXPLAIN
        $sqlUpper = strtoupper(trim($sql));
        if (str_starts_with($sqlUpper, 'EXPLAIN')) {
            return null;
        }

        try {
            // MySQL 8.0+ supports EXPLAIN ANALYZE with FORMAT=JSON for detailed output
            // For other versions, fall back to EXPLAIN ANALYZE (text format)
            $explainSql = 'EXPLAIN ANALYZE FORMAT=JSON '.$sql;
            $result = DB::connection($connection)->select($explainSql, $bindings);

            // Parse JSON result from MySQL
            if (! empty($result) && isset($result[0]->{'EXPLAIN'})) {
                return json_decode($result[0]->{'EXPLAIN'}, true);
            }

            // Fallback: Return raw result
            return json_decode(json_encode($result), true);
        } catch (\Exception $e) {
            // If FORMAT=JSON not supported, try plain EXPLAIN ANALYZE
            try {
                $explainSql = 'EXPLAIN ANALYZE '.$sql;
                $result = DB::connection($connection)->select($explainSql, $bindings);

                return [
                    'format' => 'text',
                    'output' => json_decode(json_encode($result), true),
                ];
            } catch (\Exception $fallbackError) {
                return [
                    'error' => $e->getMessage(),
                    'fallback_error' => $fallbackError->getMessage(),
                ];
            }
        }
    }

    /**
     * Reset analyzer state for new request.
     */
    public function reset(): void
    {
        $this->queryPatterns = [];
        $this->requestStartTime = microtime(true);
    }

    /**
     * Get detected N+1 patterns for current request.
     */
    public function getDetectedNPlusOnePatterns(): array
    {
        $patterns = [];
        $threshold = config('query-debugger.n_plus_one_detection.threshold', 3);

        foreach ($this->queryPatterns as $hash => $pattern) {
            if ($pattern['count'] >= $threshold) {
                $patterns[] = [
                    'query_pattern' => $pattern['normalized'],
                    'count' => $pattern['count'],
                    'executions' => count($pattern['executions']),
                ];
            }
        }

        return $patterns;
    }
}
