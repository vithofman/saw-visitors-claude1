<?php
/**
 * Content Module Model
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Content_Model 
{
    /**
     * Get training languages for customer
     *
     * @param int $customer_id
     * @return array
     */
    public function get_training_languages($customer_id) {
        global $wpdb;
        
        $languages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, language_name as name, language_code as code, flag_emoji 
             FROM %i 
             WHERE customer_id = %d
             ORDER BY language_name ASC",
            $wpdb->prefix . 'saw_training_languages',
            $customer_id
        ), ARRAY_A);
        
        return $languages ?: array();
    }
    
    /**
     * Get content for language
     *
     * @param int $customer_id
     * @param int $language_id
     * @return array
     */
    public function get_content($customer_id, $language_id) {
        // TODO: Implementovat načítání obsahu z databáze
        // Zatím vrací prázdná data
        return array(
            'video_url' => '',
            'pdf_map' => '',
            'risks_text' => '',
            'risks_documents' => array(),
            'additional_text' => '',
            'additional_documents' => array(),
        );
    }
}
