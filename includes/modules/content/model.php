<?php
/**
 * Content Module Model
 *
 * @package SAW_Visitors
 * @version 2.0.0
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
     * Get document types
     *
     * @return array
     */
    public function get_document_types() {
        global $wpdb;
        
        $types = $wpdb->get_results(
            "SELECT id, name, description FROM " . $wpdb->prefix . "saw_training_document_types ORDER BY sort_order ASC",
            ARRAY_A
        );
        
        return $types ?: array();
    }
    
    /**
     * Get content for specific context
     *
     * @param int $customer_id
     * @param int $branch_id
     * @param int $language_id
     * @return array|null
     */
    public function get_content($customer_id, $branch_id, $language_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
            $wpdb->prefix . 'saw_training_content',
            $customer_id,
            $branch_id,
            $language_id
        ), ARRAY_A);
    }
    
    /**
     * Get department content
     *
     * @param int $content_id
     * @param int $department_id
     * @return string
     */
    public function get_department_content($content_id, $department_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT text_content FROM %i WHERE training_content_id = %d AND department_id = %d",
            $wpdb->prefix . 'saw_training_department_content',
            $content_id,
            $department_id
        ));
        
        return $result ?: '';
    }
    
    /**
     * Get department content ID
     *
     * @param int $content_id
     * @param int $department_id
     * @return int|null
     */
    public function get_department_content_id($content_id, $department_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i WHERE training_content_id = %d AND department_id = %d",
            $wpdb->prefix . 'saw_training_department_content',
            $content_id,
            $department_id
        ));
    }
    
    /**
     * Get documents by type and reference
     *
     * @param string $type
     * @param int $reference_id
     * @return array
     */
    public function get_documents($type, $reference_id, $customer_id = null, $branch_id = null) {
        global $wpdb;
        
        $where = "document_type = %s AND reference_id = %d";
        $params = array($type, $reference_id);
        $formats = array('%s', '%d');
        
        if ($customer_id !== null) {
            $where .= " AND customer_id = %d";
            $params[] = $customer_id;
            $formats[] = '%d';
        }
        
        if ($branch_id !== null) {
            $where .= " AND branch_id = %d";
            $params[] = $branch_id;
            $formats[] = '%d';
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}saw_training_documents WHERE {$where} ORDER BY uploaded_at ASC";
        
        return $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
    }
    
    /**
     * Get or create training content record
     *
     * @param int $customer_id
     * @param int $branch_id
     * @param int $language_id
     * @return int Content ID
     */
    public function get_or_create_content($customer_id, $branch_id, $language_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_training_content';
        
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM %i WHERE customer_id = %d AND branch_id = %d AND language_id = %d",
            $table,
            $customer_id,
            $branch_id,
            $language_id
        ));
        
        if ($content) {
            return $content->id;
        }
        
        $wpdb->insert(
            $table,
            array(
                'customer_id' => $customer_id,
                'branch_id' => $branch_id,
                'language_id' => $language_id,
            ),
            array('%d', '%d', '%d')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Save main content
     *
     * @param int $content_id
     * @param array $data
     * @return bool
     */
    public function save_main_content($content_id, $data) {
        global $wpdb;
        
        // Build update parts - only update non-empty values
        $update_parts = array();
        $values = array();
        
        if (isset($data['video_url']) && $data['video_url'] !== '') {
            $update_parts[] = 'video_url = %s';
            $values[] = $data['video_url'];
        }
        
        if (isset($data['pdf_map_path']) && $data['pdf_map_path'] !== '') {
            $update_parts[] = 'pdf_map_path = %s';
            $values[] = $data['pdf_map_path'];
        }
        
        if (isset($data['risks_text']) && $data['risks_text'] !== '') {
            $update_parts[] = 'risks_text = %s';
            $values[] = $data['risks_text'];
        }
        
        if (isset($data['additional_text']) && $data['additional_text'] !== '') {
            $update_parts[] = 'additional_text = %s';
            $values[] = $data['additional_text'];
        }
        
        // If nothing to update, return true
        if (empty($update_parts)) {
            return true;
        }
        
        $values[] = $content_id;
        
        $sql = "UPDATE " . $wpdb->prefix . "saw_training_content SET " . 
               implode(', ', $update_parts) . 
               " WHERE id = %d";
        
        return $wpdb->query($wpdb->prepare($sql, $values)) !== false;
    }
    
    /**
     * Save department content
     *
     * @param int $content_id
     * @param int $department_id
     * @param string $text
     * @return int Department content ID
     */
    public function save_department_content($content_id, $department_id, $text, $customer_id = null, $branch_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'saw_training_department_content';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM %i WHERE training_content_id = %d AND department_id = %d",
            $table,
            $content_id,
            $department_id
        ));
        
        if ($existing) {
            $update_data = array('text_content' => $text);
            $update_format = array('%s');
            
            // Update customer_id and branch_id if provided
            if ($customer_id !== null) {
                $update_data['customer_id'] = $customer_id;
                $update_format[] = '%d';
            }
            if ($branch_id !== null) {
                $update_data['branch_id'] = $branch_id;
                $update_format[] = '%d';
            }
            
            $wpdb->update(
                $table,
                $update_data,
                array('id' => $existing->id),
                $update_format,
                array('%d')
            );
            return $existing->id;
        }
        
        $insert_data = array(
            'training_content_id' => $content_id,
            'department_id' => $department_id,
            'text_content' => $text,
        );
        $insert_format = array('%d', '%d', '%s');
        
        // Add customer_id and branch_id if provided
        if ($customer_id !== null) {
            $insert_data['customer_id'] = $customer_id;
            $insert_format[] = '%d';
        }
        if ($branch_id !== null) {
            $insert_data['branch_id'] = $branch_id;
            $insert_format[] = '%d';
        }
        
        $wpdb->insert(
            $table,
            $insert_data,
            $insert_format
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Save document
     *
     * @param string $type
     * @param int $reference_id
     * @param string $file_path
     * @param string $file_name
     * @param int $file_size
     * @param string $mime_type
     * @param int $document_type_id
     * @param int $customer_id
     * @param int $branch_id
     * @return int Document ID
     */
    public function save_document($type, $reference_id, $file_path, $file_name, $file_size, $mime_type, $document_type_id = null, $customer_id = null, $branch_id = null) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'saw_training_documents',
            array(
                'document_type' => $type,
                'reference_id' => $reference_id,
                'file_path' => $file_path,
                'file_name' => $file_name,
                'file_size' => $file_size,
                'mime_type' => $mime_type,
                'document_type_id' => $document_type_id,
                'customer_id' => $customer_id,
                'branch_id' => $branch_id,
            ),
            array('%s', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%d')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Delete documents by type and reference
     *
     * @param string $type
     * @param int $reference_id
     * @return bool
     */
    public function delete_documents($type, $reference_id) {
        global $wpdb;
        
        // Get documents to delete files
        $documents = $wpdb->get_results($wpdb->prepare(
            "SELECT file_path FROM %i WHERE document_type = %s AND reference_id = %d",
            $wpdb->prefix . 'saw_training_documents',
            $type,
            $reference_id
        ));
        
        // Delete physical files
        $upload_dir = wp_upload_dir();
        foreach ($documents as $doc) {
            if ($doc->file_path) {
                $full_path = $upload_dir['basedir'] . $doc->file_path;
                if (file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
        }
        
        // Delete database records
        return $wpdb->delete(
            $wpdb->prefix . 'saw_training_documents',
            array(
                'document_type' => $type,
                'reference_id' => $reference_id,
            ),
            array('%s', '%d')
        ) !== false;
    }
    
    /**
     * Delete single document by ID
     *
     * @param int $doc_id
     * @return bool
     */
    public function delete_document_by_id($doc_id) {
        global $wpdb;
        
        // Get document info
        $doc = $wpdb->get_row($wpdb->prepare(
            "SELECT file_path FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_training_documents',
            $doc_id
        ));
        
        if ($doc && $doc->file_path) {
            // Delete physical file from server
            // file_path is stored as: /saw-training/documents/filename.pdf
            $upload_dir = wp_upload_dir();
            $full_path = $upload_dir['basedir'] . $doc->file_path;
            
            if (file_exists($full_path)) {
                $deleted = @unlink($full_path);
                
                // Log for debugging (optional - can be removed)
                if (!$deleted) {
                    error_log("SAW Content: Failed to delete file: " . $full_path);
                } else {
                    error_log("SAW Content: Successfully deleted file: " . $full_path);
                }
            } else {
                error_log("SAW Content: File not found for deletion: " . $full_path);
            }
            
            // Delete database record
            return $wpdb->delete(
                $wpdb->prefix . 'saw_training_documents',
                array('id' => $doc_id),
                array('%d')
            ) !== false;
        }
        
        return false;
    }
}
