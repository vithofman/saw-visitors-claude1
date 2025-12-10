<?php
/**
 * ICS File Generator
 *
 * Generates iCalendar (.ics) files for visit events.
 * Compatible with Google Calendar, Apple Calendar, Outlook, and other calendar apps.
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
 * SAW ICS Generator Class
 *
 * Creates RFC 5545 compliant iCalendar files for visit events.
 *
 * @since 1.0.0
 */
class SAW_ICS_Generator {
    
    /**
     * Line ending for ICS files (CRLF per RFC 5545)
     */
    const CRLF = "\r\n";
    
    /**
     * Maximum line length before folding
     */
    const LINE_LENGTH = 75;
    
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
     * Generate ICS file content
     *
     * @return string ICS file content
     */
    public function generate() {
        $ics = [];
        
        // Calendar header
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = 'PRODID:-//SAW Visitors//Visit Calendar//CS';
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';
        $ics[] = 'X-WR-CALNAME:SAW Visitors';
        
        // Event
        $ics[] = 'BEGIN:VEVENT';
        $ics[] = 'UID:' . $this->generate_uid();
        $ics[] = 'DTSTAMP:' . $this->format_datetime(current_time('mysql'));
        $ics[] = 'DTSTART:' . $this->format_datetime($this->get_start_time());
        $ics[] = 'DTEND:' . $this->format_datetime($this->get_end_time());
        $ics[] = $this->fold_line('SUMMARY:' . $this->get_summary());
        $ics[] = $this->fold_line('DESCRIPTION:' . $this->get_description());
        
        // Location
        $location = $this->get_location();
        if (!empty($location)) {
            $ics[] = $this->fold_line('LOCATION:' . $location);
        }
        
        // Status
        $ics[] = 'STATUS:' . $this->get_status();
        
        // Organizer (if available)
        $organizer = $this->get_organizer();
        if (!empty($organizer)) {
            $ics[] = 'ORGANIZER;CN=' . $organizer['name'] . ':mailto:' . $organizer['email'];
        }
        
        // Alarm/Reminder (30 minutes before)
        $ics[] = 'BEGIN:VALARM';
        $ics[] = 'TRIGGER:-PT30M';
        $ics[] = 'ACTION:DISPLAY';
        $ics[] = 'DESCRIPTION:Připomenutí návštěvy';
        $ics[] = 'END:VALARM';
        
        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';
        
        return implode(self::CRLF, $ics);
    }
    
    /**
     * Generate unique identifier for event
     *
     * @return string UID
     */
    private function generate_uid() {
        $visit_id = $this->visit['id'] ?? 0;
        $timestamp = time();
        $domain = parse_url(home_url(), PHP_URL_HOST) ?: 'saw-visitors.local';
        
        return "visit-{$visit_id}-{$timestamp}@{$domain}";
    }
    
    /**
     * Format datetime for ICS (UTC)
     *
     * @param string $datetime MySQL datetime
     * @return string ICS formatted datetime
     */
    private function format_datetime($datetime) {
        if (empty($datetime)) {
            $datetime = current_time('mysql');
        }
        
        $timestamp = strtotime($datetime);
        
        // Convert to UTC
        return gmdate('Ymd\THis\Z', $timestamp);
    }
    
    /**
     * Get start time
     *
     * @return string Start datetime
     */
    private function get_start_time() {
        // Try scheduled_arrival first, then visit_date with default time
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
     * Get end time
     *
     * @return string End datetime
     */
    private function get_end_time() {
        // Try scheduled_departure first
        if (!empty($this->visit['scheduled_departure'])) {
            return $this->visit['scheduled_departure'];
        }
        
        // Calculate from start + duration or default 2 hours
        $start = strtotime($this->get_start_time());
        $duration = intval($this->visit['expected_duration'] ?? 120); // minutes
        
        return date('Y-m-d H:i:s', $start + ($duration * 60));
    }
    
    /**
     * Get event summary (title)
     *
     * @return string Event title
     */
    private function get_summary() {
        $parts = [];
        
        // Visit type or default
        $type = $this->visit['visit_type_label'] ?? $this->visit['visit_type'] ?? 'Návštěva';
        $parts[] = $type;
        
        // Company name
        if (!empty($this->visit['company_name'])) {
            $parts[] = $this->visit['company_name'];
        }
        
        // Visitor count
        $count = intval($this->visit['person_count'] ?? count($this->visitors) ?? 1);
        if ($count > 1) {
            $parts[] = "({$count} osob)";
        }
        
        return $this->escape_text(implode(' - ', $parts));
    }
    
    /**
     * Get event description
     *
     * @return string Event description
     */
    private function get_description() {
        $lines = [];
        
        // Purpose
        if (!empty($this->visit['purpose'])) {
            $lines[] = "Účel: " . $this->visit['purpose'];
        }
        
        // Visitors list
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
        
        // Host
        if (!empty($this->visit['host_name'])) {
            $lines[] = "";
            $lines[] = "Hostitel: " . $this->visit['host_name'];
        }
        
        // Notes
        if (!empty($this->visit['notes'])) {
            $lines[] = "";
            $lines[] = "Poznámky: " . $this->visit['notes'];
        }
        
        // Link to visit detail
        if (!empty($this->visit['id'])) {
            $lines[] = "";
            $lines[] = "Detail návštěvy: " . home_url('/admin/visits/' . $this->visit['id'] . '/');
        }
        
        return $this->escape_text(implode('\n', $lines));
    }
    
    /**
     * Get location
     *
     * @return string Location string
     */
    private function get_location() {
        $parts = [];
        
        // Branch name
        if (!empty($this->branch['name'])) {
            $parts[] = $this->branch['name'];
        }
        
        // Address
        $address_parts = [];
        if (!empty($this->branch['street'])) {
            $address_parts[] = $this->branch['street'];
        }
        if (!empty($this->branch['city'])) {
            $city = $this->branch['city'];
            if (!empty($this->branch['postal_code'])) {
                $city = $this->branch['postal_code'] . ' ' . $city;
            }
            $address_parts[] = $city;
        }
        
        if (!empty($address_parts)) {
            $parts[] = implode(', ', $address_parts);
        }
        
        // Department
        if (!empty($this->visit['department_name'])) {
            $parts[] = 'Oddělení: ' . $this->visit['department_name'];
        }
        
        return $this->escape_text(implode(', ', $parts));
    }
    
    /**
     * Get event status
     *
     * @return string ICS status
     */
    private function get_status() {
        $status = $this->visit['status'] ?? 'scheduled';
        
        $map = [
            'scheduled' => 'CONFIRMED',
            'confirmed' => 'CONFIRMED',
            'in_progress' => 'CONFIRMED',
            'completed' => 'CONFIRMED',
            'cancelled' => 'CANCELLED',
        ];
        
        return $map[$status] ?? 'TENTATIVE';
    }
    
    /**
     * Get organizer info
     *
     * @return array|null Organizer data with name and email
     */
    private function get_organizer() {
        if (!empty($this->visit['host_name']) && !empty($this->visit['host_email'])) {
            return [
                'name' => $this->escape_text($this->visit['host_name']),
                'email' => $this->visit['host_email'],
            ];
        }
        
        return null;
    }
    
    /**
     * Escape text for ICS format
     *
     * @param string $text Input text
     * @return string Escaped text
     */
    private function escape_text($text) {
        // Replace special characters
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace("\r\n", '\n', $text);
        $text = str_replace("\n", '\n', $text);
        $text = str_replace("\r", '\n', $text);
        
        return $text;
    }
    
    /**
     * Fold long lines per RFC 5545
     *
     * @param string $line Input line
     * @return string Folded line
     */
    private function fold_line($line) {
        if (strlen($line) <= self::LINE_LENGTH) {
            return $line;
        }
        
        $folded = substr($line, 0, self::LINE_LENGTH);
        $remaining = substr($line, self::LINE_LENGTH);
        
        while (strlen($remaining) > 0) {
            $chunk = substr($remaining, 0, self::LINE_LENGTH - 1);
            $folded .= self::CRLF . ' ' . $chunk;
            $remaining = substr($remaining, self::LINE_LENGTH - 1);
        }
        
        return $folded;
    }
    
    /**
     * Output ICS file for download
     *
     * @param string $filename Optional filename
     */
    public function download($filename = null) {
        if (empty($filename)) {
            $visit_id = $this->visit['id'] ?? 0;
            $date = date('Y-m-d');
            $filename = "navsteva-{$visit_id}-{$date}.ics";
        }
        
        $content = $this->generate();
        
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        
        echo $content;
        exit;
    }
    
    /**
     * Get ICS content as string
     *
     * @return string ICS content
     */
    public function get_content() {
        return $this->generate();
    }
    
    /**
     * Get data URI for ICS file (for inline download links)
     *
     * @return string Data URI
     */
    public function get_data_uri() {
        $content = $this->generate();
        return 'data:text/calendar;charset=utf-8,' . rawurlencode($content);
    }
}
