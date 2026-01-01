<?php

namespace QueryDebugger;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use QueryDebugger\Console\AnalyzeCommand;
use QueryDebugger\Console\ClearLogsCommand;
use QueryDebugger\Middleware\InjectQueryDebugMiddleware;
use QueryDebugger\Storage\JsonFileStorage;
use QueryDebugger\Support\AlertManager;
use QueryDebugger\Support\QueryFormatter;

class QueryDebuggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/query-debugger.php',
            'query-debugger'
        );

        // Register core services as singletons
        $this->app->singleton(QueryFormatter::class);
        $this->app->singleton(BacktraceCollector::class);
        $this->app->singleton(JsonFileStorage::class);
        $this->app->singleton(AlertManager::class);

        $this->app->singleton(QueryAnalyzer::class, function ($app) {
            return new QueryAnalyzer(
                $app->make(QueryFormatter::class),
                $app->make(AlertManager::class)
            );
        });

        $this->app->singleton(QueryLogger::class, function ($app) {
            return new QueryLogger(
                $app->make(JsonFileStorage::class),
                $app->make(QueryFormatter::class)
            );
        });

        $this->app->singleton(QueryDebugger::class, function ($app) {
            return new QueryDebugger(
                $app->make(QueryLogger::class),
                $app->make(QueryAnalyzer::class),
                $app->make(BacktraceCollector::class),
                $app->make(AlertManager::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        // Always register configuration and commands
        $this->registerPublishing();
        $this->registerCommands();

        // Only set up tracking if enabled
        if (! $this->shouldRun()) {
            return;
        }

        // Set up query tracking
        $this->setupQueryTracking();

        // Register middleware for API response injection
        $this->registerMiddleware($router);
    }

    /**
     * Determine if query debugger should run.
     */
    protected function shouldRun(): bool
    {
        // Must be enabled in config
        if (! config('query-debugger.enabled', false)) {
            return false;
        }

        // Don't run in testing unless explicitly enabled
        if ($this->app->runningUnitTests() && ! config('query-debugger.enable_in_tests', false)) {
            return false;
        }

        return true;
    }

    /**
     * Set up query tracking via DB::listen().
     */
    protected function setupQueryTracking(): void
    {
        $debugger = $this->app->make(QueryDebugger::class);
        $connections = config('query-debugger.connections', ['mysql']);

        // Handle wildcard to track all connections
        if (in_array('*', $connections)) {
            $connections = array_keys(config('database.connections'));
        }

        foreach ($connections as $connection) {
            // Skip if connection doesn't exist
            if (! config("database.connections.{$connection}")) {
                continue;
            }

            try {
                DB::connection($connection)->listen(function (QueryExecuted $query) use ($debugger) {
                    // Apply sampling
                    if (! $this->shouldLogQuery()) {
                        return;
                    }

                    // Use the actual connection name from the event, not the closure variable
                    $debugger->trackQuery($query, $query->connectionName);
                });
            } catch (\Exception $e) {
                // Silently fail if connection not available
                // This prevents breaking the app if a connection is misconfigured
            }
        }
    }

    /**
     * Determine if query should be logged based on sampling rate.
     */
    protected function shouldLogQuery(): bool
    {
        $sampling = config('query-debugger.sampling', 100);

        if ($sampling >= 100) {
            return true;
        }

        return random_int(1, 100) <= $sampling;
    }

    /**
     * Register middleware for response injection.
     */
    protected function registerMiddleware(Router $router): void
    {
        if (! config('query-debugger.inject_in_response', false)) {
            return;
        }

        // Register middleware in api group after application is booted
        $this->app->booted(function () use ($router) {
            $router->pushMiddlewareToGroup('api', InjectQueryDebugMiddleware::class);
        });
    }

    /**
     * Register configuration publishing.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/query-debugger.php' => config_path('query-debugger.php'),
            ], 'query-debugger-config');
        }
    }

    /**
     * Register artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearLogsCommand::class,
                AnalyzeCommand::class,
            ]);
        }
    }
}
