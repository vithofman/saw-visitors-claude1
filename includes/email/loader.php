<?php
if (!defined('ABSPATH')) { exit; }

/**
 * SAW Email System Loader
 * 
 * Inicializuje email službu a registruje WordPress hooky.
 */

// Načtení tříd
require_once __DIR__ . '/class-saw-email-logger.php';
require_once __DIR__ . '/class-saw-email-template.php';
require_once __DIR__ . '/class-saw-email-service.php';

/**
 * Získání instance email služby
 * 
 * @return SAW_Email_Service
 */
function saw_email() {
	return SAW_Email_Service::instance();
}

/**
 * Registrace automatických triggerů pro emaily
 */
class SAW_Email_Hooks {
	
	public static function register() {
		// Návštěvy
		add_action('saw_visit_created', array(__CLASS__, 'on_visit_created'), 10, 2);
		add_action('saw_visit_updated', array(__CLASS__, 'on_visit_updated'), 10, 3);
		add_action('saw_walkin_registered', array(__CLASS__, 'on_walkin_registered'), 10, 2);
		add_action('saw_visitor_checkin', array(__CLASS__, 'on_visitor_checkin'), 10, 2);
		
		// Uživatelé
		add_action('saw_user_created', array(__CLASS__, 'on_user_created'), 10, 3);
		add_action('saw_password_reset_requested', array(__CLASS__, 'on_password_reset_requested'), 10, 2);
		add_action('saw_password_changed', array(__CLASS__, 'on_password_changed'), 10, 1);
	}
	
	/**
	 * Nová návštěva vytvořena
	 */
	public static function on_visit_created($visit_id, $visit_data) {
		self::notify_hosts($visit_id, 'new');
	}
	
	/**
	 * Návštěva aktualizována
	 */
	public static function on_visit_updated($visit_id, $old_data, $new_data) {
		$dominated_fields = array('planned_date_from', 'planned_date_to', 'planned_time_from', 'planned_time_to', 'status');
		
		$has_changes = false;
		foreach ($dominated_fields as $field) {
			if (($old_data[$field] ?? '') !== ($new_data[$field] ?? '')) {
				$has_changes = true;
				break;
			}
		}
		
		if ($has_changes) {
			self::notify_hosts($visit_id, 'updated');
		}
	}
	
	/**
	 * Walk-in registrace
	 */
	public static function on_walkin_registered($visit_id, $visitor_data) {
		self::notify_hosts($visit_id, 'walkin');
	}
	
	/**
	 * Návštěvník přišel (check-in)
	 */
	public static function on_visitor_checkin($visit_id, $visitor_id) {
		self::notify_hosts($visit_id, 'checkin');
	}
	
	/**
	 * Nový uživatel vytvořen
	 */
	public static function on_user_created($user_id, $user_data, $password = null) {
		$language = $user_data['language_preference'] ?? 'cs';
		saw_email()->send_welcome($user_id, $password, $language);
	}
	
	/**
	 * Žádost o reset hesla
	 */
	public static function on_password_reset_requested($email, $token) {
		saw_email()->send_password_reset($email, $token);
	}
	
	/**
	 * Heslo změněno
	 */
	public static function on_password_changed($user_id) {
		saw_email()->send_password_changed($user_id);
	}
	
	/**
	 * Notifikace hostitelům návštěvy
	 */
	private static function notify_hosts($visit_id, $event) {
		global $wpdb;
		
		$host_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
			$visit_id
		));
		
		if (empty($host_ids)) {
			return;
		}
		
		foreach ($host_ids as $host_id) {
			saw_email()->send_host_notification($visit_id, $host_id, $event);
		}
	}
}

// Registrace hooků
SAW_Email_Hooks::register();