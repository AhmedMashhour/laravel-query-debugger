<?php

namespace QueryDebugger\Storage;

use Illuminate\Support\Facades\File;

class JsonFileStorage
{
    protected string $basePath;

    protected int $maxFileSizeMb;

    protected int $retentionDays;

    public function __construct()
    {
        $this->basePath = config('query-debugger.storage.path', storage_path('logs/queries'));
        $this->maxFileSizeMb = config('query-debugger.storage.max_file_size_mb', 50);
        $this->retentionDays = config('query-debugger.storage.retention_days', 7);

        $this->ensureDirectoryExists();
    }

    /**
     * Store query data in JSON format.
     */
    public function store(array $queryData): void
    {
        $filePath = $this->getDailyLogPath();

        // Read existing data
        $existingQueries = [];
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if (! empty($content)) {
                $existingQueries = json_decode($content, true) ?? [];
            }
        }

        // Add new query
        $existingQueries[] = $queryData;

        // Write back as formatted JSON array
        $json = json_encode($existingQueries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($filePath, $json, LOCK_EX);

        // Check if rotation needed
        $this->rotateIfNeeded($filePath);
    }

    /**
     * Get current daily log file path.
     */
    public function getDailyLogPath(?string $date = null): string
    {
        $date = $date ?? now()->format('Y-m-d');

        return $this->basePath.'/queries-'.$date.'.json';
    }

    /**
     * Rotate file if it exceeds size limit.
     */
    protected function rotateIfNeeded(string $filePath): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $fileSizeMb = filesize($filePath) / 1024 / 1024;

        if ($fileSizeMb > $this->maxFileSizeMb) {
            $timestamp = now()->timestamp;
            $newPath = str_replace('.json', '-'.$timestamp.'.json', $filePath);

            rename($filePath, $newPath);
        }
    }

    /**
     * Clean old log files based on retention policy.
     */
    public function cleanup(): int
    {
        $deleted = 0;
        $cutoffDate = now()->subDays($this->retentionDays);

        $files = File::glob($this->basePath.'/queries-*.json');

        foreach ($files as $file) {
            $fileDate = $this->extractDateFromFilename($file);

            if ($fileDate && $fileDate->lt($cutoffDate)) {
                File::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Extract date from filename.
     */
    protected function extractDateFromFilename(string $filename): ?\Illuminate\Support\Carbon
    {
        // Extract YYYY-MM-DD pattern from filename
        if (preg_match('/queries-(\d{4}-\d{2}-\d{2})/', basename($filename), $matches)) {
            try {
                return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $matches[1]);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Ensure storage directory exists.
     */
    protected function ensureDirectoryExists(): void
    {
        if (! File::isDirectory($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true);
        }
    }

    /**
     * Read queries from log file.
     */
    public function read(?string $date = null, ?int $limit = null): array
    {
        $filePath = $this->getDailyLogPath($date);

        if (! file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if (empty($content)) {
            return [];
        }

        $queries = json_decode($content, true) ?? [];

        // Apply limit if specified
        if ($limit) {
            $queries = array_slice($queries, 0, $limit);
        }

        return $queries;
    }

    /**
     * Get all log files.
     */
    public function getLogFiles(): array
    {
        return File::glob($this->basePath.'/queries-*.json');
    }
}
