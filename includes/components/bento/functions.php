<?php
/**
 * SAW Bento Helper Functions
 * 
 * Snadné helper funkce pro použití v šablonách.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Bento renderer instance
 * 
 * @return SAW_Bento_Renderer
 */
function saw_bento() {
    return SAW_Bento_Renderer::instance();
}

/**
 * Start Bento grid
 * 
 * @param string $class Additional CSS classes
 * @param array $attrs Additional HTML attributes
 */
function saw_bento_start($class = '', $attrs = []) {
    saw_bento()->start_grid($class, $attrs);
}

/**
 * End Bento grid
 */
function saw_bento_end() {
    saw_bento()->end_grid();
}

/**
 * Render Bento header card
 * 
 * @param array $args Header arguments
 */
function saw_bento_header($args) {
    saw_bento()->render('header', $args);
}

/**
 * Render Bento stat card
 * 
 * @param array $args Stat arguments
 */
function saw_bento_stat($args) {
    saw_bento()->render('stat', $args);
}

/**
 * Render multiple stat cards in a row
 * 
 * @param array $stats Array of stat configurations
 */
function saw_bento_stats($stats) {
    foreach ($stats as $stat) {
        saw_bento_stat($stat);
    }
}

/**
 * Render Bento info card
 * 
 * @param array $args Info arguments
 */
function saw_bento_info($args) {
    saw_bento()->render('info', $args);
}

/**
 * Render Bento address card
 * 
 * @param array $args Address arguments
 */
function saw_bento_address($args) {
    saw_bento()->render('address', $args);
}

/**
 * Render Bento contact card
 * 
 * @param array $args Contact arguments
 */
function saw_bento_contact($args) {
    saw_bento()->render('contact', $args);
}

/**
 * Render Bento list card
 * 
 * @param array $args List arguments
 */
function saw_bento_list($args) {
    saw_bento()->render('list', $args);
}

/**
 * Render Bento text card
 * 
 * @param array $args Text arguments
 */
function saw_bento_text($args) {
    saw_bento()->render('text', $args);
}

/**
 * Render Bento image card
 * 
 * @param array $args Image arguments
 */
function saw_bento_image($args) {
    saw_bento()->render('image', $args);
}

/**
 * Render Bento timeline card
 * 
 * @param array $args Timeline arguments
 */
function saw_bento_timeline($args) {
    saw_bento()->render('timeline', $args);
}

/**
 * Render Bento status grid
 * 
 * @param array $args Status grid arguments
 */
function saw_bento_status_grid($args) {
    saw_bento()->render('status-grid', $args);
}

/**
 * Render Bento schedule card
 * 
 * @param array $args Schedule arguments
 */
function saw_bento_schedule($args) {
    saw_bento()->render('schedule', $args);
}

/**
 * Render Bento visitors card
 * 
 * @param array $args Visitors arguments
 */
function saw_bento_visitors($args) {
    saw_bento()->render('visitors', $args);
}

/**
 * Render Bento history table
 * 
 * @param array $args History table arguments
 */
function saw_bento_history_table($args) {
    saw_bento()->render('history-table', $args);
}

/**
 * Render Bento status box
 * 
 * @param array $args Status box arguments
 */
function saw_bento_status_box($args) {
    saw_bento()->render('status-box', $args);
}

/**
 * Render Bento language tabs
 * 
 * @param array $args Language tabs arguments
 */
function saw_bento_language_tabs($args) {
    saw_bento()->render('language-tabs', $args);
}

/**
 * Render Bento expandable section
 * 
 * @param array $args Expandable arguments
 */
function saw_bento_expandable($args) {
    saw_bento()->render('expandable', $args);
}

/**
 * Render Bento meta card
 * 
 * @param array $args Meta arguments
 */
function saw_bento_meta($args) {
    saw_bento()->render('meta', $args);
}

/**
 * Render Bento actions card
 * 
 * @param array $args Actions arguments
 */
function saw_bento_actions($args) {
    saw_bento()->render('actions', $args);
}

/**
 * Render Bento merge panel
 * 
 * @param array $args Merge arguments
 */
function saw_bento_merge($args) {
    saw_bento()->render('merge', $args);
}

/**
 * Check if Bento design system is enabled
 * 
 * @return bool
 */
function saw_is_bento_enabled() {
    return SAW_Bento_Renderer::is_enabled();
}

/**
 * Initialize Bento system
 * Automatically loads when this file is included
 */
function saw_bento_init() {
    // Renderer is singleton, so just call instance to init
    SAW_Bento_Renderer::instance();
}

