<?php

namespace QueryDebugger\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertManager
{
    /**
     * Alert for slow query detected.
     */
    public function alertSlowQuery(array $queryData): void
    {
        if (! config('query-debugger.alerts.enabled', false)) {
            return;
        }

        if (! config('query-debugger.alerts.conditions.slow_query', true)) {
            return;
        }

        $this->sendAlert('Slow Query Detected', [
            'type' => 'slow_query',
            'sql' => $queryData['sql'],
            'time_ms' => $queryData['time_ms'],
            'route' => $queryData['route'] ?? 'unknown',
            'connection' => $queryData['connection'] ?? 'unknown',
            'backtrace' => $queryData['backtrace'] ?? [],
            'explain' => $queryData['explain'] ?? null,
        ]);
    }

    /**
     * Alert for N+1 query detected.
     */
    public function alertNPlusOne(array $nPlusOneData): void
    {
        if (! config('query-debugger.alerts.enabled', false)) {
            return;
        }

        if (! config('query-debugger.alerts.conditions.n_plus_one', true)) {
            return;
        }

        $this->sendAlert('N+1 Query Detected', [
            'type' => 'n_plus_one',
            'query_pattern' => $nPlusOneData['query_pattern'],
            'count' => $nPlusOneData['count'],
            'route' => $nPlusOneData['route'] ?? 'unknown',
            'suggestion' => $nPlusOneData['suggestion'] ?? 'Use eager loading',
            'location' => $nPlusOneData['location'] ?? null,
        ]);
    }

    /**
     * Alert for high query count in request.
     */
    public function alertHighQueryCount(int $queryCount, string $route): void
    {
        if (! config('query-debugger.alerts.enabled', false)) {
            return;
        }

        $threshold = config('query-debugger.alerts.conditions.query_count_threshold', 50);

        if ($queryCount < $threshold) {
            return;
        }

        $this->sendAlert('High Query Count', [
            'type' => 'high_query_count',
            'count' => $queryCount,
            'threshold' => $threshold,
            'route' => $route,
            'message' => "Request generated {$queryCount} queries (threshold: {$threshold})",
        ]);
    }

    /**
     * Send alert to configured channels.
     */
    protected function sendAlert(string $title, array $data): void
    {
        $channels = config('query-debugger.alerts.channels', ['log']);

        foreach ($channels as $channel) {
            match ($channel) {
                'log' => $this->sendToLog($title, $data),
                'slack' => $this->sendToSlack($title, $data),
                default => null,
            };
        }
    }

    /**
     * Send alert to log channel.
     */
    protected function sendToLog(string $title, array $data): void
    {
        Log::warning('[Query Debugger] '.$title, $data);
    }

    /**
     * Send alert to Slack.
     */
    protected function sendToSlack(string $title, array $data): void
    {
        if (! config('query-debugger.alerts.slack.enabled', false)) {
            return;
        }

        $webhookUrl = config('query-debugger.alerts.slack.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        try {
            Http::post($webhookUrl, [
                'text' => $title,
                'blocks' => [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => '🔍 '.$title,
                        ],
                    ],
                    [
                        'type' => 'section',
                        'fields' => $this->formatDataForSlack($data),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[Query Debugger] Failed to send Slack alert', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Format data for Slack message.
     */
    protected function formatDataForSlack(array $data): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }

            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*{$key}:*\n".substr((string) $value, 0, 500),
            ];
        }

        return $fields;
    }
}
