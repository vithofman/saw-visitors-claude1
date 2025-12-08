<?php
/**
 * Visitor Info Portal Email Service
 * 
 * Sends emails with personal info portal links to visitors.
 * Links contain unique tokens for accessing training and safety information.
 * 
 * @package     SAW_Visitors
 * @subpackage  Services
 * @since       3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitor_Info_Email {
    
    /**
     * Send info portal email to single visitor
     * 
     * Generates token if needed, builds email content, and sends.
     * Marks visitor as email sent to prevent duplicates.
     * 
     * @since 3.3.0
     * @param int $visitor_id Visitor ID
     * @param string $language Language code ('cs' or 'en')
     * @return bool True on success, false on failure
     */
    public static function send($visitor_id, $language = 'cs') {
        global $wpdb;
        
        $visitor_id = intval($visitor_id);
        if (!$visitor_id) {
            return false;
        }
        
        // Normalize language
        if (!in_array($language, array('cs', 'en'), true)) {
            $language = 'cs';
        }
        
        // Load model
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/model.php';
        $config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/config.php';
        $model = new SAW_Module_Visitors_Model($config);
        
        // Check if should send
        if (!$model->should_send_info_portal_email($visitor_id)) {
            return false;
        }
        
        // Get visitor data with relations
        $visitor = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                v.id, 
                v.first_name, 
                v.last_name, 
                v.email, 
                v.visit_id,
                vis.planned_date_from, 
                vis.planned_date_to,
                c.name as company_name, 
                b.name as branch_name, 
                cust.name as customer_name
             FROM {$wpdb->prefix}saw_visitors v
             INNER JOIN {$wpdb->prefix}saw_visits vis ON v.visit_id = vis.id
             LEFT JOIN {$wpdb->prefix}saw_companies c ON vis.company_id = c.id
             INNER JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
             INNER JOIN {$wpdb->prefix}saw_customers cust ON v.customer_id = cust.id
             WHERE v.id = %d",
            $visitor_id
        ), ARRAY_A);
        
        if (!$visitor || empty($visitor['email'])) {
            return false;
        }
        
        // Generate token
        $token = $model->generate_info_portal_token($visitor_id);
        if (is_wp_error($token)) {
            return false;
        }
        
        // Build URL and content
        $info_url = home_url('/visitor-info/' . $token . '/');
        $date_range = self::format_date_range($visitor['planned_date_from'], $visitor['planned_date_to']);
        $email = self::build_email($visitor, $info_url, $date_range, $language);
        
        // Prepare headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $visitor['customer_name'] . ' <' . get_option('admin_email') . '>',
        );
        
        // Send email
        $sent = wp_mail($visitor['email'], $email['subject'], $email['body'], $headers);
        
        if ($sent) {
            $model->mark_info_portal_email_sent($visitor_id);
        }
        
        return $sent;
    }
    
    /**
     * Send info portal emails to all visitors in a visit
     * 
     * Iterates through all visitors with email addresses that haven't
     * received the email yet and sends to each one.
     * 
     * @since 3.3.0
     * @param int $visit_id Visit ID
     * @param string $language Language code ('cs' or 'en')
     * @return int Number of emails successfully sent
     */
    public static function send_to_visit($visit_id, $language = 'cs') {
        global $wpdb;
        
        $visit_id = intval($visit_id);
        if (!$visit_id) {
            return 0;
        }
        
        // Get all visitors who haven't received email yet
        $visitor_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visitors 
             WHERE visit_id = %d 
             AND email IS NOT NULL AND email != ''
             AND info_portal_email_sent_at IS NULL",
            $visit_id
        ));
        
        if (empty($visitor_ids)) {
            return 0;
        }
        
        $sent_count = 0;
        
        foreach ($visitor_ids as $vid) {
            if (self::send($vid, $language)) {
                $sent_count++;
            }
            
            // Small delay between emails to avoid rate limiting
            usleep(100000); // 0.1 second
        }
        
        return $sent_count;
    }
    
    /**
     * Format date range for display in email
     * 
     * @since 3.3.0
     * @param string|null $from Start date
     * @param string|null $to End date
     * @return string Formatted date range
     */
    private static function format_date_range($from, $to) {
        if (empty($from) && empty($to)) {
            return '';
        }
        
        $from_formatted = $from ? date_i18n('d.m.Y', strtotime($from)) : '';
        $to_formatted = $to ? date_i18n('d.m.Y', strtotime($to)) : '';
        
        // Same day or only one date
        if ($from_formatted === $to_formatted || empty($to_formatted)) {
            return $from_formatted;
        }
        
        if (empty($from_formatted)) {
            return $to_formatted;
        }
        
        return $from_formatted . ' - ' . $to_formatted;
    }
    
    /**
     * Build email subject and body
     * 
     * @since 3.3.0
     * @param array $visitor Visitor data
     * @param string $url Info portal URL
     * @param string $date_range Formatted date range
     * @param string $lang Language code
     * @return array Array with 'subject' and 'body' keys
     */
    private static function build_email($visitor, $url, $date_range, $lang) {
        $t = self::get_translations($lang);
        
        // Build subject
        $subject = sprintf($t['subject'], $visitor['customer_name']);
        
        // Build body
        $body = sprintf($t['greeting'], $visitor['first_name']) . "\n\n";
        $body .= sprintf($t['intro'], $visitor['customer_name'], $visitor['branch_name']) . "\n\n";
        
        if (!empty($date_range)) {
            $body .= sprintf($t['date_info'], $date_range) . "\n\n";
        }
        
        $body .= $t['link_intro'] . "\n";
        $body .= $url . "\n\n";
        $body .= $t['link_description'] . "\n\n";
        $body .= $t['footer'] . "\n";
        $body .= $visitor['customer_name'];
        
        return array(
            'subject' => $subject,
            'body' => $body,
        );
    }
    
    /**
     * Get email translations
     * 
     * @since 3.3.0
     * @param string $lang Language code
     * @return array Translation strings
     */
    private static function get_translations($lang) {
        $translations = array(
            'cs' => array(
                'subject' => 'Bezpečnostní školení a informace - %s',
                'greeting' => 'Dobrý den %s,',
                'intro' => 'děkujeme za registraci k návštěvě společnosti %s, pobočka %s.',
                'date_info' => 'Termín návštěvy: %s',
                'link_intro' => 'Na následujícím odkazu naleznete bezpečnostní školení a všechny důležité informace:',
                'link_description' => 'Školení můžete absolvovat předem na svém mobilním telefonu. Po jeho dokončení budete mít na stejném odkazu k dispozici všechny materiály pro případné pozdější nahlédnutí.',
                'footer' => 'S pozdravem,',
            ),
            'en' => array(
                'subject' => 'Safety training and information - %s',
                'greeting' => 'Hello %s,',
                'intro' => 'thank you for registering for a visit to %s, branch %s.',
                'date_info' => 'Visit date: %s',
                'link_intro' => 'You can find safety training and all important information at the following link:',
                'link_description' => 'You can complete the training on your mobile phone before arrival. After completion, you will have access to all materials for later reference.',
                'footer' => 'Best regards,',
            ),
        );
        
        return isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
    }
}