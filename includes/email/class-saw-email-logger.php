<?php
if (!defined('ABSPATH')) { exit; }

class SAW_Email_Logger {
	
	private $table;
	
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'saw_email_logs';
	}
	
	public function log($data) {
		global $wpdb;
		
		$insert_data = array(
			'customer_id'     => intval($data['customer_id'] ?? 0),
			'branch_id'       => !empty($data['branch_id']) ? intval($data['branch_id']) : null,
			'recipient_email' => sanitize_email($data['recipient_email']),
			'recipient_name'  => sanitize_text_field($data['recipient_name'] ?? ''),
			'recipient_type'  => $data['recipient_type'] ?? 'other',
			'recipient_id'    => !empty($data['recipient_id']) ? intval($data['recipient_id']) : null,
			'email_type'      => sanitize_key($data['email_type']),
			'subject'         => sanitize_text_field($data['subject']),
			'body_html'       => $data['body_html'] ?? null,
			'body_text'       => $data['body_text'] ?? null,
			'language'        => $data['language'] ?? 'cs',
			'visit_id'        => !empty($data['visit_id']) ? intval($data['visit_id']) : null,
			'visitor_id'      => !empty($data['visitor_id']) ? intval($data['visitor_id']) : null,
			'status'          => $data['status'] ?? 'sent',
			'error_message'   => $data['error_message'] ?? null,
			'sent_by'         => !empty($data['sent_by']) ? intval($data['sent_by']) : null,
			'headers'         => isset($data['headers']) ? wp_json_encode($data['headers']) : null,
			'meta'            => isset($data['meta']) ? wp_json_encode($data['meta']) : null,
			'created_at'      => current_time('mysql'),
			'sent_at'         => ($data['status'] ?? 'sent') === 'sent' ? current_time('mysql') : null,
		);
		
		$result = $wpdb->insert($this->table, $insert_data);
		
		return $result ? $wpdb->insert_id : false;
	}
	
	public function get_by_visit($visit_id) {
		global $wpdb;
		
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE visit_id = %d ORDER BY created_at DESC",
			$visit_id
		), ARRAY_A);
	}
	
	public function get_by_visitor($visitor_id) {
		global $wpdb;
		
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE visitor_id = %d ORDER BY created_at DESC",
			$visitor_id
		), ARRAY_A);
	}
	
	public function get_by_recipient($email, $customer_id = null) {
		global $wpdb;
		
		$sql = "SELECT * FROM {$this->table} WHERE recipient_email = %s";
		$params = array($email);
		
		if ($customer_id) {
			$sql .= " AND customer_id = %d";
			$params[] = $customer_id;
		}
		
		$sql .= " ORDER BY created_at DESC LIMIT 50";
		
		return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
	}
	
	public function update_status($log_id, $status, $error = null) {
		global $wpdb;
		
		$data = array('status' => $status);
		
		if ($status === 'sent') {
			$data['sent_at'] = current_time('mysql');
			$data['error_message'] = null;
		} elseif ($error) {
			$data['error_message'] = $error;
		}
		
		return $wpdb->update($this->table, $data, array('id' => $log_id)) !== false;
	}
	
	public function get_failed($customer_id, $limit = 50) {
		global $wpdb;
		
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$this->table} 
			 WHERE customer_id = %d AND status = 'failed'
			 ORDER BY created_at DESC
			 LIMIT %d",
			$customer_id,
			$limit
		), ARRAY_A);
	}
}