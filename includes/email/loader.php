<?php
/**
 * SAW Email System Loader
 * 
 * @package SAW_Visitors
 * @since 1.0.0
 */

if (!defined('ABSPATH')) { 
    exit; 
}

$email_dir = __DIR__;

// Kontrola existence souborů
if (!file_exists($email_dir . '/class-saw-email-logger.php') ||
    !file_exists($email_dir . '/class-saw-email-template.php') ||
    !file_exists($email_dir . '/class-saw-email-service.php')) {
    return;
}

// Načtení tříd
require_once $email_dir . '/class-saw-email-logger.php';
require_once $email_dir . '/class-saw-email-template.php';
require_once $email_dir . '/class-saw-email-service.php';

/**
 * Získání instance email služby
 * 
 * @return SAW_Email_Service|null
 */
if (!function_exists('saw_email')) {
    function saw_email() {
        if (!class_exists('SAW_Email_Service')) {
            return null;
        }
        return SAW_Email_Service::instance();
    }
}

/**
 * Kontrola dostupnosti email služby
 * 
 * @return bool
 */
if (!function_exists('saw_email_available')) {
    function saw_email_available() {
        return class_exists('SAW_Email_Service');
    }
}