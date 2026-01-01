<?php

namespace QueryDebugger;

class BacktraceCollector
{
    /**
     * Collect and filter backtrace for query debugging.
     */
    public function collect(int $limit = 10): array
    {
        if (! config('query-debugger.backtrace.enabled', true)) {
            return [];
        }

        $limit = config('query-debugger.backtrace.limit', $limit);
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);
        $filtered = [];

        foreach ($trace as $frame) {
            // Skip frames without file information
            if (! isset($frame['file'])) {
                continue;
            }

            // Skip excluded paths
            if ($this->shouldSkipFrame($frame)) {
                continue;
            }

            $filtered[] = [
                'file' => $this->relativePath($frame['file']),
                'line' => $frame['line'] ?? 0,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? null,
                'type' => $frame['type'] ?? null,
            ];

            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Find the source repository or service from backtrace.
     */
    public function findSourceClass(array $backtrace): ?string
    {
        foreach ($backtrace as $frame) {
            if (! isset($frame['class'])) {
                continue;
            }

            // Check for Repository or Service
            if (str_contains($frame['class'], '\\Repositories\\') ||
                str_contains($frame['class'], '\\Services\\')) {
                return $frame['class'].'::'.$frame['function'];
            }
        }

        return null;
    }

    /**
     * Check if frame should be skipped based on exclude paths.
     */
    protected function shouldSkipFrame(array $frame): bool
    {
        if (! isset($frame['file'])) {
            return true;
        }

        $excludePaths = config('query-debugger.backtrace.exclude_paths', [
            '/vendor/laravel/framework/',
            '/vendor/phpunit/',
        ]);

        foreach ($excludePaths as $excludePath) {
            if (str_contains($frame['file'], $excludePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert absolute path to relative path.
     */
    protected function relativePath(string $path): string
    {
        $basePath = base_path();

        if (str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath));
        }

        return $path;
    }
}
