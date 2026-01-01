<?php

namespace QueryDebugger\Support;

class QueryFormatter
{
    /**
     * Format SQL query with bindings interpolated.
     */
    public function format(string $sql, array $bindings): string
    {
        if (empty($bindings)) {
            return $sql;
        }

        // Replace placeholders with actual values
        $formattedSql = str_replace(['%', '?'], ['%%', '%s'], $sql);

        $bindings = array_map(function ($binding) {
            if (is_null($binding)) {
                return 'NULL';
            }

            if (is_bool($binding)) {
                return $binding ? '1' : '0';
            }

            if (is_numeric($binding)) {
                return $binding;
            }

            // Escape and quote strings
            return "'".addslashes($binding)."'";
        }, $bindings);

        return vsprintf($formattedSql, $bindings);
    }

    /**
     * Normalize SQL query for pattern matching (remove specific values).
     */
    public function normalize(string $sql): string
    {
        // Replace numeric literals with placeholders
        $normalized = preg_replace('/\b\d+\b/', '?', $sql);

        // Replace string literals with placeholders
        $normalized = preg_replace("/'[^']*'/", '?', $normalized);

        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Calculate similarity between two SQL queries.
     */
    public function similarity(string $sql1, string $sql2): float
    {
        $norm1 = $this->normalize($sql1);
        $norm2 = $this->normalize($sql2);

        if ($norm1 === $norm2) {
            return 100.0;
        }

        // Use Levenshtein distance for similarity
        $maxLen = max(strlen($norm1), strlen($norm2));
        if ($maxLen === 0) {
            return 100.0;
        }

        $distance = levenshtein(substr($norm1, 0, 255), substr($norm2, 0, 255));

        return (1 - ($distance / $maxLen)) * 100;
    }

    /**
     * Generate hash for query pattern matching.
     */
    public function hash(string $sql): string
    {
        return md5($this->normalize($sql));
    }
}
