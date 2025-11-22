<?php
/**
 * SAW Core Helper Functions
 *
 * Core utility functions for the SAW Visitors plugin.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      5.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================
 * NONCE VERIFICATION - UNIFIED AJAX WRAPPER
 * @since 5.1.0
 * ============================================
 */

/**
 * Unified AJAX nonce verification
 * 
 * Wrapper around existing saw_verify_ajax_nonce() for 'saw_ajax_nonce' action.
 * Standardizes AJAX nonce checks across the plugin.
 * 
 * IMPORTANT: This is a wrapper, not a replacement. It calls the existing
 * saw_verify_ajax_nonce() function from middleware.php with fixed action.
 * 
 * USAGE:
 *   Replace: check_ajax_referer('saw_ajax_nonce', 'nonce');
 *   With:    saw_verify_ajax_unified();
 * 
 * DO NOT use for:
 *   - Normal POST forms (use check_admin_referer)
 *   - File uploads (use saw_upload_file nonce)
 *   - Terminal (use saw_terminal_search/saw_terminal_step nonce)
 *   - Content module (use saw_content_action nonce)
 *   - Auth (use saw_set_password nonce)
 * 
 * @since 5.1.0
 * @return void Dies with JSON error if verification fails
 */
function saw_verify_ajax_unified() {
    // Use existing saw_verify_ajax_nonce() function from middleware.php
    // This function already handles audit logging and proper error responses
    saw_verify_ajax_nonce('saw_ajax_nonce');
}

