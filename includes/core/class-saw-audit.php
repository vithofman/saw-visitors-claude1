<?php
/**
 * SAW Audit Logger
 * 
 * Logování všech důležitých akcí do audit_log tabulky
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Audit {

    /**
     * Zalogování akce
     *
     * @param array $data {
     *     @type string $action       Název akce (povinné)
     *     @type int    $user_id      SAW user ID (volitelné)
     *     @type int    $customer_id  Customer ID (volitelné)
     *     @type string $ip_address   IP adresa (volitelné, auto-detect)
     *     @type string $user_agent   User agent (volitelné, auto-detect)
     *     @type string $details      Detaily akce (volitelné, JSON nebo text)
     * }
     * @return int|false ID záznamu nebo false při chybě
     */
    public static function log($data) {
        global $wpdb;

        if (empty($data['action'])) {
            return false;
        }

        if (empty($data['ip_address'])) {
            $data['ip_address'] = self::get_client_ip();
        }

        if (empty($data['user_agent'])) {
            $data['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        }

        if (isset($data['details']) && is_array($data['details'])) {
            $data['details'] = wp_json_encode($data['details']);
        }

        $insert_data = array(
            'action'      => sanitize_text_field($data['action']),
            'user_id'     => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'ip_address'  => sanitize_text_field($data['ip_address']),
            'user_agent'  => sanitize_text_field($data['user_agent']),
            'details'     => $data['details'] ?? null,
            'created_at'  => current_time('mysql'),
        );

        $format = array('%s', '%d', '%d', '%s', '%s', '%s', '%s');

        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_audit_log',
            $insert_data,
            $format
        );

        if (!$result) {
            error_log(sprintf(
                '[SAW Audit] Failed to log action: %s - %s',
                $data['action'],
                $data['details'] ?? ''
            ));
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Získání audit logů s filtrováním
     *
     * @param array $args {
     *     @type int    $user_id      Filtrovat podle user ID
     *     @type int    $customer_id  Filtrovat podle customer ID
     *     @type string $action       Filtrovat podle akce
     *     @type string $date_from    Datum od (Y-m-d)
     *     @type string $date_to      Datum do (Y-m-d)
     *     @type int    $limit        Limit záznamů (default: 100)
     *     @type int    $offset       Offset (default: 0)
     *     @type string $order        Order (default: DESC)
     * }
     * @return array
     */
    public static function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'user_id'     => null,
            'customer_id' => null,
            'action'      => null,
            'date_from'   => null,
            'date_to'     => null,
            'limit'       => 100,
            'offset'      => 0,
            'order'       => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $where_values = array();

        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['customer_id']) {
            $where[] = 'customer_id = %d';
            $where_values[] = $args['customer_id'];
        }

        if ($args['action']) {
            $where[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        if ($args['date_from']) {
            $where[] = 'DATE(created_at) >= %s';
            $where_values[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where[] = 'DATE(created_at) <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_sql = implode(' AND ', $where);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$wpdb->prefix}saw_audit_log 
                WHERE {$where_sql} 
                ORDER BY created_at {$order} 
                LIMIT %d OFFSET %d";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Počet logů (pro pagination)
     *
     * @param array $args Stejné jako get_logs()
     * @return int
     */
    public static function count_logs($args = array()) {
        global $wpdb;

        $where = array('1=1');
        $where_values = array();

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if (!empty($args['customer_id'])) {
            $where[] = 'customer_id = %d';
            $where_values[] = $args['customer_id'];
        }

        if (!empty($args['action'])) {
            $where[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'DATE(created_at) >= %s';
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'DATE(created_at) <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}saw_audit_log WHERE {$where_sql}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Získání audit logů pro uživatele
     *
     * @param int $user_id SAW user ID
     * @param int $limit   Limit záznamů
     * @param int $offset  Offset
     * @return array
     */
    public static function get_user_logs($user_id, $limit = 50, $offset = 0) {
        return self::get_logs(array(
            'user_id' => $user_id,
            'limit'   => $limit,
            'offset'  => $offset,
        ));
    }

    /**
     * Získání audit logů pro zákazníka
     *
     * @param int $customer_id Customer ID
     * @param int $limit       Limit záznamů
     * @param int $offset      Offset
     * @return array
     */
    public static function get_customer_logs($customer_id, $limit = 100, $offset = 0) {
        return self::get_logs(array(
            'customer_id' => $customer_id,
            'limit'       => $limit,
            'offset'      => $offset,
        ));
    }

    /**
     * Získání logů podle akce
     *
     * @param string $action Název akce
     * @param int    $limit  Limit záznamů
     * @return array
     */
    public static function get_logs_by_action($action, $limit = 100) {
        return self::get_logs(array(
            'action' => $action,
            'limit'  => $limit,
        ));
    }

    /**
     * Cleanup starých audit logů (voláno cronem)
     *
     * @param int $days Počet dní k uchování (default: 90)
     * @return int Počet smazaných záznamů
     */
    public static function cleanup_old_logs($days = 90) {
        global $wpdb;

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}saw_audit_log WHERE created_at < %s",
            $date_threshold
        ));

        if ($deleted > 0) {
            self::log(array(
                'action'  => 'audit_log_cleanup',
                'details' => sprintf('Smazáno %d starých audit logů (starší než %d dní)', $deleted, $days),
            ));
        }

        return $deleted;
    }

    /**
     * Export audit logů do CSV
     *
     * @param array $args Filtry (stejné jako get_logs)
     * @return string CSV obsah
     */
    public static function export_to_csv($args = array()) {
        $logs = self::get_logs(array_merge($args, array('limit' => 10000)));

        $csv = "ID,Action,User ID,Customer ID,IP Address,User Agent,Details,Created At\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s\n",
                $log['id'],
                self::escape_csv($log['action']),
                $log['user_id'] ?? '',
                $log['customer_id'] ?? '',
                self::escape_csv($log['ip_address']),
                self::escape_csv($log['user_agent']),
                self::escape_csv($log['details'] ?? ''),
                $log['created_at']
            );
        }

        return $csv;
    }

    /**
     * Statistiky audit logů
     *
     * @param int|null $customer_id Volitelně filtrovat podle zákazníka
     * @return array
     */
    public static function get_stats($customer_id = null) {
        global $wpdb;

        $where = $customer_id ? $wpdb->prepare('WHERE customer_id = %d', $customer_id) : '';

        $stats = array(
            'total_logs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}saw_audit_log {$where}"),
            
            'logs_by_action' => $wpdb->get_results(
                "SELECT action, COUNT(*) as count 
                 FROM {$wpdb->prefix}saw_audit_log 
                 {$where}
                 GROUP BY action 
                 ORDER BY count DESC 
                 LIMIT 20",
                ARRAY_A
            ),
            
            'logs_last_24h' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_audit_log 
                 {$where}" . ($where ? ' AND' : ' WHERE') . " created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            ),
            
            'logs_last_7d' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_audit_log 
                 {$where}" . ($where ? ' AND' : ' WHERE') . " created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
            ),
            
            'logs_last_30d' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_audit_log 
                 {$where}" . ($where ? ' AND' : ' WHERE') . " created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
        );

        return $stats;
    }

    /**
     * Získání IP adresy klienta
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Escape hodnoty pro CSV
     *
     * @param string $value Hodnota
     * @return string
     */
    private static function escape_csv($value) {
        $value = str_replace('"', '""', $value);
        
        if (strpos($value, ',') !== false || strpos($value, "\n") !== false || strpos($value, '"') !== false) {
            $value = '"' . $value . '"';
        }
        
        return $value;
    }

    /**
     * Seznam všech akcí (pro dropdown filtry)
     *
     * @param int|null $customer_id Volitelně filtrovat podle zákazníka
     * @return array
     */
    public static function get_all_actions($customer_id = null) {
        global $wpdb;

        $where = $customer_id ? $wpdb->prepare('WHERE customer_id = %d', $customer_id) : '';

        return $wpdb->get_col(
            "SELECT DISTINCT action FROM {$wpdb->prefix}saw_audit_log 
             {$where}
             ORDER BY action ASC"
        );
    }

    /**
     * Vyhledávání v audit logu
     *
     * @param string $search_term Vyhledávací term
     * @param int    $limit       Limit výsledků
     * @return array
     */
    public static function search($search_term, $limit = 100) {
        global $wpdb;

        $search_term = '%' . $wpdb->esc_like($search_term) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_audit_log 
             WHERE action LIKE %s 
                OR details LIKE %s 
                OR ip_address LIKE %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $search_term,
            $search_term,
            $search_term,
            $limit
        ), ARRAY_A);
    }
}