<?php
/**
 * Rental Gates AI Credits Manager
 * 
 * Comprehensive credit management for AI features including:
 * - Balance tracking (subscription, purchased, bonus)
 * - Credit deduction and refunds
 * - Purchase processing
 * - Transaction history
 * - Admin adjustments
 * 
 * @package RentalGates
 * @since 2.15.0
 */
if (!defined('ABSPATH'))
    exit;

class Rental_Gates_AI_Credits
{

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Credit costs per tool
     */
    const CREDIT_COSTS = array(
        'description' => 1,
        'marketing' => 1,
        'maintenance' => 1,
        'message' => 1,
        'insights' => 2,
        'screening' => 3,
    );

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        // Initialize hooks
        add_action('rental_gates_subscription_renewed', array($this, 'on_subscription_renewed'), 10, 2);
        add_action('rental_gates_plan_changed', array($this, 'on_plan_changed'), 10, 3);

        // Ensure tables exist (for upgrades from older versions)
        $this->ensure_tables_exist();
    }

    /**
     * Ensure AI credit tables exist
     */
    private function ensure_tables_exist()
    {
        global $wpdb;

        // Quick check - if balances table exists, assume all are created
        $table_name = $wpdb->prefix . 'rg_ai_credit_balances';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Tables don't exist, create them
            if (class_exists('Rental_Gates_Database')) {
                $db = new Rental_Gates_Database();
                $db->create_ai_credit_tables_if_needed();
            }
        }
    }

    // ==========================================
    // BALANCE QUERIES
    // ==========================================

    /**
     * Get complete credit balance for an organization
     * 
     * @param int $org_id Organization ID
     * @return array Credit balance details
     */
    public function get_balance($org_id = null)
    {
        if (!$org_id) {
            $org_id = Rental_Gates_Roles::get_organization_id();
        }

        $default = array(
            'total' => 0,
            'subscription' => 0,
            'purchased' => 0,
            'bonus' => 0,
            'plan_limit' => 0,
            'used_this_cycle' => 0,
            'cycle_start' => null,
            'cycle_end' => null,
            'days_until_refresh' => 0,
            'percentage_used' => 0,
            'status' => 'empty', // empty, low, warning, healthy
        );

        if (!$org_id) {
            return $default;
        }

        global $wpdb;

        if (!class_exists('Rental_Gates_Database')) {
            return $default;
        }

        $tables = Rental_Gates_Database::get_table_names();

        // Get organization and plan info
        $org = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, s.current_period_start, s.current_period_end, s.plan_slug
             FROM {$tables['organizations']} o
             LEFT JOIN {$tables['subscriptions']} s ON o.id = s.organization_id
             WHERE o.id = %d",
            $org_id
        ));

        if (!$org) {
            return $default;
        }

        // Get plan limits
        $plans = get_option('rental_gates_plans', array());
        $plan_id = $org->plan_slug ?? $org->plan_id ?? 'free';
        $plan = $plans[$plan_id] ?? array();
        $plan_limit = $plan['limits']['ai_credits'] ?? 0;

        // Check for credit balance table
        $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
        $balance_exists = $wpdb->get_var("SHOW TABLES LIKE '{$balance_table}'");

        if ($balance_exists) {
            // Use new balance system
            $balance = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$balance_table} WHERE organization_id = %d",
                $org_id
            ));

            if ($balance) {
                $subscription = intval($balance->subscription_credits);
                $purchased = intval($balance->purchased_credits);
                $bonus = intval($balance->bonus_credits);
                $cycle_start = $balance->cycle_start;
                $cycle_end = $balance->cycle_end;
            } else {
                // Initialize balance record
                $this->initialize_balance($org_id, $plan_limit);
                $subscription = $plan_limit;
                $purchased = 0;
                $bonus = 0;
                $cycle_start = $org->current_period_start;
                $cycle_end = $org->current_period_end;
            }
        } else {
            // Fallback to legacy calculation
            $cycle_start = $org->current_period_start ?: date('Y-m-01');
            $cycle_end = $org->current_period_end ?: date('Y-m-01', strtotime('+1 month'));

            // Check ai_usage table
            $usage_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tables['ai_usage']}'");

            if ($usage_table_exists) {
                $used = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(credits_used), 0) 
                     FROM {$tables['ai_usage']} 
                     WHERE organization_id = %d 
                     AND created_at >= %s",
                    $org_id,
                    $cycle_start ?: date('Y-m-01')
                ));
            } else {
                $used = 0;
            }

            $subscription = max(0, $plan_limit - intval($used));
            $purchased = 0;
            $bonus = 0;
        }

        $total = $subscription + $purchased + $bonus;

        // Calculate usage this cycle
        $used_this_cycle = $plan_limit - $subscription;
        if ($used_this_cycle < 0)
            $used_this_cycle = 0;

        // Calculate days until refresh
        $days_until_refresh = 0;
        if ($cycle_end) {
            $end_time = strtotime($cycle_end);
            $now = time();
            $days_until_refresh = max(0, ceil(($end_time - $now) / 86400));
        }

        // Calculate percentage and status
        $percentage_used = $plan_limit > 0 ? round(($used_this_cycle / $plan_limit) * 100) : 0;
        $percentage_remaining = 100 - $percentage_used;

        if ($total <= 0) {
            $status = 'empty';
        } elseif ($percentage_remaining <= 20) {
            $status = 'low';
        } elseif ($percentage_remaining <= 50) {
            $status = 'warning';
        } else {
            $status = 'healthy';
        }

        return array(
            'total' => $total,
            'subscription' => $subscription,
            'purchased' => $purchased,
            'bonus' => $bonus,
            'plan_limit' => $plan_limit,
            'used_this_cycle' => $used_this_cycle,
            'cycle_start' => $cycle_start,
            'cycle_end' => $cycle_end,
            'days_until_refresh' => $days_until_refresh,
            'percentage_used' => $percentage_used,
            'percentage_remaining' => $percentage_remaining,
            'status' => $status,
        );
    }

    /**
     * Check if organization has enough credits
     * 
     * @param int $org_id Organization ID
     * @param int $amount Credits needed
     * @return bool
     */
    public function has_credits($org_id = null, $amount = 1)
    {
        $balance = $this->get_balance($org_id);
        return $balance['total'] >= $amount;
    }

    /**
     * Get remaining credits (quick helper)
     * 
     * @param int $org_id Organization ID
     * @return int
     */
    public function get_remaining($org_id = null)
    {
        $balance = $this->get_balance($org_id);
        return $balance['total'];
    }

    /**
     * Get credit cost for a tool
     * 
     * @param string $tool Tool name
     * @return int
     */
    public static function get_cost($tool)
    {
        return self::CREDIT_COSTS[$tool] ?? 1;
    }

    // ==========================================
    // CREDIT OPERATIONS
    // ==========================================

    /**
     * Deduct credits for AI usage
     * 
     * @param int $org_id Organization ID
     * @param int $amount Credits to deduct
     * @param string $tool Tool name
     * @param int $reference_id Related record ID
     * @return bool|WP_Error
     */
    /**
     * Deduct credits atomically to prevent race conditions.
     *
     * Uses a transaction with SELECT FOR UPDATE to lock the row,
     * eliminating the TOCTOU window where concurrent requests
     * could overspend credits.
     *
     * @param int    $org_id       Organization ID
     * @param int    $amount       Credits to deduct
     * @param string $tool         Tool name (for logging)
     * @param int    $reference_id Related entity ID (optional)
     * @return true|WP_Error
     */
    public function deduct($org_id, $amount, $tool, $reference_id = null)
    {
        global $wpdb;

        if (!$org_id) {
            return new WP_Error('no_org', __('Organization not found', 'rental-gates'));
        }

        if ($amount <= 0) {
            return new WP_Error('invalid_amount', __('Invalid credit amount', 'rental-gates'));
        }

        $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
        $tables = Rental_Gates_Database::get_table_names();

        // Use a transaction with row-level locking
        $wpdb->query('START TRANSACTION');

        try {
            // Lock the row and get current balance
            $balance = $wpdb->get_row($wpdb->prepare(
                "SELECT subscription_credits, bonus_credits, purchased_credits
                 FROM {$balance_table}
                 WHERE organization_id = %d
                 FOR UPDATE",
                $org_id
            ));

            if (!$balance) {
                // Initialize balance if it doesn't exist
                $this->initialize_balance($org_id);
                $balance = $wpdb->get_row($wpdb->prepare(
                    "SELECT subscription_credits, bonus_credits, purchased_credits
                     FROM {$balance_table}
                     WHERE organization_id = %d
                     FOR UPDATE",
                    $org_id
                ));

                if (!$balance) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('balance_error', __('Could not initialize credit balance', 'rental-gates'));
                }
            }

            $total = intval($balance->subscription_credits)
                   + intval($balance->bonus_credits)
                   + intval($balance->purchased_credits);

            if ($total < $amount) {
                $wpdb->query('ROLLBACK');
                return new WP_Error(
                    'insufficient_credits',
                    sprintf(
                        __('Not enough AI credits. You need %d credit(s), but have %d.', 'rental-gates'),
                        $amount,
                        $total
                    ),
                    array('required' => $amount, 'available' => $total)
                );
            }

            // Calculate deductions (subscription first, then bonus, then purchased)
            $remaining = $amount;
            $sub_deduct = min($remaining, intval($balance->subscription_credits));
            $remaining -= $sub_deduct;

            $bonus_deduct = min($remaining, intval($balance->bonus_credits));
            $remaining -= $bonus_deduct;

            $purchased_deduct = min($remaining, intval($balance->purchased_credits));

            // Atomic update within the transaction (row is still locked)
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$balance_table} SET
                    subscription_credits = subscription_credits - %d,
                    bonus_credits = bonus_credits - %d,
                    purchased_credits = purchased_credits - %d,
                    updated_at = NOW()
                 WHERE organization_id = %d
                 AND subscription_credits >= %d
                 AND bonus_credits >= %d
                 AND purchased_credits >= %d",
                $sub_deduct,
                $bonus_deduct,
                $purchased_deduct,
                $org_id,
                $sub_deduct,
                $bonus_deduct,
                $purchased_deduct
            ));

            if ($updated === false || $updated === 0) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('deduction_failed', __('Credit deduction failed. Please try again.', 'rental-gates'));
            }

            // Log the transaction
            $transaction_table = $wpdb->prefix . 'rg_ai_credit_transactions';
            $txn_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$transaction_table}'");

            if ($txn_table_exists) {
                $wpdb->insert(
                    $transaction_table,
                    array(
                        'organization_id'  => $org_id,
                        'user_id'          => get_current_user_id(),
                        'type'             => 'deduction',
                        'amount'           => -$amount,
                        'tool'             => $tool,
                        'reference_id'     => $reference_id,
                        'balance_after'    => $total - $amount,
                        'description'      => sprintf('AI %s usage', $tool),
                        'created_at'       => current_time('mysql'),
                    ),
                    array('%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s')
                );
            }

            // Also log to ai_usage table for backward compatibility
            if (isset($tables['ai_usage'])) {
                $wpdb->insert(
                    $tables['ai_usage'],
                    array(
                        'organization_id' => $org_id,
                        'user_id'         => get_current_user_id(),
                        'tool'            => $tool,
                        'credits_used'    => $amount,
                        'reference_id'    => $reference_id,
                        'created_at'      => current_time('mysql'),
                    ),
                    array('%d', '%d', '%s', '%d', '%d', '%s')
                );
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            Rental_Gates_Logger::error('ai_credits', 'Credit deduction failed', array(
                'org_id' => $org_id,
                'amount' => $amount,
                'error'  => $e->getMessage(),
            ));
            return new WP_Error('deduction_error', $e->getMessage());
        }
    }

    /**
     * Add credits
     * 
     * @param int $org_id Organization ID
     * @param int $amount Credits to add
     * @param string $type 'subscription'|'purchased'|'bonus'|'admin'
     * @param string $description Description
     * @param array $meta Additional metadata
     * @return bool|WP_Error
     */
    public function add($org_id, $amount, $type = 'bonus', $description = '', $meta = array())
    {
        global $wpdb;

        if (!$org_id || $amount <= 0) {
            return new WP_Error('invalid_params', __('Invalid parameters', 'rental-gates'));
        }

        // Ensure tables exist
        $this->ensure_tables_exist();

        $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
        $balance_exists = $wpdb->get_var("SHOW TABLES LIKE '{$balance_table}'") === $balance_table;

        // Map type to credit pool
        $pool = 'bonus_credits';
        if ($type === 'subscription') {
            $pool = 'subscription_credits';
        } elseif ($type === 'purchased' || $type === 'purchase') {
            $pool = 'purchased_credits';
        }

        if ($balance_exists) {
            // Ensure balance record exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$balance_table} WHERE organization_id = %d",
                $org_id
            ));

            if (!$exists) {
                $this->initialize_balance($org_id, 0);
            }

            // Update balance
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$balance_table} SET 
                    {$pool} = {$pool} + %d,
                    updated_at = NOW()
                 WHERE organization_id = %d",
                $amount,
                $org_id
            ));

            Rental_Gates_Logger::debug('ai_credits', 'Credits updated', array('pool' => $pool, 'amount' => $amount, 'org_id' => $org_id, 'result' => ($result !== false ? 'success' : 'failed')));
        } else {
            Rental_Gates_Logger::error('ai_credits', 'Balance table does not exist, cannot add credits');
            return new WP_Error('no_table', __('Credit balance table not found', 'rental-gates'));
        }

        // Log transaction
        $credit_type = ($type === 'admin') ? 'bonus' : $type;
        if ($type === 'purchased' || $type === 'purchase') {
            $credit_type = 'purchased';
        }
        $this->log_transaction($org_id, $type, $amount, $credit_type, array_merge($meta, array(
            'description' => $description,
        )));

        return true;
    }

    /**
     * Refund credits (for failed AI calls)
     * 
     * @param int $org_id Organization ID
     * @param int $amount Credits to refund
     * @param string $reason Reason for refund
     * @return bool
     */
    public function refund($org_id, $amount, $reason = '')
    {
        return $this->add($org_id, $amount, 'bonus', 'Refund: ' . $reason, array(
            'refund' => true,
        ));
    }

    /**
     * Refresh subscription credits (on billing cycle)
     * 
     * @param int $org_id Organization ID
     * @param int $plan_credits Credits for the plan
     * @param string $cycle_end New cycle end date
     * @return bool
     */
    public function refresh_subscription($org_id, $plan_credits, $cycle_end = null)
    {
        global $wpdb;

        $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
        $balance_exists = $wpdb->get_var("SHOW TABLES LIKE '{$balance_table}'");

        if (!$cycle_end) {
            $cycle_end = date('Y-m-d H:i:s', strtotime('+1 month'));
        }

        $cycle_start = date('Y-m-d H:i:s');

        if ($balance_exists) {
            // Check for rollover
            $settings = get_option('rental_gates_ai_settings', array());
            $allow_rollover = !empty($settings['allow_rollover']);
            $max_rollover = intval($settings['max_rollover'] ?? 50);

            $current_balance = $this->get_balance($org_id);
            $rollover = 0;

            if ($allow_rollover && $current_balance['subscription'] > 0) {
                $rollover = min($current_balance['subscription'], $max_rollover);
            }

            // Update balance
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$balance_table} WHERE organization_id = %d",
                $org_id
            ));

            if ($exists) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$balance_table} SET 
                        subscription_credits = %d,
                        cycle_start = %s,
                        cycle_end = %s,
                        last_refresh = NOW(),
                        rollover_credits = %d,
                        updated_at = NOW()
                     WHERE organization_id = %d",
                    $plan_credits + $rollover,
                    $cycle_start,
                    $cycle_end,
                    $rollover,
                    $org_id
                ));
            } else {
                $this->initialize_balance($org_id, $plan_credits, $cycle_start, $cycle_end);
            }

            // Log rollover if any
            if ($rollover > 0) {
                $this->log_transaction($org_id, 'rollover', $rollover, 'subscription', array(
                    'from_previous_cycle' => true,
                ));
            }
        }

        // Log refresh
        $this->log_transaction($org_id, 'subscription_grant', $plan_credits, 'subscription', array(
            'cycle_start' => $cycle_start,
            'cycle_end' => $cycle_end,
        ));

        return true;
    }

    // ==========================================
    // TRANSACTIONS
    // ==========================================

    /**
     * Log a credit transaction
     * 
     * @param int $org_id Organization ID
     * @param string $type Transaction type
     * @param int $credits Credit amount (positive or negative)
     * @param string $credit_type Credit pool type
     * @param array $meta Additional metadata
     */
    private function log_transaction($org_id, $type, $credits, $credit_type = 'subscription', $meta = array())
    {
        global $wpdb;

        $transaction_table = $wpdb->prefix . 'rg_ai_credit_transactions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$transaction_table}'");

        if (!$table_exists) {
            return;
        }

        $balance = $this->get_balance($org_id);
        $balance_before = $balance['total'] - $credits; // Reverse the effect to get before
        $balance_after = $balance['total'];

        $wpdb->insert($transaction_table, array(
            'organization_id' => $org_id,
            'user_id' => get_current_user_id(),
            'type' => $type,
            'credits' => $credits,
            'credit_type' => $credit_type,
            'balance_before' => max(0, $balance_before),
            'balance_after' => $balance_after,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'description' => $meta['description'] ?? null,
            'meta_data' => !empty($meta) ? json_encode($meta) : null,
            'created_at' => current_time('mysql'),
        ));
    }

    /**
     * Get transaction history
     * 
     * @param int $org_id Organization ID
     * @param array $args Query arguments
     * @return array
     */
    public function get_transactions($org_id, $args = array())
    {
        global $wpdb;

        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'type' => null,
            'date_from' => null,
            'date_to' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $transaction_table = $wpdb->prefix . 'rg_ai_credit_transactions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$transaction_table}'");

        if (!$table_exists) {
            // Fallback to ai_usage table
            return $this->get_legacy_transactions($org_id, $args);
        }

        $where = array("organization_id = %d");
        $params = array($org_id);

        if ($args['type']) {
            $where[] = "type = %s";
            $params[] = $args['type'];
        }

        if ($args['date_from']) {
            $where[] = "created_at >= %s";
            $params[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = "created_at <= %s";
            $params[] = $args['date_to'];
        }

        $where_sql = implode(' AND ', $where);
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.display_name as user_name
             FROM {$transaction_table} t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             WHERE {$where_sql}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $params
        ), ARRAY_A);

        return $results ?: array();
    }

    /**
     * Get legacy transactions from ai_usage table
     */
    private function get_legacy_transactions($org_id, $args)
    {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $usage_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tables['ai_usage']}'");

        if (!$usage_table_exists) {
            return array();
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                id,
                organization_id,
                user_id,
                'usage' as type,
                -credits_used as credits,
                'subscription' as credit_type,
                tool as reference_type,
                id as reference_id,
                CONCAT('AI ', tool) as description,
                created_at
             FROM {$tables['ai_usage']}
             WHERE organization_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $org_id,
            $args['limit']
        ), ARRAY_A);

        return $results ?: array();
    }

    /**
     * Get usage breakdown by tool
     * 
     * @param int $org_id Organization ID
     * @param string $period 'month'|'week'|'all'
     * @return array
     */
    public function get_usage_breakdown($org_id, $period = 'month')
    {
        global $wpdb;

        if (!class_exists('Rental_Gates_Database')) {
            return array();
        }

        $tables = Rental_Gates_Database::get_table_names();

        $date_filter = "";
        if ($period === 'month') {
            $date_filter = "AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
        } elseif ($period === 'week') {
            $date_filter = "AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)";
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT tool, COUNT(*) as count, SUM(credits_used) as credits
             FROM {$tables['ai_usage']}
             WHERE organization_id = %d {$date_filter}
             GROUP BY tool
             ORDER BY credits DESC",
            $org_id
        ), ARRAY_A);

        return $results ?: array();
    }

    // ==========================================
    // ADMIN OPERATIONS
    // ==========================================

    /**
     * Admin: Manually adjust credits
     * 
     * @param int $org_id Organization ID
     * @param int $amount Credit amount (positive to add, negative to deduct)
     * @param string $reason Reason for adjustment
     * @param string $credit_type 'subscription'|'purchased'|'bonus'
     * @param int $admin_id Admin user ID
     * @return bool|WP_Error
     */
    public function admin_adjust($org_id, $amount, $reason = '', $credit_type = 'bonus', $admin_id = null)
    {
        if (!$admin_id) {
            $admin_id = get_current_user_id();
        }

        if ($amount > 0) {
            return $this->add($org_id, $amount, $credit_type, $reason, array(
                'admin_id' => $admin_id,
                'adjustment' => true,
            ));
        } else {
            // For deductions, we'll add a negative bonus (simpler than modifying deduct())
            global $wpdb;
            $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
            $balance_exists = $wpdb->get_var("SHOW TABLES LIKE '{$balance_table}'");

            $pool = 'bonus_credits';
            if ($credit_type === 'subscription') {
                $pool = 'subscription_credits';
            } elseif ($credit_type === 'purchased') {
                $pool = 'purchased_credits';
            }

            if ($balance_exists) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$balance_table} SET 
                        {$pool} = GREATEST(0, {$pool} + %d),
                        updated_at = NOW()
                     WHERE organization_id = %d",
                    $amount, // negative
                    $org_id
                ));
            }

            $this->log_transaction($org_id, 'admin_adjustment', $amount, $credit_type, array(
                'admin_id' => $admin_id,
                'reason' => $reason,
            ));

            return true;
        }
    }

    /**
     * Admin: Reset credits to plan allocation
     * 
     * @param int $org_id Organization ID
     * @param int $admin_id Admin user ID
     * @return bool
     */
    public function admin_reset($org_id, $admin_id = null)
    {
        global $wpdb;

        $tables = Rental_Gates_Database::get_table_names();

        // Get plan credits
        $org = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, s.plan_slug
             FROM {$tables['organizations']} o
             LEFT JOIN {$tables['subscriptions']} s ON o.id = s.organization_id
             WHERE o.id = %d",
            $org_id
        ));

        $plans = get_option('rental_gates_plans', array());
        $plan_id = $org->plan_slug ?? $org->plan_id ?? 'free';
        $plan = $plans[$plan_id] ?? array();
        $plan_credits = $plan['limits']['ai_credits'] ?? 0;

        // Reset balance
        $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
        $balance_exists = $wpdb->get_var("SHOW TABLES LIKE '{$balance_table}'");

        if ($balance_exists) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$balance_table} SET 
                    subscription_credits = %d,
                    updated_at = NOW()
                 WHERE organization_id = %d",
                $plan_credits,
                $org_id
            ));
        }

        $this->log_transaction($org_id, 'admin_adjustment', $plan_credits, 'subscription', array(
            'admin_id' => $admin_id ?: get_current_user_id(),
            'reason' => 'Admin reset to plan allocation',
            'reset' => true,
        ));

        return true;
    }

    // ==========================================
    // CREDIT PACKS
    // ==========================================

    /**
     * Get available credit packs
     * 
     * @return array
     */
    public function get_credit_packs()
    {
        global $wpdb;

        $packs_table = $wpdb->prefix . 'rg_ai_credit_packs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$packs_table}'");

        if ($table_exists) {
            $packs = $wpdb->get_results(
                "SELECT * FROM {$packs_table} WHERE is_active = 1 ORDER BY sort_order ASC",
                ARRAY_A
            );

            if (!empty($packs)) {
                return $packs;
            }
        }

        // Return default packs
        return $this->get_default_packs();
    }

    /**
     * Get default credit packs
     * 
     * @return array
     */
    public function get_default_packs()
    {
        return array(
            array(
                'id' => 'starter',
                'slug' => 'starter',
                'name' => __('Starter', 'rental-gates'),
                'credits' => 50,
                'price' => 9.99,
                'currency' => 'USD',
                'badge_text' => '',
                'is_featured' => false,
            ),
            array(
                'id' => 'value',
                'slug' => 'value',
                'name' => __('Value', 'rental-gates'),
                'credits' => 150,
                'price' => 24.99,
                'currency' => 'USD',
                'badge_text' => __('Popular', 'rental-gates'),
                'is_featured' => true,
            ),
            array(
                'id' => 'pro',
                'slug' => 'pro',
                'name' => __('Pro', 'rental-gates'),
                'credits' => 500,
                'price' => 69.99,
                'currency' => 'USD',
                'badge_text' => __('Best Value', 'rental-gates'),
                'is_featured' => false,
            ),
            array(
                'id' => 'enterprise',
                'slug' => 'enterprise',
                'name' => __('Enterprise', 'rental-gates'),
                'credits' => 2000,
                'price' => 199.99,
                'currency' => 'USD',
                'badge_text' => '',
                'is_featured' => false,
            ),
        );
    }

    /**
     * Create a purchase
     * 
     * @param int $org_id Organization ID
     * @param string $pack_id Pack ID
     * @return array|WP_Error
     */
    public function create_purchase($org_id, $pack_id)
    {
        $packs = $this->get_credit_packs();
        $pack = null;

        foreach ($packs as $p) {
            if ($p['id'] == $pack_id || $p['slug'] == $pack_id) {
                $pack = $p;
                break;
            }
        }

        if (!$pack) {
            return new WP_Error('invalid_pack', __('Invalid credit pack', 'rental-gates'));
        }

        // Create Stripe checkout session
        if (class_exists('Rental_Gates_Stripe') && Rental_Gates_Stripe::is_configured()) {
            $result = Rental_Gates_Stripe::create_credit_purchase_session($org_id, $pack);

            if (is_wp_error($result)) {
                return $result;
            }

            return array(
                'redirect_url' => $result['url'],
                'session_id' => $result['session_id'],
                'pack' => $pack,
            );
        }

        return new WP_Error('payment_unavailable', __('Payment processing not available. Please configure Stripe.', 'rental-gates'));
    }

    /**
     * Complete a purchase (called from webhook or redirect)
     * 
     * @param int $org_id Organization ID
     * @param int $credits Credits to add
     * @param string $payment_intent_id Stripe payment intent ID
     * @return bool
     */
    public function complete_purchase($org_id, $credits, $payment_intent_id)
    {
        global $wpdb;

        Rental_Gates_Logger::info('ai_credits', 'Starting purchase completion', array('org_id' => $org_id, 'credits' => $credits, 'payment_intent_id' => $payment_intent_id));

        // Record purchase
        $purchases_table = $wpdb->prefix . 'rg_ai_credit_purchases';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'");

        if ($table_exists) {
            // Check for duplicate
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$purchases_table} WHERE stripe_payment_intent_id = %s",
                $payment_intent_id
            ));

            if ($existing) {
                Rental_Gates_Logger::info('ai_credits', 'Purchase already completed (idempotency)', array('purchase_id' => $existing));
                return true;
            }

            $wpdb->insert($purchases_table, array(
                'organization_id' => $org_id,
                'user_id' => get_current_user_id(),
                'credits' => $credits,
                'status' => 'completed',
                'stripe_payment_intent_id' => $payment_intent_id,
                'created_at' => current_time('mysql'),
                'completed_at' => current_time('mysql'),
            ));

            $purchase_id = $wpdb->insert_id;
            Rental_Gates_Logger::info('ai_credits', 'Created purchase record', array('purchase_id' => $purchase_id));
        }

        // Add credits
        $result = $this->add($org_id, $credits, 'purchased', 'Credit pack purchase', array(
            'payment_intent_id' => $payment_intent_id,
        ));

        if ($result) {
            Rental_Gates_Logger::info('ai_credits', 'Credits added successfully', array('org_id' => $org_id, 'credits' => $credits));

            // Generate Invoice
            if (class_exists('Rental_Gates_Stripe') && class_exists('Rental_Gates_Subscription_Invoice')) {
                $pi = Rental_Gates_Stripe::api_request('payment_intents/' . $payment_intent_id);

                if (!is_wp_error($pi)) {
                    $amount = ($pi['amount_received'] ?? $pi['amount']) / 100;
                    $currency = strtoupper($pi['currency'] ?? 'USD');

                    $items = array(
                        array(
                            'description' => sprintf(__('AI Credits Purchase - %d Credits', 'rental-gates'), $credits),
                            'amount' => $amount,
                            'quantity' => 1
                        )
                    );

                    $invoice = Rental_Gates_Subscription_Invoice::create_from_payment($org_id, $items, $amount, $payment_intent_id);

                    if (is_wp_error($invoice)) {
                        Rental_Gates_Logger::error('ai_credits', 'Failed to create invoice', array('error' => $invoice->get_error_message()));
                    } else {
                        Rental_Gates_Logger::info('ai_credits', 'Created invoice', array('invoice_number' => $invoice['invoice_number']));
                    }
                }
            }
        } else {
            Rental_Gates_Logger::error('ai_credits', 'Failed to add credits', array('org_id' => $org_id));
        }

        return $result;
    }

    // ==========================================
    // INITIALIZATION & LIFECYCLE
    // ==========================================

    /**
     * Initialize balance record for organization
     * 
     * @param int $org_id Organization ID
     * @param int $subscription_credits Initial subscription credits
     * @param string $cycle_start Cycle start date
     * @param string $cycle_end Cycle end date
     */
    public function initialize_balance($org_id, $subscription_credits = 0, $cycle_start = null, $cycle_end = null)
    {
        global $wpdb;

        $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$balance_table}'");

        if (!$table_exists) {
            return;
        }

        if (!$cycle_start) {
            $cycle_start = date('Y-m-d H:i:s');
        }
        if (!$cycle_end) {
            $cycle_end = date('Y-m-d H:i:s', strtotime('+1 month'));
        }

        $wpdb->insert($balance_table, array(
            'organization_id' => $org_id,
            'subscription_credits' => $subscription_credits,
            'purchased_credits' => 0,
            'bonus_credits' => 0,
            'cycle_start' => $cycle_start,
            'cycle_end' => $cycle_end,
            'created_at' => current_time('mysql'),
        ));
    }

    /**
     * Hook: On subscription renewed
     */
    public function on_subscription_renewed($org_id, $subscription)
    {
        $plans = get_option('rental_gates_plans', array());
        $plan_id = $subscription->plan_slug ?? 'free';
        $plan = $plans[$plan_id] ?? array();
        $plan_credits = $plan['limits']['ai_credits'] ?? 0;

        $this->refresh_subscription($org_id, $plan_credits, $subscription->current_period_end);
    }

    /**
     * Hook: On plan changed
     */
    public function on_plan_changed($org_id, $old_plan, $new_plan)
    {
        // Prorate credits on upgrade
        $old_credits = $old_plan['limits']['ai_credits'] ?? 0;
        $new_credits = $new_plan['limits']['ai_credits'] ?? 0;

        if ($new_credits > $old_credits) {
            $bonus = $new_credits - $old_credits;
            $this->add($org_id, $bonus, 'bonus', 'Plan upgrade bonus');
        }
    }

    // ==========================================
    // ANALYTICS
    // ==========================================

    /**
     * Get platform-wide analytics (admin only)
     * 
     * @param string $period 'day'|'week'|'month'
     * @return array
     */
    public function get_analytics($period = 'month')
    {
        global $wpdb;

        if (!class_exists('Rental_Gates_Database')) {
            return array();
        }

        $tables = Rental_Gates_Database::get_table_names();

        $date_filter = "";
        if ($period === 'month') {
            $date_filter = "AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)";
        } elseif ($period === 'week') {
            $date_filter = "AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)";
        } elseif ($period === 'day') {
            $date_filter = "AND DATE(created_at) = CURRENT_DATE()";
        }

        // Total credits used
        $total_used = $wpdb->get_var(
            "SELECT COALESCE(SUM(credits_used), 0) FROM {$tables['ai_usage']} WHERE 1=1 {$date_filter}"
        );

        // By tool
        $by_tool = $wpdb->get_results(
            "SELECT tool, COUNT(*) as count, SUM(credits_used) as credits
             FROM {$tables['ai_usage']}
             WHERE 1=1 {$date_filter}
             GROUP BY tool
             ORDER BY credits DESC",
            ARRAY_A
        );

        // By organization (top users)
        $top_users = $wpdb->get_results(
            "SELECT u.organization_id, o.name as org_name, s.plan_slug,
                    SUM(u.credits_used) as total_credits,
                    COUNT(*) as total_actions
             FROM {$tables['ai_usage']} u
             LEFT JOIN {$tables['organizations']} o ON u.organization_id = o.id
             LEFT JOIN {$tables['subscriptions']} s ON u.organization_id = s.organization_id
             WHERE 1=1 {$date_filter}
             GROUP BY u.organization_id
             ORDER BY total_credits DESC
             LIMIT 10",
            ARRAY_A
        );

        // Purchase revenue
        $purchases_table = $wpdb->prefix . 'rg_ai_credit_purchases';
        $revenue = 0;
        $purchases_exist = $wpdb->get_var("SHOW TABLES LIKE '{$purchases_table}'");
        if ($purchases_exist) {
            $revenue = $wpdb->get_var(
                "SELECT COALESCE(SUM(amount), 0) FROM {$purchases_table} 
                 WHERE status = 'completed' {$date_filter}"
            );
        }

        return array(
            'total_credits_used' => intval($total_used),
            'revenue' => floatval($revenue),
            'by_tool' => $by_tool ?: array(),
            'top_users' => $top_users ?: array(),
        );
    }

    /**
     * Get organizations with low credits
     * 
     * @param float $threshold Percentage threshold (0.2 = 20%)
     * @return array
     */
    public function get_low_credit_organizations($threshold = 0.2)
    {
        global $wpdb;

        if (!class_exists('Rental_Gates_Database')) {
            return array();
        }

        $tables = Rental_Gates_Database::get_table_names();
        $balance_table = $wpdb->prefix . 'rg_ai_credit_balances';

        // Get all active organizations with their balances
        $results = $wpdb->get_results(
            "SELECT o.id, o.name, o.owner_email, s.plan_slug,
                    COALESCE(b.subscription_credits, 0) + COALESCE(b.purchased_credits, 0) + COALESCE(b.bonus_credits, 0) as total_credits
             FROM {$tables['organizations']} o
             LEFT JOIN {$tables['subscriptions']} s ON o.id = s.organization_id
             LEFT JOIN {$balance_table} b ON o.id = b.organization_id
             WHERE s.status = 'active'",
            ARRAY_A
        );

        $plans = get_option('rental_gates_plans', array());
        $low_credit_orgs = array();

        foreach ($results as $org) {
            $plan = $plans[$org['plan_slug']] ?? array();
            $plan_limit = $plan['limits']['ai_credits'] ?? 0;

            if ($plan_limit > 0) {
                $percentage = $org['total_credits'] / $plan_limit;
                if ($percentage <= $threshold) {
                    $org['percentage'] = $percentage;
                    $org['plan_limit'] = $plan_limit;
                    $low_credit_orgs[] = $org;
                }
            }
        }

        return $low_credit_orgs;
    }
}

/**
 * Helper function to get AI Credits instance
 */
function rg_ai_credits()
{
    return Rental_Gates_AI_Credits::get_instance();
}
