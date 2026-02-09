<?php
if (!defined('ABSPATH')) exit;

/**
 * Structured Logger
 *
 * Replaces scattered error_log() calls with severity levels,
 * context, and sensitive data sanitization.
 *
 * Usage:
 *   Rental_Gates_Logger::error('stripe', 'Webhook failed', ['event_id' => $id]);
 *   Rental_Gates_Logger::info('email', 'Sent', ['template' => $tpl]);
 *   Rental_Gates_Logger::debug('cache', 'Miss', ['key' => $k]);
 *
 * @package RentalGates
 * @since 2.42.0
 */
class Rental_Gates_Logger {

    const LEVEL_DEBUG   = 'DEBUG';
    const LEVEL_INFO    = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR   = 'ERROR';

    /**
     * Minimum level to log. Set via RENTAL_GATES_LOG_LEVEL constant or filter.
     */
    private static $min_level = null;

    /**
     * Level hierarchy for comparison.
     */
    private static $levels = array(
        'DEBUG'   => 0,
        'INFO'    => 1,
        'WARNING' => 2,
        'ERROR'   => 3,
    );

    /**
     * Fields that must be sanitized before logging.
     */
    private static $sensitive_keys = array(
        'email', 'user_email', 'to', 'from_email',
        'stripe_account_id', 'stripe_subscription_id', 'stripe_customer_id',
        'payment_intent_id', 'stripe_payment_intent_id', 'client_secret',
        'secret_key', 'webhook_secret', 'api_key', 'token', 'password',
    );

    // ─── Public API ─────────────────────────────────────────────

    public static function debug($channel, $message, $context = array()) {
        self::log(self::LEVEL_DEBUG, $channel, $message, $context);
    }

    public static function info($channel, $message, $context = array()) {
        self::log(self::LEVEL_INFO, $channel, $message, $context);
    }

    public static function warning($channel, $message, $context = array()) {
        self::log(self::LEVEL_WARNING, $channel, $message, $context);
    }

    public static function error($channel, $message, $context = array()) {
        self::log(self::LEVEL_ERROR, $channel, $message, $context);
    }

    // ─── Core ───────────────────────────────────────────────────

    /**
     * Write a log entry if the level meets the minimum threshold.
     *
     * @param string $level   One of DEBUG, INFO, WARNING, ERROR.
     * @param string $channel Subsystem name (stripe, email, db, cache, etc.).
     * @param string $message Human-readable description.
     * @param array  $context Key-value pairs with extra data.
     */
    public static function log($level, $channel, $message, $context = array()) {
        if (!self::should_log($level)) {
            return;
        }

        $context = self::sanitize_context($context);

        $entry = sprintf(
            'Rental Gates [%s] %s: %s%s',
            $level,
            strtoupper($channel),
            $message,
            !empty($context) ? ' | ' . self::format_context($context) : ''
        );

        error_log($entry);

        /**
         * Fires after a log entry is written.
         *
         * Allows external log aggregators (Datadog, Sentry, etc.)
         * to capture structured data.
         *
         * @param string $level   Log level.
         * @param string $channel Subsystem.
         * @param string $message Message.
         * @param array  $context Sanitized context.
         */
        do_action('rental_gates_log', $level, $channel, $message, $context);
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Check if a level should be logged based on threshold.
     */
    private static function should_log($level) {
        if (self::$min_level === null) {
            if (defined('RENTAL_GATES_LOG_LEVEL')) {
                self::$min_level = strtoupper(RENTAL_GATES_LOG_LEVEL);
            } else {
                self::$min_level = (defined('WP_DEBUG') && WP_DEBUG)
                    ? self::LEVEL_DEBUG
                    : self::LEVEL_WARNING;
            }
        }

        $threshold = self::$levels[self::$min_level] ?? 2;
        $current   = self::$levels[$level] ?? 0;

        return $current >= $threshold;
    }

    /**
     * Mask sensitive values in context array.
     */
    private static function sanitize_context($context) {
        foreach ($context as $key => &$value) {
            if (!is_string($value)) {
                continue;
            }

            $lower_key = strtolower($key);

            // Redact known sensitive keys
            if (in_array($lower_key, self::$sensitive_keys, true)) {
                $value = self::mask($value);
                continue;
            }

            // Redact anything that looks like an email
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $value = self::mask_email($value);
            }
        }

        return $context;
    }

    /**
     * Mask a string showing only the last 4 characters.
     */
    private static function mask($value) {
        $len = strlen($value);
        if ($len <= 4) {
            return '****';
        }
        return str_repeat('*', $len - 4) . substr($value, -4);
    }

    /**
     * Mask an email: j***@e***.com
     */
    private static function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }
        $local  = $parts[0];
        $domain = $parts[1];
        $dot    = strrpos($domain, '.');
        if ($dot === false) {
            return substr($local, 0, 1) . '***@***';
        }
        return substr($local, 0, 1) . '***@' . substr($domain, 0, 1) . '***' . substr($domain, $dot);
    }

    /**
     * Format context array as a loggable string.
     */
    private static function format_context($context) {
        $parts = array();
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $value = 'null';
            }
            $parts[] = $key . '=' . $value;
        }
        return implode(', ', $parts);
    }
}
