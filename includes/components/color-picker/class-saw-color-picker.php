<?php
/**
 * Color Picker Component
 *
 * Simple component wrapper for color picker functionality.
 * Handles asset enqueuing for color picker CSS and JavaScript.
 *
 * @package     SAW_Visitors
 * @subpackage  Components
 * @version     1.0.0
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Color Picker
 *
 * Lightweight component for color selection with asset management.
 *
 * @since 1.0.0
 */
class SAW_Color_Picker {
    
    /**
     * Constructor
     *
     * Initializes the color picker component.
     * Currently empty as no initialization is needed.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // No initialization needed
    }
    
    /**
     * Enqueue color picker assets
     *
     * Loads CSS and JavaScript files for color picker functionality.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        // Assets are now enqueued globally via SAW_Asset_Loader
        // to prevent FOUC on first page load. Do not re-enqueue here.
        return;
    }
}