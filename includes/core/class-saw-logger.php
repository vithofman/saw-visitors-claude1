<?php
/**
 * SAW Logger Class
 *
 * Centralized logging service for the SAW Visitors plugin.
 * Wraps error_log and provides structured logging with levels.
 *
 * @package     SAW_Visitors
 * @subpackage  Core
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Logger {

    /**
     * Log levels
     */
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';

    /**
     * Log a message
     *
     * @param string $message The message to log
     * @param string $level The log level (default: INFO)
     * @param array $context Optional context data
     */
    public static function log($message, $level = self::LEVEL_INFO, $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Format message
        $formatted_message = sprintf(
            '[SAW][%s] %s',
            $level,
            $message
        );

        // Add context if provided
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . json_encode($context);
        }

        // Write to error_log (which goes to debug.log if WP_DEBUG_LOG is true)
        error_log($formatted_message);
    }

    /**
     * Log an error
     *
     * @param string $message
     * @param array $context
     */
    public static function error($message, $context = array()) {
        self::log($message, self::LEVEL_ERROR, $context);
    }

    /**
     * Log a warning
     *
     * @param string $message
     * @param array $context
     */
    public static function warning($message, $context = array()) {
        self::log($message, self::LEVEL_WARNING, $context);
    }

    /**
     * Log info
     *
     * @param string $message
     * @param array $context
     */
    public static function info($message, $context = array()) {
        self::log($message, self::LEVEL_INFO, $context);
    }

    /**
     * Log debug info
     *
     * @param string $message
     * @param array $context
     */
    public static function debug($message, $context = array()) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            self::log($message, self::LEVEL_DEBUG, $context);
        }
    }
}
