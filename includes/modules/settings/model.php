<?php
/**
 * Settings Module Model
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Settings_Model 
{
    /**
     * Get customer data by ID
     *
     * @param int $customer_id
     * @return array|null
     */
    public function get_customer($customer_id) {
        global $wpdb;
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, ico, dic, logo_url, primary_color, created_at, updated_at 
             FROM %i 
             WHERE id = %d",
            $wpdb->prefix . 'saw_customers',
            $customer_id
        ), ARRAY_A);
        
        return $customer ?: null;
    }
    
    /**
     * Update customer data
     *
     * @param int $customer_id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update_customer($customer_id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['ico'])) {
            $ico = sanitize_text_field($data['ico']);
            $update_data['ico'] = preg_replace('/[^0-9]/', '', $ico);
        }
        
        if (isset($data['dic'])) {
            $dic = sanitize_text_field($data['dic']);
            $update_data['dic'] = preg_replace('/[^A-Z0-9]/', '', strtoupper($dic));
        }
        
        if (isset($data['logo'])) {
            $update_data['logo'] = sanitize_file_name($data['logo']);
        }
        
        if (isset($data['logo_url'])) {
            $update_data['logo_url'] = esc_url_raw($data['logo_url']);
        }
        
        if (isset($data['primary_color'])) {
            $color = sanitize_text_field($data['primary_color']);
            if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
                $update_data['primary_color'] = $color;
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'Žádná data k aktualizaci');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_customers',
            $update_data,
            array('id' => $customer_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Chyba při ukládání do databáze');
        }
        
        delete_transient('saw_customer_' . $customer_id);
        
        return true;
    }
    
    /**
     * Delete customer logo
     *
     * @param int $customer_id
     * @return bool
     */
    public function delete_logo($customer_id) {
        global $wpdb;
        
        $logo_url = $wpdb->get_var($wpdb->prepare(
            "SELECT logo_url FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_customers',
            $customer_id
        ));
        
        if ($logo_url) {
            $filename = basename($logo_url);
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/saw-visitors/customers/' . $filename;
            
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        $wpdb->update(
            $wpdb->prefix . 'saw_customers',
            array('logo_url' => null),
            array('id' => $customer_id),
            array('%s'),
            array('%d')
        );
        
        delete_transient('saw_customer_' . $customer_id);
        
        return true;
    }
}
