<?php

namespace QueryDebugger\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QueryDebugger\QueryDebugger;
use Symfony\Component\HttpFoundation\Response;

class InjectQueryDebugMiddleware
{
    protected QueryDebugger $debugger;

    public function __construct(QueryDebugger $debugger)
    {
        $this->debugger = $debugger;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if we should inject debug info
        if (! $this->shouldInject($request)) {
            return $next($request);
        }

        // Start tracking with unique request ID
        $this->debugger->startRequest();

        $response = $next($request);

        // Only inject in JSON responses
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        // Get collected queries
        $queryData = $this->debugger->getCurrentRequestQueries();

        // Inject into response
        $content = $response->getData(true);
        $responseKey = config('query-debugger.response_key', '_query_debug');

        // Determine what to include
        if (config('query-debugger.include_full_queries_in_response', false)) {
            $content[$responseKey] = $queryData;
        } else {
            // Summary only
            $content[$responseKey] = [
                'total_queries' => $queryData['total_queries'],
                'total_time_ms' => $queryData['total_time_ms'],
                'slow_queries_count' => $queryData['slow_queries_count'],
                'n_plus_one_count' => $queryData['n_plus_one_count'],
                'slow_queries' => $queryData['slow_queries'],
                'n_plus_one_patterns' => $queryData['n_plus_one_patterns'],
            ];
        }

        $response->setData($content);

        // Add custom headers
        $response->header('X-Query-Count', $queryData['total_queries']);
        $response->header('X-Query-Time-Ms', $queryData['total_time_ms']);

        // Finish request tracking
        $this->debugger->finishRequest();

        return $response;
    }

    /**
     * Determine if query debug should be injected for this request.
     */
    protected function shouldInject(Request $request): bool
    {
        // Must be enabled in config
        if (! config('query-debugger.enabled', false)) {
            return false;
        }

        // Check if inject_in_response is enabled OR X-Query-Debug header is present
        $alwaysInject = config('query-debugger.inject_in_response', false);
        $headerActivated = $request->header('X-Query-Debug') === 'true';

        if (! $alwaysInject && ! $headerActivated) {
            return false;
        }

        // Only inject in local environment or when debug mode is on
        // This is a safety measure to prevent exposing in production
        if (! app()->environment('local') && ! config('app.debug')) {
            return false;
        }

        return true;
    }

    /**
     * Terminate middleware (cleanup).
     */
    public function terminate(Request $request, Response $response): void
    {
        // Finish request tracking if not already done
        try {
            $this->debugger->finishRequest();
        } catch (\Exception $e) {
            // Silently fail - don't interrupt request
        }
    }
}
