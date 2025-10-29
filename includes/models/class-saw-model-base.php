<?php
/**
 * Base Model Class
 *
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SAW_Model_Base {
    
    protected $db;
    protected $table_name;
    protected $fillable = [];
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . 'saw_' . $this->table_name;
    }
    
    abstract protected function validate($data, $id = null);
}