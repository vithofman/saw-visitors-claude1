<?php
/**
 * Settings Module Model
 *
 * @package SAW_Visitors
 * @subpackage Modules\Settings
 * @since 4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
if (!class_exists('SAW_Base_Model')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
}

class SAW_Settings_Model extends SAW_Base_Model {
    
    public function __construct($config = []) {
        parent::__construct();
    }
}
