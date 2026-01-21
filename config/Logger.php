<?php

class Logger
{
    private static $logDir = null;
    private static $logFile = null;

    /**
     * Initialize logger with log directory and file
     */
    public static function init($logDir = null)
    {
        if ($logDir === null) {
            $logDir = dirname(__DIR__) . '/logs';
        }

        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        self::$logDir = $logDir;
        self::$logFile = $logDir . '/smtp_' . date('Y-m-d') . '.log';
    }

    /**
     * Log a message to the log file
     */
    public static function log($message, $level = 'INFO')
    {
        if (self::$logDir === null) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Append to log file
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Log an info message
     */
    public static function info($message)
    {
        self::log($message, 'INFO');
    }

    /**
     * Log a success message
     */
    public static function success($message)
    {
        self::log($message, 'SUCCESS');
    }

    /**
     * Log an error message
     */
    public static function error($message)
    {
        self::log($message, 'ERROR');
    }

    /**
     * Log a warning message
     */
    public static function warning($message)
    {
        self::log($message, 'WARNING');
    }

    /**
     * Get the current log file path
     */
    public static function getLogFile()
    {
        if (self::$logDir === null) {
            self::init();
        }

        return self::$logFile;
    }
}
