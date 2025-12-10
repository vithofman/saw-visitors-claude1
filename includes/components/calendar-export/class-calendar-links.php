<?php
/**
 * Calendar Links Builder
 *
 * Generates direct URLs for adding events to external calendars.
 * Supports Google Calendar, Microsoft Outlook, and generic ICS download.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/CalendarExport
 * @version     1.0.0
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Calendar Links Class
 *
 * Creates URLs for external calendar services.
 *
 * @since 1.0.0
 */
class SAW_Calendar_Links {
    
    /**
     * Visit data
     *
     * @var array
     */
    private $visit;
    
    /**
     * Branch data
     *
     * @var array
     */
    private $branch;
    
    /**
     * Visitors list
     *
     * @var array
     */
    private $visitors;
    
    /**
     * Constructor
     *
     * @param array $visit    Visit data
     * @param array $branch   Branch data (optional)
     * @param array $visitors Visitors list (optional)
     */
    public function __construct($visit, $branch = [], $visitors = []) {
        $this->visit = $visit;
        $this->branch = $branch;
        $this->visitors = $visitors;
    }
    
    /**
     * Get all calendar links
     *
     * @return array Links for all supported calendars
     */
    public function get_all_links() {
        return [
            'google' => [
                'url' => $this->get_google_url(),
                'label' => 'Google Calendar',
                'icon' => '📅',
                'class' => 'saw-cal-google',
            ],
            'outlook' => [
                'url' => $this->get_outlook_url(),
                'label' => 'Outlook',
                'icon' => '📧',
                'class' => 'saw-cal-outlook',
            ],
            'office365' => [
                'url' => $this->get_office365_url(),
                'label' => 'Office 365',
                'icon' => '💼',
                'class' => 'saw-cal-office365',
            ],
            'ics' => [
                'url' => $this->get_ics_download_url(),
                'label' => 'Apple / iCal',
                'icon' => '🍎',
                'class' => 'saw-cal-ics',
            ],
        ];
    }
    
    /**
     * Generate Google Calendar URL
     *
     * @return string Google Calendar add event URL
     */
    public function get_google_url() {
        $base_url = 'https://calendar.google.com/calendar/render';
        
        $params = [
            'action' => 'TEMPLATE',
            'text' => $this->get_title(),
            'dates' => $this->get_google_dates(),
            'details' => $this->get_description(),
            'location' => $this->get_location(),
            'sf' => 'true', // Show as free
            'output' => 'xml',
        ];
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Generate Outlook.com (Live) URL
     *
     * @return string Outlook calendar URL
     */
    public function get_outlook_url() {
        $base_url = 'https://outlook.live.com/calendar/0/deeplink/compose';
        
        $params = [
            'subject' => $this->get_title(),
            'startdt' => $this->get_iso_start(),
            'enddt' => $this->get_iso_end(),
            'body' => $this->get_description_html(),
            'location' => $this->get_location(),
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
        ];
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Generate Office 365 URL
     *
     * @return string Office 365 calendar URL
     */
    public function get_office365_url() {
        $base_url = 'https://outlook.office.com/calendar/0/deeplink/compose';
        
        $params = [
            'subject' => $this->get_title(),
            'startdt' => $this->get_iso_start(),
            'enddt' => $this->get_iso_end(),
            'body' => $this->get_description_html(),
            'location' => $this->get_location(),
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
        ];
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Generate Yahoo Calendar URL
     *
     * @return string Yahoo calendar URL
     */
    public function get_yahoo_url() {
        $base_url = 'https://calendar.yahoo.com/';
        
        $params = [
            'v' => '60',
            'title' => $this->get_title(),
            'st' => $this->get_yahoo_start(),
            'et' => $this->get_yahoo_end(),
            'desc' => $this->get_description(),
            'in_loc' => $this->get_location(),
        ];
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Get ICS file download URL
     *
     * @return string URL to download .ics file
     */
    public function get_ics_download_url() {
        $visit_id = $this->visit['id'] ?? 0;
        
        return add_query_arg([
            'action' => 'saw_download_ics',
            'visit_id' => $visit_id,
            'nonce' => wp_create_nonce('saw_ics_download_' . $visit_id),
        ], admin_url('admin-ajax.php'));
    }
    
    /**
     * Get event title
     *
     * @return string Event title
     */
    private function get_title() {
        $parts = [];
        
        // Visit type
        $type = $this->visit['visit_type_label'] ?? $this->visit['visit_type'] ?? 'Návštěva';
        $parts[] = $type;
        
        // Company
        if (!empty($this->visit['company_name'])) {
            $parts[] = $this->visit['company_name'];
        }
        
        // Visitor count
        $count = intval($this->visit['person_count'] ?? 1);
        if ($count > 1) {
            $parts[] = "({$count} osob)";
        }
        
        return implode(' - ', $parts);
    }
    
    /**
     * Get event description (plain text)
     *
     * @return string Description
     */
    private function get_description() {
        $lines = [];
        
        if (!empty($this->visit['purpose'])) {
            $lines[] = "Účel: " . $this->visit['purpose'];
        }
        
        if (!empty($this->visitors)) {
            $lines[] = "";
            $lines[] = "Návštěvníci:";
            foreach ($this->visitors as $visitor) {
                $name = trim(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? ''));
                if (!empty($name)) {
                    $lines[] = "- " . $name;
                }
            }
        }
        
        if (!empty($this->visit['host_name'])) {
            $lines[] = "";
            $lines[] = "Hostitel: " . $this->visit['host_name'];
        }
        
        if (!empty($this->visit['id'])) {
            $lines[] = "";
            $lines[] = "Detail: " . home_url('/admin/visits/' . $this->visit['id'] . '/');
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Get event description (HTML)
     *
     * @return string HTML description
     */
    private function get_description_html() {
        $html = [];
        
        if (!empty($this->visit['purpose'])) {
            $html[] = "<b>Účel:</b> " . esc_html($this->visit['purpose']);
        }
        
        if (!empty($this->visitors)) {
            $html[] = "<br><b>Návštěvníci:</b><ul>";
            foreach ($this->visitors as $visitor) {
                $name = trim(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? ''));
                if (!empty($name)) {
                    $html[] = "<li>" . esc_html($name) . "</li>";
                }
            }
            $html[] = "</ul>";
        }
        
        if (!empty($this->visit['host_name'])) {
            $html[] = "<b>Hostitel:</b> " . esc_html($this->visit['host_name']);
        }
        
        if (!empty($this->visit['id'])) {
            $url = home_url('/admin/visits/' . $this->visit['id'] . '/');
            $html[] = "<br><a href=\"{$url}\">Zobrazit detail návštěvy</a>";
        }
        
        return implode("", $html);
    }
    
    /**
     * Get location string
     *
     * @return string Location
     */
    private function get_location() {
        $parts = [];
        
        if (!empty($this->branch['name'])) {
            $parts[] = $this->branch['name'];
        }
        
        if (!empty($this->branch['street'])) {
            $parts[] = $this->branch['street'];
        }
        
        if (!empty($this->branch['city'])) {
            $city = $this->branch['city'];
            if (!empty($this->branch['postal_code'])) {
                $city = $this->branch['postal_code'] . ' ' . $city;
            }
            $parts[] = $city;
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Get start datetime
     *
     * @return string MySQL datetime
     */
    private function get_start_datetime() {
        if (!empty($this->visit['scheduled_arrival'])) {
            return $this->visit['scheduled_arrival'];
        }
        
        if (!empty($this->visit['visit_date'])) {
            $time = $this->visit['expected_arrival_time'] ?? '09:00:00';
            return $this->visit['visit_date'] . ' ' . $time;
        }
        
        return current_time('mysql');
    }
    
    /**
     * Get end datetime
     *
     * @return string MySQL datetime
     */
    private function get_end_datetime() {
        if (!empty($this->visit['scheduled_departure'])) {
            return $this->visit['scheduled_departure'];
        }
        
        $start = strtotime($this->get_start_datetime());
        $duration = intval($this->visit['expected_duration'] ?? 120);
        
        return date('Y-m-d H:i:s', $start + ($duration * 60));
    }
    
    /**
     * Get Google Calendar date format
     *
     * @return string Dates in Google format (YYYYMMDDTHHMMSSZ/YYYYMMDDTHHMMSSZ)
     */
    private function get_google_dates() {
        $start = gmdate('Ymd\THis\Z', strtotime($this->get_start_datetime()));
        $end = gmdate('Ymd\THis\Z', strtotime($this->get_end_datetime()));
        
        return $start . '/' . $end;
    }
    
    /**
     * Get ISO 8601 start time
     *
     * @return string ISO 8601 datetime
     */
    private function get_iso_start() {
        return gmdate('Y-m-d\TH:i:s\Z', strtotime($this->get_start_datetime()));
    }
    
    /**
     * Get ISO 8601 end time
     *
     * @return string ISO 8601 datetime
     */
    private function get_iso_end() {
        return gmdate('Y-m-d\TH:i:s\Z', strtotime($this->get_end_datetime()));
    }
    
    /**
     * Get Yahoo Calendar start time
     *
     * @return string Yahoo format datetime
     */
    private function get_yahoo_start() {
        return gmdate('Ymd\THis\Z', strtotime($this->get_start_datetime()));
    }
    
    /**
     * Get Yahoo Calendar end time
     *
     * @return string Yahoo format datetime
     */
    private function get_yahoo_end() {
        return gmdate('Ymd\THis\Z', strtotime($this->get_end_datetime()));
    }
    
    /**
     * Render calendar buttons HTML
     *
     * @param string $style Button style: 'inline', 'block', 'icons'
     * @return string HTML
     */
    public function render_buttons($style = 'inline') {
        $links = $this->get_all_links();
        
        $class = 'saw-calendar-buttons';
        if ($style === 'block') {
            $class .= ' saw-calendar-buttons--block';
        } elseif ($style === 'icons') {
            $class .= ' saw-calendar-buttons--icons';
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <span class="saw-calendar-buttons__label">📅 Přidat do kalendáře:</span>
            <div class="saw-calendar-buttons__links">
                <?php foreach ($links as $key => $link): ?>
                    <a href="<?php echo esc_url($link['url']); ?>" 
                       class="saw-calendar-btn <?php echo esc_attr($link['class']); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="<?php echo esc_attr($link['label']); ?>">
                        <?php if ($style === 'icons'): ?>
                            <span class="saw-calendar-btn__icon"><?php echo $link['icon']; ?></span>
                        <?php else: ?>
                            <span class="saw-calendar-btn__icon"><?php echo $link['icon']; ?></span>
                            <span class="saw-calendar-btn__text"><?php echo esc_html($link['label']); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render buttons for email (inline styles)
     *
     * @return string HTML with inline styles
     */
    public function render_email_buttons() {
        $links = $this->get_all_links();
        
        $button_style = 'display: inline-block; padding: 8px 16px; margin: 4px; ' .
                        'background-color: #f0f0f0; color: #333; text-decoration: none; ' .
                        'border-radius: 4px; font-size: 13px; font-family: Arial, sans-serif;';
        
        $google_style = 'background-color: #4285f4; color: #fff;';
        $outlook_style = 'background-color: #0078d4; color: #fff;';
        $apple_style = 'background-color: #333; color: #fff;';
        
        ob_start();
        ?>
        <div style="margin: 20px 0; padding: 16px; background-color: #f9f9f9; border-radius: 8px; text-align: center;">
            <p style="margin: 0 0 12px 0; font-size: 14px; color: #666;">
                📅 Přidat návštěvu do kalendáře:
            </p>
            <div>
                <a href="<?php echo esc_url($links['google']['url']); ?>" 
                   style="<?php echo $button_style . $google_style; ?>"
                   target="_blank">
                    Google Calendar
                </a>
                <a href="<?php echo esc_url($links['outlook']['url']); ?>" 
                   style="<?php echo $button_style . $outlook_style; ?>"
                   target="_blank">
                    Outlook
                </a>
                <a href="<?php echo esc_url($links['ics']['url']); ?>" 
                   style="<?php echo $button_style . $apple_style; ?>"
                   target="_blank">
                    Apple / iCal
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Static helper: Get links for visit
     *
     * @param int $visit_id Visit ID
     * @return array Calendar links
     */
    public static function for_visit($visit_id) {
        global $wpdb;
        
        // Load visit
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
            return [];
        }
        
        // Load branch
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $visit['branch_id'] ?? 0
        ), ARRAY_A) ?: [];
        
        // Load visitors
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT vr.* 
             FROM {$wpdb->prefix}saw_visitors vr
             INNER JOIN {$wpdb->prefix}saw_visit_visitors vv ON vr.id = vv.visitor_id
             WHERE vv.visit_id = %d",
            $visit_id
        ), ARRAY_A) ?: [];
        
        $instance = new self($visit, $branch, $visitors);
        return $instance->get_all_links();
    }
}
