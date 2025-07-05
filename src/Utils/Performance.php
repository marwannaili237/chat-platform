<?php

namespace App\Utils;

class Performance
{
    private static $cache = [];
    private static $cacheDir = null;

    public static function init()
    {
        self::$cacheDir = __DIR__ . '/../../storage/cache/';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * Simple in-memory cache
     */
    public static function cache($key, $value = null, $ttl = 3600)
    {
        if ($value === null) {
            // Get from cache
            if (isset(self::$cache[$key])) {
                $item = self::$cache[$key];
                if ($item['expires'] > time()) {
                    return $item['value'];
                } else {
                    unset(self::$cache[$key]);
                }
            }
            return null;
        } else {
            // Set cache
            self::$cache[$key] = [
                'value' => $value,
                'expires' => time() + $ttl
            ];
            return $value;
        }
    }

    /**
     * File-based cache for persistent data
     */
    public static function fileCache($key, $value = null, $ttl = 3600)
    {
        self::init();
        $filename = self::$cacheDir . md5($key) . '.cache';

        if ($value === null) {
            // Get from file cache
            if (file_exists($filename)) {
                $data = unserialize(file_get_contents($filename));
                if ($data['expires'] > time()) {
                    return $data['value'];
                } else {
                    unlink($filename);
                }
            }
            return null;
        } else {
            // Set file cache
            $data = [
                'value' => $value,
                'expires' => time() + $ttl
            ];
            file_put_contents($filename, serialize($data));
            return $value;
        }
    }

    /**
     * Clear expired cache entries
     */
    public static function clearExpiredCache()
    {
        // Clear memory cache
        foreach (self::$cache as $key => $item) {
            if ($item['expires'] <= time()) {
                unset(self::$cache[$key]);
            }
        }

        // Clear file cache
        self::init();
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] <= time()) {
                unlink($file);
            }
        }
    }

    /**
     * Optimize database queries with prepared statement caching
     */
    public static function optimizeQuery($sql, $params = [])
    {
        $cacheKey = 'query_' . md5($sql . serialize($params));
        
        // Check if result is cached
        $cached = self::cache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Execute query and cache result
        $db = \App\Models\Database::getInstance();
        $stmt = $db->query($sql, $params);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Cache for 5 minutes for frequently accessed data
        self::cache($cacheKey, $result, 300);
        
        return $result;
    }

    /**
     * Compress output for better bandwidth usage
     */
    public static function enableCompression()
    {
        if (!headers_sent() && extension_loaded('zlib')) {
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                ob_start('ob_gzhandler');
            }
        }
    }

    /**
     * Minify CSS content
     */
    public static function minifyCSS($css)
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace(['; ', ' {', '{ ', ' }', '} ', ': ', ', '], [';', '{', '{', '}', '}', ':', ','], $css);
        
        return trim($css);
    }

    /**
     * Minify JavaScript content
     */
    public static function minifyJS($js)
    {
        // Remove single-line comments (but preserve URLs)
        $js = preg_replace('/(?<!:)\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remove unnecessary whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        $js = str_replace(['; ', ' {', '{ ', ' }', '} ', ' (', '( ', ' )', ') ', ' =', '= ', ' +', '+ ', ' -', '- '], [';', '{', '{', '}', '}', '(', '(', ')', ')', '=', '=', '+', '+', '-', '-'], $js);
        
        return trim($js);
    }

    /**
     * Lazy load images with placeholder
     */
    public static function lazyLoadImage($src, $alt = '', $class = '', $placeholder = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIwIiBoZWlnaHQ9IjI0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5Mb2FkaW5nLi4uPC90ZXh0Pjwvc3ZnPg==')
    {
        return sprintf(
            '<img src="%s" data-src="%s" alt="%s" class="lazy-load %s" loading="lazy">',
            htmlspecialchars($placeholder),
            htmlspecialchars($src),
            htmlspecialchars($alt),
            htmlspecialchars($class)
        );
    }

    /**
     * Generate optimized WebSocket payload
     */
    public static function optimizeWebSocketPayload($data)
    {
        // Remove unnecessary fields
        $optimized = [];
        
        foreach ($data as $key => $value) {
            // Skip null values and empty strings
            if ($value !== null && $value !== '') {
                $optimized[$key] = $value;
            }
        }

        // Use shorter field names for frequently sent data
        $fieldMap = [
            'message_id' => 'id',
            'user_id' => 'uid',
            'username' => 'u',
            'content' => 'c',
            'created_at' => 't',
            'message_type' => 'mt'
        ];

        $compressed = [];
        foreach ($optimized as $key => $value) {
            $newKey = $fieldMap[$key] ?? $key;
            $compressed[$newKey] = $value;
        }

        return $compressed;
    }

    /**
     * Batch database operations for better performance
     */
    public static function batchInsert($table, $data, $batchSize = 100)
    {
        if (empty($data)) {
            return;
        }

        $db = \App\Models\Database::getInstance();
        $chunks = array_chunk($data, $batchSize);
        
        foreach ($chunks as $chunk) {
            $db->beginTransaction();
            try {
                foreach ($chunk as $row) {
                    $columns = array_keys($row);
                    $placeholders = ':' . implode(', :', $columns);
                    $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
                    $db->query($sql, $row);
                }
                $db->commit();
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
    }

    /**
     * Monitor performance metrics
     */
    public static function startTimer($name)
    {
        self::$cache["timer_{$name}"] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage()
        ];
    }

    public static function endTimer($name)
    {
        if (!isset(self::$cache["timer_{$name}"])) {
            return null;
        }

        $timer = self::$cache["timer_{$name}"];
        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $metrics = [
            'execution_time' => $endTime - $timer['start'],
            'memory_used' => $endMemory - $timer['memory_start'],
            'peak_memory' => memory_get_peak_usage()
        ];

        unset(self::$cache["timer_{$name}"]);
        return $metrics;
    }

    /**
     * Log performance metrics
     */
    public static function logMetrics($name, $metrics)
    {
        $logFile = __DIR__ . '/../../storage/performance.log';
        $logEntry = sprintf(
            "[%s] %s - Time: %.4fs, Memory: %s, Peak: %s\n",
            date('Y-m-d H:i:s'),
            $name,
            $metrics['execution_time'],
            self::formatBytes($metrics['memory_used']),
            self::formatBytes($metrics['peak_memory'])
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Format bytes for human reading
     */
    private static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Clean up old performance logs
     */
    public static function cleanupLogs($days = 7)
    {
        $logFile = __DIR__ . '/../../storage/performance.log';
        if (!file_exists($logFile)) {
            return;
        }

        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $lines = file($logFile);
        $newLines = [];

        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($logTime >= $cutoffTime) {
                    $newLines[] = $line;
                }
            }
        }

        file_put_contents($logFile, implode('', $newLines));
    }
}

