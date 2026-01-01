<?php

namespace QueryDebugger\Console;

use Illuminate\Console\Command;
use QueryDebugger\Storage\JsonFileStorage;

class AnalyzeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'query-debugger:analyze
                            {--date= : Specific date to analyze (Y-m-d format)}
                            {--slow : Show only slow queries}
                            {--n-plus-one : Show only N+1 queries}
                            {--limit=50 : Limit number of queries to display}';

    /**
     * The console command description.
     */
    protected $description = 'Analyze query debugger logs and display insights';

    protected JsonFileStorage $storage;

    public function __construct(JsonFileStorage $storage)
    {
        parent::__construct();
        $this->storage = $storage;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ?? now()->format('Y-m-d');
        $limit = (int) $this->option('limit');

        $this->info("Analyzing queries for {$date}...");

        $queries = $this->storage->read($date);

        if (empty($queries)) {
            $this->warn("No queries found for {$date}");

            return self::SUCCESS;
        }

        // Apply filters
        if ($this->option('slow')) {
            $threshold = config('query-debugger.slow_query_threshold', 100);
            $queries = array_filter($queries, fn ($q) => ($q['time_ms'] ?? 0) >= $threshold);
        }

        if ($this->option('n-plus-one')) {
            $queries = array_filter($queries, fn ($q) => isset($q['n_plus_one']));
        }

        // Limit results
        $queries = array_slice($queries, 0, $limit);

        // Display summary
        $this->displaySummary($queries);

        // Display slow queries
        if ($this->option('slow')) {
            $this->displaySlowQueries($queries);
        }

        // Display N+1 queries
        if ($this->option('n-plus-one')) {
            $this->displayNPlusOneQueries($queries);
        }

        return self::SUCCESS;
    }

    /**
     * Display summary statistics.
     */
    protected function displaySummary(array $queries): void
    {
        $totalQueries = count($queries);
        $totalTime = array_sum(array_column($queries, 'time_ms'));
        $avgTime = $totalQueries > 0 ? $totalTime / $totalQueries : 0;

        $slowQueries = array_filter($queries, fn ($q) => $q['slow_query'] ?? false);
        $nPlusOneQueries = array_filter($queries, fn ($q) => isset($q['n_plus_one']));

        $this->newLine();
        $this->line('=== Query Summary ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Queries', $totalQueries],
                ['Total Time', round($totalTime, 2).' ms'],
                ['Average Time', round($avgTime, 2).' ms'],
                ['Slow Queries', count($slowQueries)],
                ['N+1 Patterns', count($nPlusOneQueries)],
            ]
        );
    }

    /**
     * Display slow queries.
     */
    protected function displaySlowQueries(array $queries): void
    {
        $slowQueries = array_filter($queries, fn ($q) => $q['slow_query'] ?? false);

        if (empty($slowQueries)) {
            return;
        }

        $this->newLine();
        $this->line('=== Slow Queries ===');

        $rows = [];
        foreach ($slowQueries as $query) {
            $rows[] = [
                substr($query['sql'] ?? 'N/A', 0, 80),
                round($query['time_ms'] ?? 0, 2).' ms',
                $query['connection'] ?? 'unknown',
                $query['route'] ?? 'unknown',
            ];
        }

        $this->table(
            ['SQL (truncated)', 'Time', 'Connection', 'Route'],
            array_slice($rows, 0, 20)
        );

        if (count($rows) > 20) {
            $this->warn('Showing first 20 slow queries. Use --limit to see more.');
        }
    }

    /**
     * Display N+1 queries.
     */
    protected function displayNPlusOneQueries(array $queries): void
    {
        $nPlusOneQueries = array_filter($queries, fn ($q) => isset($q['n_plus_one']));

        if (empty($nPlusOneQueries)) {
            return;
        }

        $this->newLine();
        $this->line('=== N+1 Query Patterns ===');

        $rows = [];
        foreach ($nPlusOneQueries as $query) {
            $nPlusOne = $query['n_plus_one'];
            $rows[] = [
                substr($nPlusOne['query_pattern'] ?? 'N/A', 0, 60),
                $nPlusOne['count'] ?? 0,
                $query['route'] ?? 'unknown',
                $nPlusOne['suggestion'] ?? 'N/A',
            ];
        }

        $this->table(
            ['Query Pattern (truncated)', 'Count', 'Route', 'Suggestion'],
            array_slice($rows, 0, 20)
        );

        if (count($rows) > 20) {
            $this->warn('Showing first 20 N+1 patterns. Use --limit to see more.');
        }
    }
}
