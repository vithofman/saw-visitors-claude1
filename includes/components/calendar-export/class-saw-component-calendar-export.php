<?php
/**
 * Calendar Export Component
 *
 * Main component class that handles ICS downloads and calendar link generation.
 * Registers AJAX handlers and provides helper methods.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/CalendarExport
 * @version     1.0.0
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-ics-generator.php';
require_once __DIR__ . '/class-calendar-links.php';

/**
 * SAW Calendar Export Component
 *
 * Handles calendar export functionality including ICS downloads
 * and external calendar link generation.
 *
 * @since 1.0.0
 */
class SAW_Component_Calendar_Export {
    
    /**
     * Singleton instance
     *
     * @var SAW_Component_Calendar_Export
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return SAW_Component_Calendar_Export
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // ICS download (authenticated users)
        add_action('wp_ajax_saw_download_ics', [$this, 'handle_ics_download']);
        
        // ICS download (public - for email links)
        add_action('wp_ajax_nopriv_saw_download_ics', [$this, 'handle_ics_download_public']);
    }
    
    /**
     * Handle ICS download for authenticated users
     */
    public function handle_ics_download() {
        $visit_id = intval($_GET['visit_id'] ?? 0);
        $nonce = sanitize_text_field($_GET['nonce'] ?? '');
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'saw_ics_download_' . $visit_id)) {
            wp_die('Neplatný požadavek', 'Chyba', ['response' => 403]);
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            wp_die('Přístup zamítnut', 'Chyba', ['response' => 403]);
        }
        
        $this->generate_and_download_ics($visit_id);
    }
    
    /**
     * Handle ICS download for public (email links)
     * Uses token-based authentication
     */
    public function handle_ics_download_public() {
        $visit_id = intval($_GET['visit_id'] ?? 0);
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        // Verify token (simple hash-based)
        $expected_token = $this->generate_public_token($visit_id);
        
        if (!hash_equals($expected_token, $token)) {
            wp_die('Neplatný odkaz', 'Chyba', ['response' => 403]);
        }
        
        $this->generate_and_download_ics($visit_id);
    }
    
    /**
     * Generate and download ICS file
     *
     * @param int $visit_id Visit ID
     */
    private function generate_and_download_ics($visit_id) {
        global $wpdb;
        
        // Load visit with related data
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, 
                    c.name as company_name,
                    d.name as department_name,
                    u.display_name as host_name,
                    u.user_email as host_email
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             LEFT JOIN {$wpdb->prefix}saw_departments d ON v.department_id = d.id
             LEFT JOIN {$wpdb->users} u ON v.host_user_id = u.ID
             WHERE v.id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            wp_die('Návštěva nenalezena', 'Chyba', ['response' => 404]);
        }
        
        // Load branch
        $branch = [];
        if (!empty($visit['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                $visit['branch_id']
            ), ARRAY_A) ?: [];
        }
        
        // Load visitors
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT vr.* 
             FROM {$wpdb->prefix}saw_visitors vr
             INNER JOIN {$wpdb->prefix}saw_visit_visitors vv ON vr.id = vv.visitor_id
             WHERE vv.visit_id = %d",
            $visit_id
        ), ARRAY_A) ?: [];
        
        // Generate and download
        $generator = new SAW_ICS_Generator($visit, $branch, $visitors);
        $generator->download();
    }
    
    /**
     * Generate public token for ICS download
     *
     * @param int $visit_id Visit ID
     * @return string Token
     */
    public function generate_public_token($visit_id) {
        $secret = defined('AUTH_KEY') ? AUTH_KEY : 'saw-visitors-secret';
        return hash('sha256', $visit_id . $secret);
    }
    
    /**
     * Get public ICS download URL
     *
     * @param int $visit_id Visit ID
     * @return string URL
     */
    public function get_public_ics_url($visit_id) {
        return add_query_arg([
            'action' => 'saw_download_ics',
            'visit_id' => $visit_id,
            'token' => $this->generate_public_token($visit_id),
        ], admin_url('admin-ajax.php'));
    }
    
    /**
     * Get calendar buttons HTML for a visit
     *
     * @param int    $visit_id Visit ID
     * @param string $style    Button style
     * @return string HTML
     */
    public function get_buttons($visit_id, $style = 'inline') {
        $links = SAW_Calendar_Links::for_visit($visit_id);
        
        if (empty($links)) {
            return '';
        }
        
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        $branch = [];
        if (!empty($visit['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                $visit['branch_id']
            ), ARRAY_A) ?: [];
        }
        
        $instance = new SAW_Calendar_Links($visit, $branch);
        return $instance->render_buttons($style);
    }
    
    /**
     * Get email-friendly calendar buttons
     *
     * @param int $visit_id Visit ID
     * @return string HTML with inline styles
     */
    public function get_email_buttons($visit_id) {
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, 
                    c.name as company_name,
                    d.name as department_name
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             LEFT JOIN {$wpdb->prefix}saw_departments d ON v.department_id = d.id
             WHERE v.id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            return '';
        }
        
        $branch = [];
        if (!empty($visit['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                $visit['branch_id']
            ), ARRAY_A) ?: [];
        }
        
        // Modify ICS link to use public token
        $visit['ics_url'] = $this->get_public_ics_url($visit_id);
        
        $instance = new SAW_Calendar_Links($visit, $branch);
        
        // Get links and replace ICS URL with public version
        $links = $instance->get_all_links();
        $links['ics']['url'] = $visit['ics_url'];
        
        return $this->render_email_buttons_from_links($links);
    }
    
    /**
     * Render email buttons from links array
     *
     * @param array $links Calendar links
     * @return string HTML
     */
    private function render_email_buttons_from_links($links) {
        $button_style = 'display: inline-block; padding: 10px 20px; margin: 4px; ' .
                        'text-decoration: none; border-radius: 6px; font-size: 14px; ' .
                        'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';
        
        $styles = [
            'google' => 'background-color: #4285f4; color: #ffffff;',
            'outlook' => 'background-color: #0078d4; color: #ffffff;',
            'office365' => 'background-color: #0078d4; color: #ffffff;',
            'ics' => 'background-color: #333333; color: #ffffff;',
        ];
        
        ob_start();
        ?>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px auto;">
            <tr>
                <td style="padding: 16px 20px; background-color: #f8f9fa; border-radius: 8px; text-align: center;">
                    <p style="margin: 0 0 12px 0; font-size: 14px; color: #666666; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                        📅 Přidat návštěvu do kalendáře:
                    </p>
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                        <tr>
                            <?php foreach (['google', 'outlook', 'ics'] as $key): ?>
                                <?php if (isset($links[$key])): ?>
                                    <td style="padding: 0 4px;">
                                        <a href="<?php echo esc_url($links[$key]['url']); ?>" 
                                           style="<?php echo $button_style . $styles[$key]; ?>"
                                           target="_blank">
                                            <?php echo esc_html($links[$key]['label']); ?>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <?php
        return ob_get_clean();
    }
}

// Initialize singleton
SAW_Component_Calendar_Export::instance();
