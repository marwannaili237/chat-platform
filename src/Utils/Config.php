<?php

namespace App\Utils;

class Config
{
    private static $config = [];
    private static $loaded = false;

    public static function load()
    {
        if (self::$loaded) {
            return;
        }

        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) {
                    continue;
                }
                list($key, $value) = explode('=', $line, 2);
                self::$config[trim($key)] = trim($value);
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null)
    {
        self::load();
        return self::$config[$key] ?? $default;
    }

    public static function set($key, $value)
    {
        self::load();
        self::$config[$key] = $value;
    }
}

