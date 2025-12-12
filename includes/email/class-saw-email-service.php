<?php
if (!defined('ABSPATH')) { exit; }

class SAW_Email_Service {
	
	private static $instance = null;
	private $template;
	private $logger;
	
	private const EMAIL_TYPES = array(
		'info_portal' => array(
			'dual' => true,
			'recipient_type' => 'visitor',
		),
		'pin_reminder' => array(
			'dual' => true,
			'recipient_type' => 'visitor',
		),
		'invitation' => array(
			'dual' => true,
			'recipient_type' => 'contact',
		),
		'risks_request' => array(
			'dual' => true,
			'recipient_type' => 'visitor',
		),
		'host_notification' => array(
			'dual' => false,
			'recipient_type' => 'host',
		),
		'password_reset' => array(
			'dual' => false,
			'recipient_type' => 'user',
		),
		'password_changed' => array(
			'dual' => false,
			'recipient_type' => 'user',
		),
		'welcome' => array(
			'dual' => false,
			'recipient_type' => 'user',
		),
	);
	
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		require_once __DIR__ . '/class-saw-email-template.php';
		require_once __DIR__ . '/class-saw-email-logger.php';
		
		$this->template = new SAW_Email_Template();
		$this->logger = new SAW_Email_Logger();
	}
	
	public function send($type, $data, $language = 'cs') {
		if (!isset(self::EMAIL_TYPES[$type])) {
			return new WP_Error('invalid_type', "Unknown email type: {$type}");
		}
		
		$config = self::EMAIL_TYPES[$type];
		
		if (empty($data['recipient_email']) || !is_email($data['recipient_email'])) {
			return new WP_Error('invalid_email', 'Invalid recipient email');
		}
		
		if (empty($data['customer_id'])) {
			return new WP_Error('missing_customer', 'Customer ID is required');
		}
		
		$customer = $this->get_customer($data['customer_id']);
		if (!$customer) {
			return new WP_Error('customer_not_found', 'Customer not found');
		}
		
		if ($config['dual']) {
			$content = $this->template->render_dual($type, $data, $customer);
			$language = 'dual';
		} else {
			$content = $this->template->render_single($type, $language, $data, $customer);
		}
		
		if (is_wp_error($content)) {
			return $content;
		}
		
		$headers = $this->build_headers($customer, $data);
		
		$sent = wp_mail(
			$data['recipient_email'],
			$content['subject'],
			$content['body_html'],
			$headers
		);
		
		$log_data = array(
			'customer_id'     => $data['customer_id'],
			'branch_id'       => $data['branch_id'] ?? null,
			'recipient_email' => $data['recipient_email'],
			'recipient_name'  => $data['recipient_name'] ?? '',
			'recipient_type'  => $config['recipient_type'],
			'recipient_id'    => $data['recipient_id'] ?? null,
			'email_type'      => $type,
			'subject'         => $content['subject'],
			'body_html'       => $content['body_html'],
			'body_text'       => $content['body_text'] ?? null,
			'language'        => $language,
			'visit_id'        => $data['visit_id'] ?? null,
			'visitor_id'      => $data['visitor_id'] ?? null,
			'status'          => $sent ? 'sent' : 'failed',
			'error_message'   => $sent ? null : 'wp_mail returned false',
			'sent_by'         => get_current_user_id() ?: null,
			'headers'         => $headers,
			'meta'            => $data['meta'] ?? null,
		);
		
		$this->logger->log($log_data);
		
		if (!$sent) {
			return new WP_Error('send_failed', 'Failed to send email');
		}
		
		return true;
	}
	
	private function build_headers($customer, $data) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		
		$from_name = $customer['email_from_name'] ?? $customer['name'];
		$from_email = get_option('admin_email');
		$headers[] = "From: {$from_name} <{$from_email}>";
		
		if (!empty($customer['email_reply_to'])) {
			$headers[] = "Reply-To: {$customer['email_reply_to']}";
		} elseif (!empty($customer['contact_email'])) {
			$headers[] = "Reply-To: {$customer['contact_email']}";
		}
		
		if (!empty($customer['email_bcc'])) {
			$headers[] = "Bcc: {$customer['email_bcc']}";
		}
		
		return $headers;
	}
	
	private function get_customer($customer_id) {
		global $wpdb;
		
		return $wpdb->get_row($wpdb->prepare(
			"SELECT id, name, logo_url, primary_color, 
					contact_email, contact_phone,
					address_street, address_city, address_zip,
					email_from_name, email_reply_to, email_bcc, email_footer_text
			 FROM {$wpdb->prefix}saw_customers 
			 WHERE id = %d",
			$customer_id
		), ARRAY_A);
	}
	
	public function get_type_config($type) {
		return self::EMAIL_TYPES[$type] ?? null;
	}
	
	public function is_dual_language($type) {
		return self::EMAIL_TYPES[$type]['dual'] ?? false;
	}
	
	public function get_logger() {
		return $this->logger;
	}
	
	// ========================================
	// CONVENIENCE METHODS
	// ========================================
	
	public function send_info_portal($visitor_id, $token) {
		global $wpdb;
		
		$visitor = $wpdb->get_row($wpdb->prepare(
			"SELECT 
				v.id, v.first_name, v.last_name, v.email, v.visit_id,
				v.customer_id, v.branch_id,
				vis.planned_date_from, vis.planned_date_to,
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
			return new WP_Error('visitor_not_found', 'Visitor not found or has no email');
		}
		
		$info_url = home_url('/visitor-info/' . $token . '/');
		
		return $this->send('info_portal', array(
			'customer_id'     => $visitor['customer_id'],
			'branch_id'       => $visitor['branch_id'],
			'recipient_email' => $visitor['email'],
			'recipient_name'  => trim($visitor['first_name'] . ' ' . $visitor['last_name']),
			'recipient_id'    => $visitor_id,
			'visit_id'        => $visitor['visit_id'],
			'visitor_id'      => $visitor_id,
			'placeholders'    => array(
				'first_name'     => $visitor['first_name'],
				'last_name'      => $visitor['last_name'],
				'customer_name'  => $visitor['customer_name'],
				'branch_name'    => $visitor['branch_name'],
				'company_name'   => $visitor['company_name'] ?? '',
				'date_from'      => $visitor['planned_date_from'],
				'date_to'        => $visitor['planned_date_to'],
				'info_url'       => $info_url,
			),
		));
	}
	
	public function send_invitation($visit_id) {
		global $wpdb;
		
		$visit = $wpdb->get_row($wpdb->prepare(
			"SELECT 
				v.*, 
				b.name as branch_name,
				cust.name as customer_name
			 FROM {$wpdb->prefix}saw_visits v
			 INNER JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
			 INNER JOIN {$wpdb->prefix}saw_customers cust ON v.customer_id = cust.id
			 WHERE v.id = %d",
			$visit_id
		), ARRAY_A);
		
		if (!$visit || empty($visit['invitation_email'])) {
			return new WP_Error('visit_not_found', 'Visit not found or has no invitation email');
		}
		
		if (empty($visit['invitation_token'])) {
			$visit['invitation_token'] = $this->generate_invitation_token($visit_id);
		}
		
		$invitation_url = home_url('/visitor-invitation/' . $visit['invitation_token'] . '/');
		
		return $this->send('invitation', array(
			'customer_id'     => $visit['customer_id'],
			'branch_id'       => $visit['branch_id'],
			'recipient_email' => $visit['invitation_email'],
			'recipient_name'  => '',
			'visit_id'        => $visit_id,
			'placeholders'    => array(
				'customer_name'  => $visit['customer_name'],
				'branch_name'    => $visit['branch_name'],
				'date_from'      => $visit['planned_date_from'],
				'date_to'        => $visit['planned_date_to'],
				'pin_code'       => $visit['pin_code'] ?? '',
				'invitation_url' => $invitation_url,
			),
		));
	}
	
	public function send_pin_reminder($visit_id) {
		global $wpdb;
		
		$visit = $wpdb->get_row($wpdb->prepare(
			"SELECT 
				v.*, 
				b.name as branch_name,
				cust.name as customer_name
			 FROM {$wpdb->prefix}saw_visits v
			 INNER JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
			 INNER JOIN {$wpdb->prefix}saw_customers cust ON v.customer_id = cust.id
			 WHERE v.id = %d",
			$visit_id
		), ARRAY_A);
		
		if (!$visit || empty($visit['invitation_email']) || empty($visit['pin_code'])) {
			return new WP_Error('visit_invalid', 'Visit not found, has no email, or has no PIN');
		}
		
		$date = !empty($visit['planned_date_from']) 
			? date_i18n('d.m.Y', strtotime($visit['planned_date_from']))
			: 'N/A';
		
		return $this->send('pin_reminder', array(
			'customer_id'     => $visit['customer_id'],
			'branch_id'       => $visit['branch_id'],
			'recipient_email' => $visit['invitation_email'],
			'visit_id'        => $visit_id,
			'placeholders'    => array(
				'customer_name' => $visit['customer_name'],
				'branch_name'   => $visit['branch_name'],
				'pin_code'      => $visit['pin_code'],
				'date'          => $date,
			),
		));
	}
	
	public function send_host_notification($visit_id, $host_user_id, $event = 'new') {
		global $wpdb;
		
		$host = $wpdb->get_row($wpdb->prepare(
			"SELECT su.*, wu.user_email, wu.display_name
			 FROM {$wpdb->prefix}saw_users su
			 JOIN {$wpdb->prefix}users wu ON su.wp_user_id = wu.ID
			 WHERE su.id = %d",
			$host_user_id
		), ARRAY_A);
		
		if (!$host || empty($host['user_email'])) {
			return new WP_Error('host_not_found', 'Host user not found or has no email');
		}
		
		$visit = $wpdb->get_row($wpdb->prepare(
			"SELECT v.*, 
					b.name as branch_name,
					c.name as company_name,
					cust.name as customer_name
			 FROM {$wpdb->prefix}saw_visits v
			 INNER JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
			 LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
			 INNER JOIN {$wpdb->prefix}saw_customers cust ON v.customer_id = cust.id
			 WHERE v.id = %d",
			$visit_id
		), ARRAY_A);
		
		if (!$visit) {
			return new WP_Error('visit_not_found', 'Visit not found');
		}
		
		$visitors = $wpdb->get_results($wpdb->prepare(
			"SELECT first_name, last_name FROM {$wpdb->prefix}saw_visitors WHERE visit_id = %d",
			$visit_id
		), ARRAY_A);
		
		$visitor_names = array_map(function($v) {
			return trim($v['first_name'] . ' ' . $v['last_name']);
		}, $visitors);
		
		$language = $host['language_preference'] ?? 'cs';
		
		return $this->send('host_notification', array(
			'customer_id'     => $visit['customer_id'],
			'branch_id'       => $visit['branch_id'],
			'recipient_email' => $host['user_email'],
			'recipient_name'  => $host['first_name'] ?? $host['display_name'],
			'recipient_id'    => $host_user_id,
			'visit_id'        => $visit_id,
			'placeholders'    => array(
				'host_name'      => $host['first_name'] ?? $host['display_name'],
				'event_type'     => $event,
				'customer_name'  => $visit['customer_name'],
				'branch_name'    => $visit['branch_name'],
				'company_name'   => $visit['company_name'] ?? '',
				'visitor_names'  => implode(', ', $visitor_names),
				'visitor_count'  => count($visitors),
				'date_from'      => $visit['planned_date_from'],
				'date_to'        => $visit['planned_date_to'],
				'time_from'      => $visit['planned_time_from'] ?? '',
				'time_to'        => $visit['planned_time_to'] ?? '',
				'purpose'        => $visit['purpose'] ?? '',
				'status'         => $visit['status'],
			),
		), $language);
	}
	
	public function send_password_reset($email, $token) {
		global $wpdb;
		
		$user = $wpdb->get_row($wpdb->prepare(
			"SELECT su.*, wu.user_email 
			 FROM {$wpdb->prefix}saw_users su
			 JOIN {$wpdb->prefix}users wu ON su.wp_user_id = wu.ID
			 WHERE wu.user_email = %s",
			$email
		), ARRAY_A);
		
		if (!$user) {
			return new WP_Error('user_not_found', 'User not found');
		}
		
		$reset_url = home_url('/reset-password/?token=' . $token);
		$language = $user['language_preference'] ?? 'cs';
		
		return $this->send('password_reset', array(
			'customer_id'     => $user['customer_id'],
			'recipient_email' => $email,
			'recipient_name'  => $user['first_name'],
			'recipient_id'    => $user['id'],
			'placeholders'    => array(
				'first_name' => $user['first_name'],
				'reset_url'  => $reset_url,
				'expires_in' => '1 hodinu',
			),
		), $language);
	}
	
	public function send_password_changed($user_id) {
		global $wpdb;
		
		$user = $wpdb->get_row($wpdb->prepare(
			"SELECT su.*, wu.user_email 
			 FROM {$wpdb->prefix}saw_users su
			 JOIN {$wpdb->prefix}users wu ON su.wp_user_id = wu.ID
			 WHERE su.id = %d",
			$user_id
		), ARRAY_A);
		
		if (!$user || empty($user['user_email'])) {
			return new WP_Error('user_not_found', 'User not found');
		}
		
		$language = $user['language_preference'] ?? 'cs';
		
		return $this->send('password_changed', array(
			'customer_id'     => $user['customer_id'],
			'recipient_email' => $user['user_email'],
			'recipient_name'  => $user['first_name'],
			'recipient_id'    => $user_id,
			'placeholders'    => array(
				'first_name'  => $user['first_name'],
				'changed_at'  => current_time('d.m.Y H:i'),
			),
		), $language);
	}
	
	public function send_welcome($user_id, $password = null, $language = 'cs') {
		global $wpdb;
		
		$user = $wpdb->get_row($wpdb->prepare(
			"SELECT su.*, wu.user_email, wu.user_login
			 FROM {$wpdb->prefix}saw_users su
			 JOIN {$wpdb->prefix}users wu ON su.wp_user_id = wu.ID
			 WHERE su.id = %d",
			$user_id
		), ARRAY_A);
		
		if (!$user || empty($user['user_email'])) {
			return new WP_Error('user_not_found', 'User not found');
		}
		
		$login_url = home_url('/admin/login/');
		
		return $this->send('welcome', array(
			'customer_id'     => $user['customer_id'],
			'recipient_email' => $user['user_email'],
			'recipient_name'  => $user['first_name'],
			'recipient_id'    => $user_id,
			'placeholders'    => array(
				'first_name'         => $user['first_name'],
				'last_name'          => $user['last_name'],
				'username'           => $user['user_login'],
				'temporary_password' => $password ?? '',
				'login_url'          => $login_url,
			),
		), $language);
	}
	
	public function send_risks_request($visit_id) {
		global $wpdb;
		
		$visit = $wpdb->get_row($wpdb->prepare(
			"SELECT 
				v.id, v.customer_id, v.branch_id, 
				v.invitation_email, v.invitation_token,
				b.name as branch_name,
				cust.name as customer_name
			 FROM {$wpdb->prefix}saw_visits v
			 INNER JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
			 INNER JOIN {$wpdb->prefix}saw_customers cust ON v.customer_id = cust.id
			 WHERE v.id = %d",
			$visit_id
		), ARRAY_A);
		
		if (!$visit) {
			return new WP_Error('visit_not_found', 'Návštěva nenalezena');
		}
		
		if (empty($visit['invitation_email'])) {
			return new WP_Error('no_email', 'Návštěva nemá vyplněný email pro pozvánku');
		}
		
		if (empty($visit['invitation_token'])) {
			$visit['invitation_token'] = $this->generate_invitation_token($visit_id);
		}
		
		$risks_url = home_url('/visitor-invitation/' . $visit['invitation_token'] . '/?step=risks');
		
		return $this->send('risks_request', array(
			'customer_id'     => $visit['customer_id'],
			'branch_id'       => $visit['branch_id'],
			'recipient_email' => $visit['invitation_email'],
			'recipient_name'  => '',
			'recipient_type'  => 'contact',
			'visit_id'        => $visit_id,
			'placeholders'    => array(
				'customer_name' => $visit['customer_name'],
				'branch_name'   => $visit['branch_name'],
				'risks_url'     => $risks_url,
			),
		));
	}
	
	private function generate_invitation_token($visit_id) {
		global $wpdb;
		
		$token = wp_generate_password(32, false);
		$expires = date('Y-m-d H:i:s', strtotime('+30 days'));
		
		$wpdb->update(
			$wpdb->prefix . 'saw_visits',
			array(
				'invitation_token' => $token,
				'invitation_token_expires_at' => $expires,
			),
			array('id' => $visit_id)
		);
		
		return $token;
	}
}

function saw_email() {
	return SAW_Email_Service::instance();
}