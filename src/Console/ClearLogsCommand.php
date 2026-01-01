<?php

namespace QueryDebugger\Console;

use Illuminate\Console\Command;
use QueryDebugger\Storage\JsonFileStorage;

class ClearLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'query-debugger:clear {--days= : Number of days to keep (default: retention_days from config)}';

    /**
     * The console command description.
     */
    protected $description = 'Clear old query debugger logs based on retention policy';

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
        $days = $this->option('days');

        if ($days) {
            // Temporarily override config
            config(['query-debugger.storage.retention_days' => (int) $days]);
        }

        $this->info('Cleaning up old query debugger logs...');

        $deleted = $this->storage->cleanup();

        $this->info("Deleted {$deleted} log file(s).");

        return self::SUCCESS;
    }
}
