<?php

class EnvLoader
{
    private static $env = [];
    private static $loaded = false;

    /**
     * Load environment variables from .env file
     */
    public static function load($filePath = null)
    {
        if (self::$loaded) {
            return;
        }

        if ($filePath === null) {
            $filePath = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($filePath)) {
            throw new Exception(".env file not found at: $filePath");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }

                self::$env[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$env[$key] ?? $default;
    }

    /**
     * Get all environment variables
     */
    public static function getAll()
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$env;
    }
}