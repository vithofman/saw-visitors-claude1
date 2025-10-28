<?php
/**
 * SAW Content Management Controller
 * Frontend správa školících materiálů
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Content {
    
    const LANGUAGES = array(
        'cs' => 'Čeština',
        'en' => 'English',
        'de' => 'Deutsch',
        'uk' => 'Українська',
    );
    
    const DOC_CATEGORIES = array(
        'emergency'     => 'Havarijní plány',
        'fire'          => 'Požární ochrana',
        'work_safety'   => 'Bezpečnost práce',
        'hygiene'       => 'Hygiena',
        'environment'   => 'Životní prostředí',
        'security'      => 'Bezpečnost',
        'other'         => 'Ostatní',
    );
    
    private $customer_id;
    
    public function __construct($customer_id) {
        $this->customer_id = $customer_id;
    }
    
    /**
     * Main index page
     */
    public function index() {
        global $wpdb;
        
        // Handle actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['saw_save_content']) && wp_verify_nonce($_POST['_wpnonce'], 'saw_save_content')) {
                $this->handle_save();
                return;
            }
        }
        
        if (isset($_GET['delete_material']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_material_' . $_GET['delete_material'])) {
                $this->delete_material(intval($_GET['delete_material']));
                wp_redirect('/admin/settings/content?deleted=1');
                exit;
            }
        }
        
        if (isset($_GET['delete_doc']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_doc_' . $_GET['delete_doc'])) {
                $this->delete_document(intval($_GET['delete_doc']));
                wp_redirect('/admin/settings/content?deleted=1');
                exit;
            }
        }
        
        // Load data
        $materials = $this->get_materials();
        $documents = $this->get_documents();
        $departments = $this->get_departments();
        $dept_materials = $this->get_dept_materials();
        
        // Pass controller instance to template
        $controller = $this;
        
        // Render template
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/content/index.php';
        $content = ob_get_clean();
        
        return $content;
    }
    
    /**
     * Handle save
     */
    private function handle_save() {
        global $wpdb;
        
        $success_count = 0;
        $error_count = 0;
        
        foreach (self::LANGUAGES as $lang_code => $lang_name) {
            // 1. VIDEO URL
            if (isset($_POST['video_url_' . $lang_code])) {
                $video_url = sanitize_text_field($_POST['video_url_' . $lang_code]);
                if (!empty($video_url)) {
                    $result = $this->save_material('video', $lang_code, $video_url, '', 'url');
                    $result ? $success_count++ : $error_count++;
                }
            }
            
            // 2. PDF UPLOAD
            if (isset($_FILES['pdf_file_' . $lang_code]) && $_FILES['pdf_file_' . $lang_code]['error'] === UPLOAD_ERR_OK) {
                $upload = $this->handle_file_upload($_FILES['pdf_file_' . $lang_code], 'pdf');
                if ($upload['success']) {
                    $result = $this->save_material('pdf', $lang_code, $upload['url'], $upload['filename'], 'file');
                    $result ? $success_count++ : $error_count++;
                } else {
                    $error_count++;
                }
            }
            
            // 3. RISKS WYSIWYG
            if (isset($_POST['risks_wysiwyg_' . $lang_code])) {
                $content = wp_kses_post($_POST['risks_wysiwyg_' . $lang_code]);
                $result = $this->save_material('risks_wysiwyg', $lang_code, '', '', 'wysiwyg', $content);
                $result ? $success_count++ : $error_count++;
            }
            
            // 4. RISKS DOCUMENTS
            if (isset($_FILES['risks_docs_' . $lang_code]) && is_array($_FILES['risks_docs_' . $lang_code]['name'])) {
                foreach ($_FILES['risks_docs_' . $lang_code]['name'] as $key => $filename) {
                    if ($_FILES['risks_docs_' . $lang_code]['error'][$key] === UPLOAD_ERR_OK) {
                        $file = array(
                            'name' => $_FILES['risks_docs_' . $lang_code]['name'][$key],
                            'type' => $_FILES['risks_docs_' . $lang_code]['type'][$key],
                            'tmp_name' => $_FILES['risks_docs_' . $lang_code]['tmp_name'][$key],
                            'error' => $_FILES['risks_docs_' . $lang_code]['error'][$key],
                            'size' => $_FILES['risks_docs_' . $lang_code]['size'][$key],
                        );
                        $upload = $this->handle_file_upload($file, 'document');
                        if ($upload['success']) {
                            $this->save_document('risks', $lang_code, $upload['url'], $upload['filename']);
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
            }
            
            // 5. ADDITIONAL INFO WYSIWYG
            if (isset($_POST['additional_wysiwyg_' . $lang_code])) {
                $content = wp_kses_post($_POST['additional_wysiwyg_' . $lang_code]);
                $result = $this->save_material('additional_wysiwyg', $lang_code, '', '', 'wysiwyg', $content);
                $result ? $success_count++ : $error_count++;
            }
            
            // 6. ADDITIONAL DOCUMENTS
            if (isset($_FILES['additional_docs_' . $lang_code]) && is_array($_FILES['additional_docs_' . $lang_code]['name'])) {
                foreach ($_FILES['additional_docs_' . $lang_code]['name'] as $key => $filename) {
                    if ($_FILES['additional_docs_' . $lang_code]['error'][$key] === UPLOAD_ERR_OK) {
                        $file = array(
                            'name' => $_FILES['additional_docs_' . $lang_code]['name'][$key],
                            'type' => $_FILES['additional_docs_' . $lang_code]['type'][$key],
                            'tmp_name' => $_FILES['additional_docs_' . $lang_code]['tmp_name'][$key],
                            'error' => $_FILES['additional_docs_' . $lang_code]['error'][$key],
                            'size' => $_FILES['additional_docs_' . $lang_code]['size'][$key],
                        );
                        $upload = $this->handle_file_upload($file, 'document');
                        if ($upload['success']) {
                            $this->save_document('additional', $lang_code, $upload['url'], $upload['filename']);
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
            }
            
            // 7. DEPARTMENT MATERIALS
            $departments = $this->get_departments();
            foreach ($departments as $dept) {
                $field_name = 'dept_wysiwyg_' . $dept->id . '_' . $lang_code;
                if (isset($_POST[$field_name])) {
                    $content = wp_kses_post($_POST[$field_name]);
                    $this->save_dept_material($dept->id, $lang_code, $content);
                    $success_count++;
                }
            }
        }
        
        if ($error_count > 0) {
            wp_redirect('/admin/settings/content?error=' . urlencode('Některé soubory se nepodařilo uložit.'));
        } else {
            wp_redirect('/admin/settings/content?saved=1');
        }
        exit;
    }
    
    /**
     * Handle file upload
     */
    private function handle_file_upload($file, $type = 'document') {
        $allowed_types = array(
            'pdf' => array('application/pdf'),
            'document' => array('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        );
        
        if (!in_array($file['type'], $allowed_types[$type])) {
            return array('success' => false, 'message' => 'Nepovolený typ souboru.');
        }
        
        $upload_dir = wp_upload_dir();
        $saw_dir = $upload_dir['basedir'] . '/saw-content/customer-' . $this->customer_id . '/';
        
        if (!file_exists($saw_dir)) {
            wp_mkdir_p($saw_dir);
        }
        
        $filename = sanitize_file_name($file['name']);
        $filename = time() . '_' . $filename;
        $filepath = $saw_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $file_url = $upload_dir['baseurl'] . '/saw-content/customer-' . $this->customer_id . '/' . $filename;
            return array(
                'success' => true,
                'url' => $file_url,
                'filename' => $filename,
            );
        }
        
        return array('success' => false, 'message' => 'Chyba při nahrávání souboru.');
    }
    
    /**
     * Save material
     */
    private function save_material($type, $language, $file_url = '', $filename = '', $content_type = 'file', $wysiwyg_content = '') {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_materials WHERE title = %s AND language = %s AND customer_id = %d",
            $type, $language, $this->customer_id
        ));
        
        if ($existing) {
            return $wpdb->update(
                $wpdb->prefix . 'saw_materials',
                array(
                    'file_url' => $file_url,
                    'filename' => $filename,
                    'content_type' => $content_type,
                    'wysiwyg_content' => $wysiwyg_content,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing->id)
            );
        } else {
            return $wpdb->insert(
                $wpdb->prefix . 'saw_materials',
                array(
                    'customer_id' => $this->customer_id,
                    'title' => $type,
                    'type' => $type,
                    'language' => $language,
                    'file_url' => $file_url,
                    'filename' => $filename,
                    'content_type' => $content_type,
                    'wysiwyg_content' => $wysiwyg_content,
                    'created_at' => current_time('mysql'),
                )
            );
        }
    }
    
    /**
     * Save document
     */
    private function save_document($category, $language, $file_url, $filename) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'saw_documents',
            array(
                'customer_id' => $this->customer_id,
                'category' => $category,
                'language' => $language,
                'file_url' => $file_url,
                'filename' => $filename,
                'created_at' => current_time('mysql'),
            )
        );
    }
    
    /**
     * Save department material
     */
    private function save_dept_material($dept_id, $language, $content) {
        global $wpdb;
        
        $wysiwyg_field = 'wysiwyg_' . $language;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_department_materials WHERE department_id = %d",
            $dept_id
        ));
        
        if ($existing) {
            return $wpdb->update(
                $wpdb->prefix . 'saw_department_materials',
                array(
                    $wysiwyg_field => $content,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $existing->id)
            );
        } else {
            return $wpdb->insert(
                $wpdb->prefix . 'saw_department_materials',
                array(
                    'department_id' => $dept_id,
                    $wysiwyg_field => $content,
                    'created_at' => current_time('mysql'),
                )
            );
        }
    }
    
    /**
     * Delete material
     */
    private function delete_material($material_id) {
        global $wpdb;
        $material = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_materials WHERE id = %d AND customer_id = %d",
            $material_id, $this->customer_id
        ));
        
        if (!$material) return false;
        
        if ($material->file_url) {
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $material->file_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        return $wpdb->delete($wpdb->prefix . 'saw_materials', array('id' => $material_id));
    }
    
    /**
     * Delete document
     */
    private function delete_document($doc_id) {
        global $wpdb;
        $doc = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_documents WHERE id = %d",
            $doc_id
        ));
        
        if (!$doc) return false;
        
        if ($doc->file_url) {
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $doc->file_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        return $wpdb->delete($wpdb->prefix . 'saw_documents', array('id' => $doc_id));
    }
    
    /**
     * Get materials
     */
    private function get_materials() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_materials 
            WHERE customer_id = %d 
            ORDER BY type, language",
            $this->customer_id
        ));
    }
    
    /**
     * Get material by type and language
     */
    public function get_material($materials, $type, $language) {
        foreach ($materials as $material) {
            if ($material->title === $type && $material->language === $language) {
                return $material;
            }
        }
        return null;
    }
    
    /**
     * Get documents
     */
    private function get_documents() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_documents 
            WHERE customer_id = %d 
            ORDER BY category, language, filename",
            $this->customer_id
        ));
    }
    
    /**
     * Get documents by category and language
     */
    public function get_documents_by_category($documents, $category, $language) {
        $result = array();
        foreach ($documents as $doc) {
            if ($doc->category === $category && $doc->language === $language) {
                $result[] = $doc;
            }
        }
        return $result;
    }
    
    /**
     * Get departments
     */
    private function get_departments() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_departments 
            WHERE customer_id = %d AND is_active = 1 
            ORDER BY name ASC",
            $this->customer_id
        ));
    }
    
    /**
     * Get department materials
     */
    private function get_dept_materials() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT dm.*, d.name as department_name 
            FROM {$wpdb->prefix}saw_department_materials dm
            LEFT JOIN {$wpdb->prefix}saw_departments d ON dm.department_id = d.id
            WHERE d.customer_id = %d",
            $this->customer_id
        ));
    }
    
    /**
     * Get dept material by department and language
     */
    public function get_dept_material($dept_materials, $dept_id, $language) {
        foreach ($dept_materials as $dm) {
            if ($dm->department_id == $dept_id) {
                $field = 'wysiwyg_' . $language;
                return isset($dm->$field) ? $dm->$field : '';
            }
        }
        return '';
    }
    
    /**
     * Get video embed HTML
     */
    public function get_video_embed($url) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $url, $matches);
            if (isset($matches[1])) {
                return '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $matches[1] . '" frameborder="0" allowfullscreen></iframe>';
            }
        }
        
        if (strpos($url, 'vimeo.com') !== false) {
            preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
            if (isset($matches[1])) {
                return '<iframe src="https://player.vimeo.com/video/' . $matches[1] . '" width="560" height="315" frameborder="0" allowfullscreen></iframe>';
            }
        }
        
        return '';
    }
    
    /**
     * Get file size
     */
    public function get_file_size($file_url) {
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
        
        if (file_exists($file_path)) {
            $size = filesize($file_path);
            if ($size < 1024) {
                return $size . ' B';
            } elseif ($size < 1024 * 1024) {
                return round($size / 1024, 2) . ' KB';
            } else {
                return round($size / (1024 * 1024), 2) . ' MB';
            }
        }
        return 'N/A';
    }
}