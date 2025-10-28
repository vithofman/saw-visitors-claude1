<?php
/**
 * Customers Controller - FIXED with custom cell rendering
 * 
 * OPRAVY v4.6.2:
 * 1. Přidána metoda render_logo_cell() - renderování loga
 * 2. Přidána metoda render_color_cell() - renderování barvy
 * 3. Přidána metoda render_actions_cell() - akční tlačítka
 * 4. Správné napojení custom_cell_callback v konfiguraci
 * 5. Přidáno tlačítko "Přidat zákazníka"
 * 6. Debug výpisy pro sledování DB komunikace
 * 
 * @package SAW_Visitors
 * @version 4.6.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Customers_Controller {
    
    private $customer_model;
    private $data_table;
    
    public function __construct() {
        // Load dependencies
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-customer.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/data-tables/class-saw-data-table-column.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/data-tables/class-saw-data-table.php';
        
        $this->customer_model = new SAW_Customer();
        
        // 🔍 DEBUG: Log inicializace
        error_log('=== SAW Customers Controller Initialized ===');
    }
    
    /**
     * List customers - using Data Table system
     */
    public function index() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // 🔍 DEBUG: Test DB connection
        error_log('=== Testing Customer Model ===');
        $test_customers = $this->customer_model->get_all(array('limit' => 5));
        error_log('Customer count from DB: ' . count($test_customers));
        if (!empty($test_customers)) {
            error_log('First customer: ' . print_r($test_customers[0], true));
        }
        
        // Get message from query string
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $message_type = isset($_GET['message_type']) ? sanitize_text_field($_GET['message_type']) : 'success';
        
        // Create data table with CUSTOM CELL CALLBACKS
        $this->data_table = new SAW_Data_Table('customers', array(
            'model_class' => 'SAW_Customer',
            'columns' => array(
                'logo' => array(
                    'label' => 'Logo',
                    'sortable' => false,
                    'width' => '80px',
                    'type' => 'custom',
                    'custom_render' => array($this, 'render_logo_cell')  // ✅ OPRAVENO!
                ),
                'name' => array(
                    'label' => 'Název',
                    'sortable' => true
                ),
                'ico' => array(
                    'label' => 'IČO',
                    'sortable' => true,
                    'width' => '120px'
                ),
                'address' => array(
                    'label' => 'Adresa',
                    'sortable' => false
                ),
                'color' => array(
                    'label' => 'Barva',
                    'sortable' => false,
                    'width' => '100px',
                    'type' => 'custom',
                    'custom_render' => array($this, 'render_color_cell')  // ✅ OPRAVENO!
                )
            ),
            'ajax_action' => 'saw_search_customers',
            'per_page' => 20,
            'default_orderby' => 'name',
            'default_order' => 'ASC',
            'search_placeholder' => 'Hledat zákazníka...',
            'empty_title' => 'Žádní zákazníci',
            'empty_message' => 'Zatím nemáte žádné zákazníky. Klikněte na tlačítko výše pro přidání prvního zákazníka.',
            'empty_icon' => 'dashicons-groups',
            'actions' => array('edit', 'delete'),
            'row_actions_callback' => array($this, 'render_actions_cell'),  // ✅ OPRAVENO!
            'add_button' => array(  // ✅ PŘIDÁNO tlačítko!
                'label' => 'Přidat zákazníka',
                'url' => '/admin/settings/customers/new/',
                'icon' => 'dashicons-plus-alt'
            )
        ));
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Get current user and customer data
        $user = $this->get_current_user_data();
        $customer = $this->get_current_customer_data();
        
        // Start output buffering for content
        ob_start();
        
        // Show success/error message
        if ($message) {
            $alert_class = $message_type === 'error' ? 'saw-alert-danger' : 'saw-alert-success';
            ?>
            <div class="saw-alert <?php echo $alert_class; ?>">
                <?php echo esc_html($message); ?>
            </div>
            <?php
        }
        
        // Render the data table
        $this->data_table->render();
        
        $content = ob_get_clean();
        
        // Use layout system
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-layout.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-header.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-sidebar.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-footer.php';
        
        $layout = new SAW_App_Layout();
        $layout->render(
            $content,
            'Správa zákazníků',
            'settings-customers',
            $user,
            $customer
        );
    }
    
    /**
     * ✅ NOVÁ METODA: Render logo cell
     * 
     * @param mixed $value Logo URL
     * @param array $row Celý řádek dat
     * @return string HTML
     */
    public function render_logo_cell($value, $row) {
        $logo_url = !empty($row['logo_url_full']) ? $row['logo_url_full'] : '';
        
        if ($logo_url) {
            return '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($row['name']) . '" class="saw-customer-logo" style="max-width: 60px; max-height: 40px; border-radius: 4px;">';
        } else {
            return '<div class="saw-customer-logo-placeholder" style="width: 60px; height: 40px; background: #e5e7eb; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 12px; font-weight: 500;">' . esc_html(strtoupper(substr($row['name'], 0, 2))) . '</div>';
        }
    }
    
    /**
     * ✅ NOVÁ METODA: Render color cell
     * 
     * @param mixed $value Color hex
     * @param array $row Celý řádek dat
     * @return string HTML
     */
    public function render_color_cell($value, $row) {
        $color = !empty($row['color']) ? $row['color'] : '#2563eb';
        
        return '<div class="saw-color-preview" style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; background-color: ' . esc_attr($color) . '; border: 1px solid rgba(0,0,0,0.1);"></span>
            <code style="font-size: 12px; color: #6b7280;">' . esc_html($color) . '</code>
        </div>';
    }
    
    /**
     * ✅ NOVÁ METODA: Render actions cell
     * 
     * @param array $row Celý řádek dat
     * @return string HTML
     */
    public function render_actions_cell($row) {
        $edit_url = '/admin/settings/customers/' . $row['id'] . '/edit/';
        $delete_url = '/admin/settings/customers/' . $row['id'] . '/delete/';
        
        $html = '<div class="saw-table-actions">';
        
        // Edit button
        $html .= '<a href="' . esc_url($edit_url) . '" class="saw-btn saw-btn-sm saw-btn-secondary" title="Upravit">';
        $html .= '<span class="dashicons dashicons-edit"></span>';
        $html .= '</a>';
        
        // Delete button
        $html .= '<button type="button" class="saw-btn saw-btn-sm saw-btn-danger saw-delete-customer" data-id="' . esc_attr($row['id']) . '" data-name="' . esc_attr($row['name']) . '" title="Smazat">';
        $html .= '<span class="dashicons dashicons-trash"></span>';
        $html .= '</button>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get current user data
     */
    private function get_current_user_data() {
        $current_user = wp_get_current_user();
        
        return array(
            'id' => $current_user->ID,
            'name' => $current_user->display_name ?: $current_user->user_login,
            'email' => $current_user->user_email,
            'role' => 'admin',
        );
    }
    
    /**
     * Get current customer data
     */
    private function get_current_customer_data() {
        // For now, return demo data
        // TODO: Implement real customer switching logic
        return array(
            'id' => 1,
            'name' => 'Demo Zákazník A',
            'ico' => '12345678',
            'address' => 'Demo Adresa 123, Praha',
            'color' => '#2563eb',
        );
    }
    
    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        // Data table assets are enqueued by SAW_Data_Table->enqueue_assets()
        $this->data_table->enqueue_assets();
        
        // Customer-specific JS
        wp_enqueue_script(
            'saw-visitors-customers',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-customers.js',
            array('jquery', 'saw-visitors-data-tables'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'saw-visitors-customers',
            'sawCustomers',
            array(
                'confirmDelete' => 'Opravdu chcete smazat zákazníka "{name}"? Tato akce je nevratná!',
                'deleteSuccess' => 'Zákazník byl úspěšně smazán.',
                'deleteError' => 'Chyba při mazání zákazníka.',
            )
        );
    }
}