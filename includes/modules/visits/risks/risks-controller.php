<?php
/**
 * Visit Risks Editor Controller
 * 
 * Handles editing of risk information for visits directly from admin.
 * Allows super_admin, admin, super_manager, and manager roles to add/edit
 * risk text and documents for any visit (especially walk-ins).
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits/Risks
 * @since       5.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visit_Risks_Controller {
    
    /**
     * Visit ID
     * @var int
     */
    private $visit_id;
    
    /**
     * Visit data
     * @var array
     */
    private $visit;
    
    /**
     * Current user's SAW role
     * @var string
     */
    private $user_role;
    
    /**
     * Translations
     * @var array
     */
    private $translations;
    
    /**
     * Current language
     * @var string
     */
    private $lang = 'cs';
    
    /**
     * Allowed roles for editing risks
     * @var array
     */
    private const ALLOWED_ROLES = ['super_admin', 'admin', 'super_manager', 'manager'];
    
    /**
     * Constructor
     * 
     * @param int $visit_id Visit ID
     */
    public function __construct($visit_id) {
        $this->visit_id = intval($visit_id);
        $this->detect_language();
        $this->load_translations();
    }
    
    /**
     * Initialize and render the page
     */
    public function init() {
        // Check permissions
        if (!$this->check_permissions()) {
            wp_die(
                $this->tr('error_no_permission'),
                $this->tr('error_forbidden'),
                ['response' => 403]
            );
        }
        
        // Load visit data
        if (!$this->load_visit()) {
            wp_die(
                $this->tr('error_visit_not_found'),
                $this->tr('error_not_found'),
                ['response' => 404]
            );
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_save();
            return;
        }
        
        // Render page
        $this->render();
    }
    
    /**
     * Check if current user has permission
     * 
     * @return bool
     */
    private function check_permissions() {
        // WordPress super admin always has access
        if (current_user_can('manage_options')) {
            $this->user_role = 'super_admin';
            return true;
        }
        
        // Check SAW role
        if (function_exists('saw_get_current_role')) {
            $this->user_role = saw_get_current_role();
            return in_array($this->user_role, self::ALLOWED_ROLES);
        }
        
        // Fallback: check if user can edit posts
        if (current_user_can('edit_posts')) {
            $this->user_role = 'manager';
            return true;
        }
        
        return false;
    }
    
    /**
     * Load visit data from database
     * 
     * @return bool
     */
    private function load_visit() {
        global $wpdb;
        
        $this->visit = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, 
                    c.name as customer_name,
                    b.name as branch_name,
                    comp.name as company_name,
                    (SELECT CONCAT(first_name, ' ', last_name) 
                     FROM {$wpdb->prefix}saw_visitors 
                     WHERE visit_id = v.id 
                     ORDER BY id ASC LIMIT 1) as first_visitor_name
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_customers c ON v.customer_id = c.id
             LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
             LEFT JOIN {$wpdb->prefix}saw_companies comp ON v.company_id = comp.id
             WHERE v.id = %d",
            $this->visit_id
        ), ARRAY_A);
        
        if (!$this->visit) {
            return false;
        }
        
        // Check customer isolation (non-super_admin can only edit their customer's visits)
        if ($this->user_role !== 'super_admin') {
            $current_customer_id = null;
            
            if (class_exists('SAW_Context')) {
                $current_customer_id = SAW_Context::get_customer_id();
            }
            
            if ($current_customer_id && intval($this->visit['customer_id']) !== $current_customer_id) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Handle form submission
     */
    private function handle_save() {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'saw_edit_visit_risks_' . $this->visit_id)) {
            wp_die($this->tr('error_invalid_nonce'));
        }
        
        $risks_text = wp_kses_post($_POST['risks_text'] ?? '');
        $delete_files = isset($_POST['delete_files']) ? (array) $_POST['delete_files'] : [];
        
        // ========================================
        // 1. Save/Update text content
        // ========================================
        $existing_text_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'text'
             ORDER BY uploaded_at DESC LIMIT 1",
            $this->visit_id
        ));
        
        if (!empty(trim($risks_text))) {
            if ($existing_text_id) {
                // Update existing
                $wpdb->update(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    [
                        'text_content' => $risks_text,
                        'uploaded_at' => current_time('mysql')
                    ],
                    ['id' => $existing_text_id],
                    ['%s', '%s'],
                    ['%d']
                );
            } else {
                // Insert new
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    [
                        'visit_id' => $this->visit_id,
                        'customer_id' => $this->visit['customer_id'],
                        'branch_id' => $this->visit['branch_id'],
                        'company_id' => $this->visit['company_id'] ?: null,
                        'material_type' => 'text',
                        'text_content' => $risks_text,
                        'uploaded_at' => current_time('mysql')
                    ]
                );
            }
        } else {
            // If text is empty, delete existing text record
            if ($existing_text_id) {
                $wpdb->delete(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    ['id' => $existing_text_id]
                );
            }
        }
        
        // ========================================
        // 2. Delete marked files
        // ========================================
        if (!empty($delete_files)) {
            $upload_dir = wp_upload_dir();
            
            foreach ($delete_files as $file_id) {
                $file = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials 
                     WHERE id = %d AND visit_id = %d AND material_type = 'document'",
                    intval($file_id),
                    $this->visit_id
                ));
                
                if ($file && !empty($file->file_path)) {
                    $file_path = $upload_dir['basedir'] . $file->file_path;
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
                
                $wpdb->delete(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    ['id' => intval($file_id), 'visit_id' => $this->visit_id]
                );
            }
        }
        
        // ========================================
        // 3. Upload new files
        // ========================================
        if (!empty($_FILES['risks_documents']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            
            $upload_overrides = ['test_form' => false];
            $upload_dir = wp_upload_dir();
            
            $allowed_types = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png',
                'image/gif'
            ];
            
            foreach ($_FILES['risks_documents']['name'] as $key => $name) {
                if (empty($name)) continue;
                
                $file = [
                    'name' => $_FILES['risks_documents']['name'][$key],
                    'type' => $_FILES['risks_documents']['type'][$key],
                    'tmp_name' => $_FILES['risks_documents']['tmp_name'][$key],
                    'error' => $_FILES['risks_documents']['error'][$key],
                    'size' => $_FILES['risks_documents']['size'][$key],
                ];
                
                // Validate file type
                if (!in_array($file['type'], $allowed_types)) {
                    continue;
                }
                
                // Validate file size (max 10MB)
                if ($file['size'] > 10 * 1024 * 1024) {
                    continue;
                }
                
                $movefile = wp_handle_upload($file, $upload_overrides);
                
                if ($movefile && !isset($movefile['error'])) {
                    $relative_path = str_replace($upload_dir['basedir'], '', $movefile['file']);
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'saw_visit_invitation_materials',
                        [
                            'visit_id' => $this->visit_id,
                            'customer_id' => $this->visit['customer_id'],
                            'branch_id' => $this->visit['branch_id'],
                            'company_id' => $this->visit['company_id'] ?: null,
                            'material_type' => 'document',
                            'file_name' => sanitize_file_name($file['name']),
                            'file_path' => $relative_path,
                            'file_size' => $file['size'],
                            'mime_type' => $movefile['type'],
                            'uploaded_at' => current_time('mysql'),
                        ]
                    );
                }
            }
        }
        
        // ========================================
        // 4. Clear cache and redirect
        // ========================================
        if (class_exists('SAW_Cache')) {
            SAW_Cache::delete('visit_' . $this->visit_id, 'visits');
            SAW_Cache::flush('visits');
        }
        
        // Redirect back to visit detail
        $redirect_url = $this->get_return_url();
        
        // Add success message via transient
        set_transient('saw_risks_saved_' . $this->visit_id, true, 30);
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Get URL to return to after saving
     * 
     * @return string
     */
    private function get_return_url() {
        // Check if we have a return URL in the request
        if (!empty($_GET['return_url'])) {
            return esc_url_raw(urldecode($_GET['return_url']));
        }
        
        // Default: visits list with detail modal
        return home_url('/admin/visits/' . $this->visit_id);
    }
    
    /**
     * Get edit URL for this controller
     * 
     * @param int $visit_id
     * @param string|null $return_url Optional return URL
     * @return string
     */
    public static function get_edit_url($visit_id, $return_url = null) {
        $url = admin_url('admin.php?page=saw-visits&action=edit-risks&visit_id=' . intval($visit_id));
        
        if ($return_url) {
            $url .= '&return_url=' . urlencode($return_url);
        }
        
        return $url;
    }
    
    /**
     * Check if current user can edit risks
     * 
     * @return bool
     */
    public static function can_edit_risks() {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (function_exists('saw_get_current_role')) {
            return in_array(saw_get_current_role(), self::ALLOWED_ROLES);
        }
        
        return current_user_can('edit_posts');
    }
    
    /**
     * Render the edit page
     */
    private function render() {
        // Enqueue required assets
        $this->enqueue_assets();
        
        // Load existing data
        $existing_data = $this->load_existing_data();
        
        // Prepare template variables
        $visit = $this->visit;
        $visit_id = $this->visit_id;
        $risks_text = $existing_data['text'];
        $existing_docs = $existing_data['documents'];
        $return_url = $this->get_return_url();
        $controller = $this;
        
        // Check for success message
        $show_success = get_transient('saw_risks_saved_' . $this->visit_id);
        if ($show_success) {
            delete_transient('saw_risks_saved_' . $this->visit_id);
        }
        
        // Load template
        $template = __DIR__ . '/risks-template.php';
        
        if (file_exists($template)) {
            include $template;
        } else {
            wp_die('Template not found: ' . $template);
        }
    }
    
    /**
     * Load existing risks data
     * 
     * @return array
     */
    private function load_existing_data() {
        global $wpdb;
        
        $text = $wpdb->get_var($wpdb->prepare(
            "SELECT text_content FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'text'
             ORDER BY uploaded_at DESC LIMIT 1",
            $this->visit_id
        ));
        
        $documents = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'document'
             ORDER BY uploaded_at ASC",
            $this->visit_id
        ), ARRAY_A);
        
        return [
            'text' => $text ?? '',
            'documents' => $documents ?? []
        ];
    }
    
    /**
     * Enqueue required assets
     */
    private function enqueue_assets() {
        // WordPress editor
        wp_enqueue_editor();
        wp_enqueue_media();
        
        // Richtext editor component
        $richtext_file = SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.php';
        if (file_exists($richtext_file)) {
            require_once $richtext_file;
            if (function_exists('saw_richtext_editor_init')) {
                saw_richtext_editor_init();
            }
            if (function_exists('saw_richtext_editor_enqueue_assets')) {
                saw_richtext_editor_enqueue_assets();
            }
        }
    }
    
    /**
     * Detect current language
     */
    private function detect_language() {
        if (class_exists('SAW_Component_Language_Switcher')) {
            $this->lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        if (!in_array($this->lang, ['cs', 'en'])) {
            $this->lang = 'cs';
        }
    }
    
    /**
     * Load translations
     */
    private function load_translations() {
        $all_translations = [
            'cs' => [
                'page_title' => 'Editace rizik',
                'page_subtitle' => 'Informace o rizicích pro návštěvu',
                'breadcrumb_visits' => 'Návštěvy',
                'breadcrumb_detail' => 'Detail',
                'breadcrumb_risks' => 'Rizika',
                
                'visit_info' => 'Návštěva',
                'visit_type_planned' => 'Plánovaná',
                'visit_type_walk_in' => 'Walk-in',
                
                'section_text' => 'Textový popis rizik',
                'section_text_help' => 'Popište potenciální rizika, která návštěvník přináší nebo kterým může být vystaven.',
                'section_documents' => 'Dokumenty',
                'section_documents_help' => 'Nahrajte relevantní dokumenty (bezpečnostní listy, certifikáty, OOPP požadavky).',
                
                'upload_zone_text' => 'Přetáhněte soubory sem nebo',
                'upload_zone_button' => 'vyberte soubory',
                'upload_zone_help' => 'PDF, Word, obrázky do 10 MB',
                
                'existing_files' => 'Nahrané soubory',
                'new_files' => 'Nově vybrané soubory',
                'no_files' => 'Zatím nejsou nahrány žádné soubory',
                'delete_file' => 'Smazat soubor',
                'undo' => 'Vrátit zpět',
                
                'btn_save' => 'Uložit změny',
                'btn_cancel' => 'Zrušit',
                'btn_save_close' => 'Uložit a zavřít',
                
                'success_saved' => 'Změny byly úspěšně uloženy.',
                
                'error_no_permission' => 'Nemáte oprávnění k úpravě rizik.',
                'error_forbidden' => 'Přístup odepřen',
                'error_visit_not_found' => 'Návštěva nebyla nalezena.',
                'error_not_found' => 'Nenalezeno',
                'error_invalid_nonce' => 'Neplatný bezpečnostní token.',
            ],
            'en' => [
                'page_title' => 'Edit Risks',
                'page_subtitle' => 'Risk information for visit',
                'breadcrumb_visits' => 'Visits',
                'breadcrumb_detail' => 'Detail',
                'breadcrumb_risks' => 'Risks',
                
                'visit_info' => 'Visit',
                'visit_type_planned' => 'Planned',
                'visit_type_walk_in' => 'Walk-in',
                
                'section_text' => 'Risk Description',
                'section_text_help' => 'Describe potential risks that the visitor brings or may be exposed to.',
                'section_documents' => 'Documents',
                'section_documents_help' => 'Upload relevant documents (safety data sheets, certificates, PPE requirements).',
                
                'upload_zone_text' => 'Drag files here or',
                'upload_zone_button' => 'browse files',
                'upload_zone_help' => 'PDF, Word, images up to 10 MB',
                
                'existing_files' => 'Uploaded files',
                'new_files' => 'Newly selected files',
                'no_files' => 'No files uploaded yet',
                'delete_file' => 'Delete file',
                'undo' => 'Undo',
                
                'btn_save' => 'Save changes',
                'btn_cancel' => 'Cancel',
                'btn_save_close' => 'Save and close',
                
                'success_saved' => 'Changes saved successfully.',
                
                'error_no_permission' => 'You do not have permission to edit risks.',
                'error_forbidden' => 'Access denied',
                'error_visit_not_found' => 'Visit not found.',
                'error_not_found' => 'Not found',
                'error_invalid_nonce' => 'Invalid security token.',
            ]
        ];
        
        $this->translations = $all_translations[$this->lang] ?? $all_translations['cs'];
    }
    
    /**
     * Get translation
     * 
     * @param string $key
     * @param string $fallback
     * @return string
     */
    public function tr($key, $fallback = '') {
        return $this->translations[$key] ?? ($fallback ?: $key);
    }
}