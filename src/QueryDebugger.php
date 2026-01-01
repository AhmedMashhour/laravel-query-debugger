<?php

namespace QueryDebugger;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use QueryDebugger\Support\AlertManager;

class QueryDebugger
{
    protected QueryLogger $logger;

    protected QueryAnalyzer $analyzer;

    protected BacktraceCollector $backtraceCollector;

    protected AlertManager $alertManager;

    protected ?string $requestId = null;

    protected array $requestMetadata = [];

    public function __construct(
        QueryLogger $logger,
        QueryAnalyzer $analyzer,
        BacktraceCollector $backtraceCollector,
        AlertManager $alertManager
    ) {
        $this->logger = $logger;
        $this->analyzer = $analyzer;
        $this->backtraceCollector = $backtraceCollector;
        $this->alertManager = $alertManager;
    }

    /**
     * Track a query execution.
     */
    public function trackQuery(QueryExecuted $query, string $connection): void
    {
        $queryData = [
            'timestamp' => now()->toIso8601String(),
            'request_id' => $this->getRequestId(),
            'connection' => $connection,
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time_ms' => round($query->time, 2),
        ];

        // Add request metadata
        $queryData = array_merge($queryData, $this->collectRequestMetadata());

        // Add backtrace if enabled
        if (config('query-debugger.backtrace.enabled', true)) {
            $queryData['backtrace'] = $this->backtraceCollector->collect();
            $queryData['source'] = $this->backtraceCollector->findSourceClass($queryData['backtrace']);
        }

        // Analyze query for issues
        $queryData = $this->analyzer->analyze($queryData);

        // Log query
        $this->logger->log($queryData);
    }

    /**
     * Start request tracking.
     */
    public function startRequest(?string $requestId = null): void
    {
        $this->requestId = $requestId ?? Str::uuid()->toString();
        $this->requestMetadata = [];
        $this->analyzer->reset();
        $this->logger->clearRequest();
    }

    /**
     * Get or generate request ID.
     */
    protected function getRequestId(): string
    {
        if (! $this->requestId) {
            $this->requestId = Str::uuid()->toString();
        }

        return $this->requestId;
    }

    /**
     * Collect request metadata (user, tenant, branch, etc.).
     */
    protected function collectRequestMetadata(): array
    {
        if (! empty($this->requestMetadata)) {
            return $this->requestMetadata;
        }

        $metadata = [];
        $metadataConfig = config('query-debugger.metadata', []);

        // Route information
        if (function_exists('request')) {
            $metadata['route'] = request()->route()?->getName() ?? request()->path();
            $metadata['method'] = request()->method();
        }

        // User ID
        if ($metadataConfig['user_id'] ?? true) {
            $metadata['user_id'] = auth()->id();
        }

        // Tenant ID (for multi-tenant applications)
        if ($metadataConfig['tenant_id'] ?? false) {
            $metadata['tenant_id'] = config('app.tenant_id') ?? config('globals.tenant')?->id;
        }

        // IP address
        if (($metadataConfig['ip'] ?? true) && function_exists('request')) {
            $metadata['ip'] = request()->ip();
        }

        // User agent
        if (($metadataConfig['user_agent'] ?? false) && function_exists('request')) {
            $metadata['user_agent'] = request()->userAgent();
        }

        // Memory usage
        if ($metadataConfig['memory_usage'] ?? true) {
            $metadata['memory_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        }

        // Cache in request
        $this->requestMetadata = $metadata;

        return $metadata;
    }

    /**
     * Get current request queries.
     */
    public function getCurrentRequestQueries(): array
    {
        return $this->logger->getRequestSummary();
    }

    /**
     * Finish request and alert if needed.
     */
    public function finishRequest(): void
    {
        $queryCount = $this->logger->getRequestQueryCount();
        $route = $this->requestMetadata['route'] ?? 'unknown';

        // Check for high query count
        $this->alertManager->alertHighQueryCount($queryCount, $route);

        // Clear request data
        $this->requestMetadata = [];
    }
}
