# Laravel Query Debugger

Automatic query tracking, analysis, and debugging for Laravel applications with N+1 detection, slow query monitoring, and performance insights.

## Features

- ✅ **Auto-Tracking**: Automatically track all database queries with zero code changes
- ✅ **Daily JSON Logs**: Store queries in JSON format with daily rotation
- ✅ **N+1 Detection**: Automatically detect and alert on N+1 query patterns
- ✅ **Slow Query Monitoring**: Flag queries exceeding configurable thresholds
- ✅ **EXPLAIN ANALYZE**: Auto-execute EXPLAIN for slow queries
- ✅ **Backtrace Collection**: Track where queries originate in your code
- ✅ **API Response Injection**: Optionally include query debug data in responses
- ✅ **Multi-Tenant Support**: Optional tenant context capture
- ✅ **Sampling**: Reduce overhead by sampling percentage of queries
- ✅ **Alert System**: Log, Slack notifications for query issues
- ✅ **Per-Route Configuration**: Different settings per endpoint
- ✅ **Artisan Commands**: Analyze and clean logs via CLI

## Installation

### Option 1: Install from GitHub (Recommended)

Add the package repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/AhmedMashhour/laravel-query-debugger.git"
        }
    ]
}
```

Then install via Composer:

```bash
composer require ahmedmashhour/laravel-query-debugger:dev-main
```

Or specify a specific version/tag:

```bash
composer require ahmedmashhour/laravel-query-debugger:^1.0
```

### Option 2: Install from Local Path

Add the package to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/query-debugger"
        }
    ],
    "require": {
        "ahmedmashhour/laravel-query-debugger": "dev-main"
    }
}
```

Run composer install:

```bash
composer require ahmedmashhour/laravel-query-debugger
```

### Step 2: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=query-debugger-config
```

### Step 3: Configure

Add to your `.env`:

```env
QUERY_DEBUG_ENABLED=true
QUERY_DEBUG_SLOW_THRESHOLD=100
QUERY_DEBUG_CONNECTIONS=mysql,tenant_db
QUERY_DEBUG_BACKTRACE=true
QUERY_DEBUG_N_PLUS_ONE=true
QUERY_DEBUG_INJECT_RESPONSE=false
QUERY_DEBUG_SAMPLING=100
```

## Configuration

### Quick Start - Key Environment Variables

```env
# Enable/Disable
QUERY_DEBUG_ENABLED=true

# API Response Injection
QUERY_DEBUG_INJECT_RESPONSE=true              # Show summary in responses
QUERY_DEBUG_FULL_QUERIES_IN_RESPONSE=true     # Show ALL queries (default: false)

# Analysis Features
QUERY_DEBUG_ANALYZE=true                      # Enable EXPLAIN for slow queries (default: false)
QUERY_DEBUG_ANALYZE_ALL=true                  # Enable EXPLAIN for ALL queries (default: false)
QUERY_DEBUG_EXPLAIN_ANALYZE=true              # Enable EXPLAIN ANALYZE for slow queries (default: false)
QUERY_DEBUG_EXPLAIN_ANALYZE_ALL=true          # Enable EXPLAIN ANALYZE for ALL queries (default: false)
QUERY_DEBUG_SLOW_THRESHOLD=100                # Slow query threshold in ms
QUERY_DEBUG_N_PLUS_ONE=true                   # Enable N+1 detection

# Performance
QUERY_DEBUG_BACKTRACE=true                    # Collect stack traces
QUERY_DEBUG_SAMPLING=100                      # Log X% of queries (100 = all)

# Connections
QUERY_DEBUG_CONNECTIONS=mysql,tenant_db       # Which connections to track
```

### Basic Settings

```php
'enabled' => env('QUERY_DEBUG_ENABLED', false),
'slow_query_threshold' => 100, // milliseconds
'connections' => ['mysql', 'tenant_db'], // or ['*'] for all
```

### Storage

```php
'storage' => [
    'path' => storage_path('logs/queries'),
    'rotation' => 'daily',
    'retention_days' => 7,
    'max_file_size_mb' => 50,
],
```

### N+1 Detection

```php
'n_plus_one_detection' => [
    'enabled' => true,
    'threshold' => 3, // min occurrences
    'time_window_ms' => 100,
],
```

### API Response Injection

```php
'inject_in_response' => false, // always inject
// Or use header: X-Query-Debug: true
```

### Alerts

```php
'alerts' => [
    'enabled' => true,
    'channels' => ['log', 'slack'],
    'slack' => [
        'webhook_url' => env('QUERY_DEBUG_SLACK_WEBHOOK'),
    ],
],
```

## Usage

### Enable for Development

```env
# Basic - Enable query debugging
QUERY_DEBUG_ENABLED=true

# Show summary in API responses
QUERY_DEBUG_INJECT_RESPONSE=true

# Show ALL queries in API responses (not just summary)
QUERY_DEBUG_FULL_QUERIES_IN_RESPONSE=true

# Enable EXPLAIN for slow queries (query execution plan)
QUERY_DEBUG_ANALYZE=true

# Enable EXPLAIN for ALL queries (not just slow ones)
QUERY_DEBUG_ANALYZE_ALL=true

# Enable EXPLAIN ANALYZE for slow queries (detailed execution stats with actual timing)
QUERY_DEBUG_EXPLAIN_ANALYZE=true

# Enable EXPLAIN ANALYZE for ALL queries (very high overhead!)
QUERY_DEBUG_EXPLAIN_ANALYZE_ALL=true

# Slow query threshold (milliseconds)
QUERY_DEBUG_SLOW_THRESHOLD=100

# Enable N+1 detection
QUERY_DEBUG_N_PLUS_ONE=true

# Enable backtrace
QUERY_DEBUG_BACKTRACE=true

# Sampling rate (100 = log all queries)
QUERY_DEBUG_SAMPLING=100
```

### Enable for Specific Request

```bash
curl -H "X-Query-Debug: true" https://api.example.com/orders
```

**Summary Response** (default - `QUERY_DEBUG_FULL_QUERIES_IN_RESPONSE=false`):

```json
{
    "data": { ... },
    "_query_debug": {
        "total_queries": 15,
        "total_time_ms": 45.67,
        "slow_queries_count": 2,
        "n_plus_one_count": 1,
        "slow_queries": [
          {
            "sql": "SELECT * FROM orders WHERE ...",
            "time_ms": 150.5,
            "formatted_sql": "SELECT * FROM orders WHERE id = 123",
            "explain": { ... }
          }
        ],
        "n_plus_one_patterns": [
          {
            "query_pattern": "SELECT * FROM items WHERE order_id = ?",
            "count": 10,
            "suggestion": "Use Order::with('items')->get()"
          }
        ]
    }
}
```

**Full Response** (with `QUERY_DEBUG_FULL_QUERIES_IN_RESPONSE=true`):

```json
{
    "data": { ... },
    "_query_debug": {
        "total_queries": 15,
        "total_time_ms": 45.67,
        "slow_queries_count": 2,
        "n_plus_one_count": 1,
        "slow_queries": [...],
        "n_plus_one_patterns": [...],
        "queries": [
          {
            "timestamp": "2026-01-01T15:30:45.123456Z",
            "sql": "SELECT * FROM users WHERE id = ?",
            "bindings": [123],
            "time_ms": 2.5,
            "connection": "mysql",
            "route": "GET /api/orders",
            "backtrace": [...],
            ...
          },
          ...all 15 queries...
        ]
    }
}
```

### Query Analysis: EXPLAIN vs EXPLAIN ANALYZE

The package supports two levels of query analysis for slow queries:

#### EXPLAIN (Execution Plan)
- **What it does**: Shows the query execution plan without running the query
- **Enable**: `QUERY_DEBUG_ANALYZE=true`
- **Output**: Tabular data showing indexes, join types, estimated rows
- **Overhead**: Low (~5-10ms per slow query)
- **Use when**: You want to understand the query plan and index usage

**Example output:**
```json
"explain": [
  {
    "id": 1,
    "select_type": "SIMPLE",
    "table": "orders",
    "type": "index_merge",
    "possible_keys": "orders_restaurant_id_foreign,orders_branch_id_foreign",
    "key": "orders_restaurant_id_foreign,orders_branch_id_foreign",
    "rows": 6463,
    "filtered": 99.99,
    "Extra": "Using intersect(...); Using where"
  }
]
```

#### EXPLAIN ANALYZE (Detailed Execution Stats)
- **What it does**: Actually executes the query and provides real timing/cost data
- **Enable**: `QUERY_DEBUG_EXPLAIN_ANALYZE=true`
- **Output**: JSON tree with actual timing, row counts, and costs for each operation
- **Overhead**: Higher (~20-50ms per slow query as it executes the query)
- **Use when**: You need detailed execution statistics and actual vs estimated comparisons

**Example output:**
```json
"explain_analyze": {
  "query_block": {
    "select_id": 1,
    "cost_info": {
      "query_cost": "1234.56"
    },
    "table": {
      "table_name": "orders",
      "access_type": "index_merge",
      "actual_rows": 6500,
      "filtered": 100.0,
      "cost_info": {
        "read_cost": "1000.00",
        "eval_cost": "234.56",
        "prefix_cost": "1234.56",
        "data_read_per_join": "5M"
      },
      "used_columns": [...],
      "index_merge": {
        "sort_union": [...]
      }
    }
  }
}
```

**Configuration Options:**

1. **Slow Queries Only** (Recommended)
   - `QUERY_DEBUG_ANALYZE=true` - EXPLAIN for slow queries
   - `QUERY_DEBUG_EXPLAIN_ANALYZE=true` - EXPLAIN ANALYZE for slow queries
   - **Use when**: You want to analyze only problematic queries
   - **Overhead**: Low-Medium

2. **All Queries** (Deep Debugging)
   - `QUERY_DEBUG_ANALYZE_ALL=true` - EXPLAIN for all queries
   - `QUERY_DEBUG_EXPLAIN_ANALYZE_ALL=true` - EXPLAIN ANALYZE for all queries
   - **Use when**: You need to analyze every single query in a request
   - **Overhead**: High-Very High (use sparingly!)

**Recommendation:**
- **Development**: Use `ANALYZE_ALL` or `EXPLAIN_ANALYZE_ALL` for targeted debugging of specific endpoints
- **Staging**: Use `ANALYZE=true` (slow queries only) to identify bottlenecks
- **Production**: Disable all analysis unless debugging critical issues

### View Logs

```bash
# View today's log
cat storage/logs/queries/queries-2026-01-01.json | jq

# Filter slow queries
cat storage/logs/queries/queries-2026-01-01.json | jq '.[] | select(.slow_query == true)'

# Count total queries
cat storage/logs/queries/queries-2026-01-01.json | jq 'length'

# Get queries from specific route
cat storage/logs/queries/queries-2026-01-01.json | jq '.[] | select(.route | contains("/api/users"))'
```

### Analyze Queries

```bash
# Analyze today's queries
php artisan query-debugger:analyze

# Analyze specific date
php artisan query-debugger:analyze --date=2026-01-01

# Show only slow queries
php artisan query-debugger:analyze --slow

# Show only N+1 patterns
php artisan query-debugger:analyze --n-plus-one
```

### Clean Old Logs

```bash
# Clean logs older than retention period
php artisan query-debugger:clear

# Keep last 3 days
php artisan query-debugger:clear --days=3
```

## JSON Log Format

Queries are stored as a JSON array with pretty formatting for easy reading:

```json
[
  {
    "timestamp": "2026-01-01T15:30:45.123456Z",
    "request_id": "req_abc123",
    "connection": "mysql",
    "sql": "select * from users where id = ?",
    "bindings": [123],
    "time_ms": 2.45,
    "slow_query": false,
    "route": "GET /api/users/123",
    "method": "GET",
    "user_id": "uuid",
    "tenant_id": "tenant-uuid",
    "ip": "192.168.1.1",
    "backtrace": [...],
    "explain": null,
    "query_hash": "md5_hash",
    "memory_mb": 45.2,
    "source": "App\\Repositories\\UserRepository::find",
    "n_plus_one": {...}
  },
  {
    "timestamp": "2026-01-01T15:30:46.789012Z",
    "request_id": "req_abc123",
    "connection": "mysql",
    "sql": "select * from posts where user_id = ?",
    "bindings": [123],
    "time_ms": 5.67,
    "slow_query": false,
    "route": "GET /api/users/123",
    "method": "GET",
    "user_id": "uuid",
    "ip": "192.168.1.1",
    ...
  }
]
```

## Per-Route Configuration

Override settings for specific routes:

```php
'route_config' => [
    'api/report/*' => [
        'slow_query_threshold' => 500, // higher for reports
        'n_plus_one_detection' => ['enabled' => false],
    ],
],
```

## Performance

### Overhead

- **Minimal mode** (sampling=10, backtrace=false): <1% overhead
- **Standard mode** (sampling=100, backtrace=true): <5% overhead
- **Full analysis** (explain=true): <20% overhead (dev only)

### Optimization Tips

1. Use sampling in production: `QUERY_DEBUG_SAMPLING=10`
2. Disable backtrace: `QUERY_DEBUG_BACKTRACE=false`
3. Disable EXPLAIN: `QUERY_DEBUG_ANALYZE=false`
4. Use exclude patterns for known queries
5. Enable only in local/staging environments

## Multi-Tenant Support

Optional tenant context capture for multi-tenant applications:

- Set `tenant_id` via `config('app.tenant_id')` in your application
- Or the package will try to read from `config('globals.tenant')->id` (if available)
- Enable in config: `'metadata' => ['tenant_id' => true]`

The package works with any Laravel application - multi-tenant or single-tenant!

## Advanced Features

### Exclude Patterns

Skip tracking specific queries:

```php
'exclude_patterns' => [
    '/^SELECT \* FROM `sessions`/i',
    '/^SELECT \* FROM `cache`/i',
    '/information_schema/i',
],
```

### Metadata Collection

Configure what metadata to collect:

```php
'metadata' => [
    'user_id' => true,
    'tenant_id' => true,
    'branch_id' => true,
    'restaurant_id' => true,
    'ip' => true,
    'user_agent' => false,
    'request_id' => true,
    'memory_usage' => true,
],
```

## Troubleshooting

### Queries Not Being Logged

1. Check `QUERY_DEBUG_ENABLED=true` in `.env`
2. Verify connection is in `connections` array
3. Check sampling rate: `QUERY_DEBUG_SAMPLING=100`
4. Look for exclude patterns matching your queries

### High Overhead

1. Reduce sampling: `QUERY_DEBUG_SAMPLING=10`
2. Disable backtrace: `QUERY_DEBUG_BACKTRACE=false`
3. Disable EXPLAIN: `QUERY_DEBUG_ANALYZE=false`
4. Add exclude patterns for frequent queries

### Logs Not Rotating

1. Check file permissions on `storage/logs/queries/`
2. Verify `max_file_size_mb` setting
3. Run `php artisan query-debugger:clear` manually

## License

MIT

## Support

For issues and questions, please open an issue on the GitHub repository.
