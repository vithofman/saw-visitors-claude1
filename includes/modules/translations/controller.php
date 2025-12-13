<?php
/**
 * Translations Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Translations
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}
if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

class SAW_Module_Translations_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/translations/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Translations_Model($this->config);
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Index action - display list view
     */
    public function index() {
        // Check if user is super admin
        if (!function_exists('saw_is_super_admin') || !saw_is_super_admin()) {
            wp_die('Nemáte oprávnění.', 403);
        }
        
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    /**
     * Override verify_module_access to check super admin
     */
    protected function verify_module_access() {
        // Translations module - only super admins
        if (!function_exists('saw_is_super_admin') || !saw_is_super_admin()) {
            wp_die('Nemáte oprávnění.', 403);
        }
        
        parent::verify_module_access();
    }
    
    /**
     * Enqueue module assets
     */
    public function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('translations');
    }
    
    /**
     * Get display name for detail header
     */
    public function get_display_name($item) {
        if (empty($item)) {
            return '';
        }
        
        $key = $item['translation_key'] ?? '';
        $context = $item['context'] ?? '';
        $lang = $item['language_code'] ?? '';
        
        return sprintf('%s [%s/%s]', $key, $context, $lang);
    }
    
    /**
     * Get header meta badges for detail sidebar
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta_parts = array();
        
        // Language badge
        if (!empty($item['language_code'])) {
            $lang_labels = array(
                'cs' => '🇨🇿 Čeština',
                'en' => '🇬🇧 English',
                'de' => '🇩🇪 Deutsch',
                'sk' => '🇸🇰 Slovenčina',
            );
            $lang_label = $lang_labels[$item['language_code']] ?? $item['language_code'];
            $meta_parts[] = '<span class="saw-badge-transparent saw-badge-info">' . esc_html($lang_label) . '</span>';
        }
        
        // Context badge
        if (!empty($item['context'])) {
            $context_labels = array(
                'terminal' => '🖥️ Terminal',
                'invitation' => '📧 Pozvánka',
                'admin' => '⚙️ Admin',
                'common' => '🌐 Společné',
            );
            $context_label = $context_labels[$item['context']] ?? $item['context'];
            $meta_parts[] = '<span class="saw-badge-transparent saw-badge-secondary">' . esc_html($context_label) . '</span>';
        }
        
        // Section badge
        if (!empty($item['section'])) {
            $meta_parts[] = '<span class="saw-badge-transparent">📁 ' . esc_html($item['section']) . '</span>';
        }
        
        return implode('', $meta_parts);
    }
    
    /**
     * Get table columns configuration
     */
    public function get_table_columns() {
        // Load translations
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'translations') 
            : [];
        
        $tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
        
        return array(
            'translation_key' => array(
                'label' => $tr('col_translation_key', 'Klíč'),
                'type' => 'text',
                'class' => 'saw-table-cell-bold',
                'sortable' => true,
            ),
            'language_code' => array(
                'label' => $tr('col_language', 'Jazyk'),
                'type' => 'badge',
                'sortable' => true,
                'map' => array(
                    'cs' => 'info',
                    'en' => 'secondary',
                    'de' => 'warning',
                    'sk' => 'success',
                ),
                'labels' => array(
                    'cs' => '🇨🇿 CS',
                    'en' => '🇬🇧 EN',
                    'de' => '🇩🇪 DE',
                    'sk' => '🇸🇰 SK',
                ),
            ),
            'context' => array(
                'label' => $tr('col_context', 'Kontext'),
                'type' => 'badge',
                'sortable' => true,
                'map' => array(
                    'terminal' => 'primary',
                    'invitation' => 'info',
                    'admin' => 'warning',
                    'common' => 'secondary',
                    'email' => 'info',
                ),
                'labels' => array(
                    'terminal' => '🖥️ Terminal',
                    'invitation' => '📧 Pozvánka',
                    'admin' => '⚙️ Admin',
                    'common' => '🌐 Společné',
                    'email' => '📧 Email',
                ),
            ),
            'section' => array(
                'label' => $tr('col_section', 'Sekce'),
                'type' => 'text',
                'sortable' => true,
            ),
            'translation_text' => array(
                'label' => $tr('col_translation_text', 'Text'),
                'type' => 'text',
                'class' => 'saw-table-cell-truncate',
                'maxlength' => 100,
            ),
            'description' => array(
                'label' => $tr('col_description', 'Popis'),
                'type' => 'text',
                'class' => 'saw-table-cell-truncate',
                'maxlength' => 50,
            ),
            'created_at' => array(
                'label' => $tr('col_created_at', 'Vytvořeno'),
                'type' => 'callback',
                'callback' => function($value) {
                    return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
                },
            ),
        );
    }
}

