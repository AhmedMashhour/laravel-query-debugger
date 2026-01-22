<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Query Debugger Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable query debugging globally. When disabled, no queries
    | will be tracked and no overhead will be added to your application.
    |
    */
    'enabled' => env('QUERY_DEBUG_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Enable in Tests
    |--------------------------------------------------------------------------
    |
    | Whether to enable query debugging during PHPUnit tests.
    |
    */
    'enable_in_tests' => env('QUERY_DEBUG_ENABLE_IN_TESTS', false),

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configure how query logs are stored.
    | 'path' => directory for log files (default: storage/logs/queries)
    | 'rotation' => 'daily' | 'hourly' | 'weekly'
    | 'retention_days' => number of days to keep old logs
    | 'max_file_size_mb' => maximum file size before rotation (default: 50MB)
    |
    */
    'storage' => [
        'path' => env('QUERY_DEBUG_STORAGE_PATH', storage_path('logs/queries')),
        'rotation' => env('QUERY_DEBUG_ROTATION', 'daily'),
        'retention_days' => (int) env('QUERY_DEBUG_RETENTION_DAYS', 7),
        'max_file_size_mb' => (int) env('QUERY_DEBUG_MAX_FILE_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slow Query Threshold
    |--------------------------------------------------------------------------
    |
    | Queries taking longer than this threshold (in milliseconds) will be
    | flagged as slow queries and can trigger alerts.
    |
    */
    'slow_query_threshold' => (int) env('QUERY_DEBUG_SLOW_THRESHOLD', 100),

    /*
    |--------------------------------------------------------------------------
    | Tracked Connections
    |--------------------------------------------------------------------------
    |
    | Database connections to track. Use ['*'] to track all connections.
    | Example: ['mysql', 'tenant_db']
    |
    */
    'connections' => env('QUERY_DEBUG_CONNECTIONS') === '*'
        ? ['*']
        : (env('QUERY_DEBUG_CONNECTIONS')
            ? explode(',', env('QUERY_DEBUG_CONNECTIONS'))
            : ['mysql', 'tenant_db']),

    /*
    |--------------------------------------------------------------------------
    | Query Analysis
    |--------------------------------------------------------------------------
    |
    | Enable EXPLAIN for slow queries to get execution plans.
    | WARNING: This adds overhead. Use only in development/staging.
    |
    */
    'analyze_queries' => filter_var(env('QUERY_DEBUG_ANALYZE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Analyze All Queries
    |--------------------------------------------------------------------------
    |
    | Run EXPLAIN on ALL queries, not just slow ones.
    | WARNING: High overhead. Use only for deep debugging.
    |
    */
    'analyze_all_queries' => filter_var(env('QUERY_DEBUG_ANALYZE_ALL', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | EXPLAIN ANALYZE
    |--------------------------------------------------------------------------
    |
    | Enable EXPLAIN ANALYZE for slow queries to get detailed execution stats.
    | This actually executes the query and provides real timing/cost data.
    | WARNING: Higher overhead than EXPLAIN. Use only in development.
    |
    */
    'explain_analyze' => filter_var(env('QUERY_DEBUG_EXPLAIN_ANALYZE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | EXPLAIN ANALYZE All Queries
    |--------------------------------------------------------------------------
    |
    | Run EXPLAIN ANALYZE on ALL queries, not just slow ones.
    | WARNING: Very high overhead. Use only for targeted debugging.
    |
    */
    'explain_analyze_all_queries' => filter_var(env('QUERY_DEBUG_EXPLAIN_ANALYZE_ALL', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Backtrace Settings
    |--------------------------------------------------------------------------
    |
    | Capture stack traces to identify where queries originate.
    | 'enabled' => capture backtraces
    | 'limit' => maximum number of stack frames to capture
    | 'exclude_paths' => paths to exclude from backtrace
    |
    */
    'backtrace' => [
        'enabled' => env('QUERY_DEBUG_BACKTRACE', true),
        'limit' => (int) env('QUERY_DEBUG_BACKTRACE_LIMIT', 10),
        'exclude_paths' => [
            '/vendor/laravel/framework/',
            '/vendor/phpunit/',
            '/vendor/symfony/',
            '/vendor/doctrine/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Rate
    |--------------------------------------------------------------------------
    |
    | Sample queries at this percentage rate (1-100).
    | 100 = log all queries, 10 = log 10% of queries
    | Useful for reducing overhead in high-traffic production environments.
    |
    */
    'sampling' => (int) env('QUERY_DEBUG_SAMPLING', 100),

    /*
    |--------------------------------------------------------------------------
    | Per-Route Tracking
    |--------------------------------------------------------------------------
    |
    | Enable tracking queries per route/endpoint.
    | 'enabled' => track by route
    | 'include_routes' => specific routes to track (empty = all routes)
    | 'exclude_routes' => routes to exclude from tracking
    |
    */
    'route_tracking' => [
        'enabled' => env('QUERY_DEBUG_ROUTE_TRACKING', true),
        'include_routes' => [],
        'exclude_routes' => [
            'telescope.*',
            'horizon.*',
            'debugbar.*',
            '_debugbar/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | N+1 Query Detection
    |--------------------------------------------------------------------------
    |
    | Detect potential N+1 query problems.
    | 'enabled' => enable detection
    | 'threshold' => minimum number of similar queries to flag as N+1
    | 'time_window_ms' => time window to check for repeated queries (default: 100ms)
    | 'similarity_threshold' => percentage similarity to consider queries related
    |
    */
    'n_plus_one_detection' => [
        'enabled' => env('QUERY_DEBUG_N_PLUS_ONE', true),
        'threshold' => (int) env('QUERY_DEBUG_N_PLUS_ONE_THRESHOLD', 3),
        'time_window_ms' => (int) env('QUERY_DEBUG_N_PLUS_ONE_WINDOW', 100),
        'similarity_threshold' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Inject in API Response
    |--------------------------------------------------------------------------
    |
    | Add query debug information to API responses.
    | Only works when 'enabled' is true.
    | Can be activated per-request using 'X-Query-Debug: true' header.
    |
    */
    'inject_in_response' => env('QUERY_DEBUG_INJECT_RESPONSE', false),

    /*
    |--------------------------------------------------------------------------
    | Response Key
    |--------------------------------------------------------------------------
    |
    | The key to use when injecting debug info into API responses.
    |
    */
    'response_key' => env('QUERY_DEBUG_RESPONSE_KEY', '_query_debug'),

    /*
    |--------------------------------------------------------------------------
    | Include Full Queries in Response
    |--------------------------------------------------------------------------
    |
    | When injecting into response, include full query list or just summary.
    |
    */
    'include_full_queries_in_response' => env('QUERY_DEBUG_FULL_QUERIES_IN_RESPONSE', false),

    /*
    |--------------------------------------------------------------------------
    | Alert Settings
    |--------------------------------------------------------------------------
    |
    | Configure alerts for query issues.
    | 'enabled' => enable alerting
    | 'channels' => alert channels (log, slack)
    | 'conditions' => when to trigger alerts
    |
    */
    'alerts' => [
        'enabled' => env('QUERY_DEBUG_ALERTS', false),
        'channels' => ['log'], // Available: 'log', 'slack'

        'conditions' => [
            'slow_query' => true,
            'n_plus_one' => true,
            'query_count_threshold' => (int) env('QUERY_DEBUG_ALERT_COUNT_THRESHOLD', 50),
        ],

        'slack' => [
            'enabled' => env('QUERY_DEBUG_SLACK_ENABLED', false),
            'webhook_url' => env('QUERY_DEBUG_SLACK_WEBHOOK'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude Patterns
    |--------------------------------------------------------------------------
    |
    | Exclude queries matching these patterns from being logged.
    | Useful for excluding migrations, cache queries, etc.
    | Supports regular expressions.
    |
    */
    'exclude_patterns' => [
        '/^SHOW FULL COLUMNS FROM/i',
        '/^SHOW TABLES LIKE/i',
        '/^select \* from `migrations`/i',
        '/information_schema/i',
        '/^SELECT DATABASE\(\)/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Metadata
    |--------------------------------------------------------------------------
    |
    | Additional metadata to include with each query log.
    | Available: 'user_id', 'tenant_id', 'ip', 'user_agent',
    | 'request_id', 'memory_usage'
    |
    | Note: For multi-tenant apps, set tenant_id via config('app.tenant_id')
    | or the package will try to read from config('globals.tenant')->id
    |
    */
    'metadata' => [
        'user_id' => true,
        'tenant_id' => false,  // Enable for multi-tenant applications
        'ip' => true,
        'user_agent' => false,
        'request_id' => true,
        'memory_usage' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Route Configuration
    |--------------------------------------------------------------------------
    |
    | Override settings for specific routes. Use route names or patterns.
    |
    | Example:
    | 'route_config' => [
    |     'api/report/*' => [
    |         'slow_query_threshold' => 500,
    |         'query_count_threshold' => 100,
    |     ],
    | ],
    |
    */
    'route_config' => [
        // Example: Higher thresholds for report endpoints
        // 'api/report/*' => [
        //     'slow_query_threshold' => 500,
        //     'n_plus_one_detection' => ['enabled' => false],
        // ],
    ],
];
