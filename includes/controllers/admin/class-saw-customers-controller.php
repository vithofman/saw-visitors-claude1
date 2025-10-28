<?php
/**
 * Customers Controller - FIXED VERSION with proper Layout
 * 
 * ✅ OPRAVENO:
 * - Používá SAW_App_Layout pro kompletní layout (header, sidebar, footer)
 * - Odstraněn render_page() který vytvářel vlastní HTML dokument
 * - Správně načítá user a customer data
 * - Zachovává funkční Data Tables systém
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
    }
    
    /**
     * List customers - using Data Table system
     */
    public function index() {
        // ✅ Permission check
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // ✅ Get message from query string
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $message_type = isset($_GET['message_type']) ? sanitize_text_field($_GET['message_type']) : 'success';
        
        // ✅ Enqueue assets PŘED render layoutu!
        $this->enqueue_assets();
        
        // ✅ Create data table
        $this->data_table = new SAW_Data_Table('customers', array(
            'model_class' => 'SAW_Customer',
            'columns' => array(
                'logo' => array(
                    'label' => 'Logo',
                    'sortable' => false,
                    'width' => '80px',
                    'type' => 'custom'
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
                    'type' => 'custom'
                )
            ),
            'ajax_action' => 'saw_search_customers',
            'per_page' => 20,
            'default_orderby' => 'name',
            'default_order' => 'ASC',
            'search_placeholder' => 'Hledat zákazníka...',
            'empty_title' => 'Žádní zákazníci',
            'empty_message' => 'Zatím nemáte žádné zákazníky. Klikněte na tlačítko výše pro přidání prvního zákazníka.',
            'add_new_button' => array(
                'text' => 'Přidat zákazníka',
                'url' => home_url('/admin/settings/customers/new/')
            ),
            'cell_renderer' => array($this, 'render_customer_cell')
        ));
        
        // ✅ Build page content
        ob_start();
        ?>
        <div class="saw-page-wrapper">
            <!-- Page Header -->
            <div class="saw-page-header">
                <h1 class="saw-page-title">Správa zákazníků</h1>
                <p class="saw-page-subtitle">Zde můžete spravovat všechny zákazníky v systému</p>
            </div>
            
            <!-- Message Alert -->
            <?php if ($message): ?>
                <div class="saw-alert saw-alert-<?php echo esc_attr($message_type); ?>">
                    <?php echo esc_html($message); ?>
                    <button type="button" class="saw-alert-close">&times;</button>
                </div>
                
                <script>
                // Alert auto-hide + close button
                jQuery(document).ready(function($) {
                    $('.saw-alert-close').on('click', function() {
                        $(this).parent().fadeOut();
                    });
                    
                    setTimeout(function() {
                        $('.saw-alert').fadeOut();
                    }, 5000);
                });
                </script>
            <?php endif; ?>
            
            <!-- Data Table -->
            <?php echo $this->data_table->render(); ?>
            
        </div>
        <?php
        $content = ob_get_clean();
        
        // ✅ Get current user and customer data
        $current_user = $this->get_current_user_data();
        $current_customer = $this->get_current_customer_data();
        
        // ✅ POUŽÍT SAW_App_Layout pro celý layout!
        $layout = new SAW_App_Layout();
        $layout->render(
            $content,                           // HTML obsah stránky
            'Správa zákazníků',                 // Page title
            'settings-customers',               // Active menu ID
            $current_user,                      // User data
            $current_customer                   // Customer data
        );
        
        exit;
    }
    
    /**
     * ✅ Get current user data
     */
    private function get_current_user_data() {
        $wp_user = wp_get_current_user();
        
        if (!$wp_user || $wp_user->ID === 0) {
            return array(
                'id' => 0,
                'name' => 'Guest',
                'email' => '',
                'role' => 'guest'
            );
        }
        
        return array(
            'id' => $wp_user->ID,
            'name' => $wp_user->display_name,
            'email' => $wp_user->user_email,
            'role' => $this->get_saw_role($wp_user)
        );
    }
    
    /**
     * ✅ Get SAW role from WP user
     */
    private function get_saw_role($wp_user) {
        if (in_array('administrator', (array) $wp_user->roles)) {
            return 'super_admin';
        }
        if (in_array('editor', (array) $wp_user->roles)) {
            return 'admin';
        }
        if (in_array('author', (array) $wp_user->roles)) {
            return 'manager';
        }
        
        return 'guest';
    }
    
    /**
     * ✅ Get current customer data
     */
    private function get_current_customer_data() {
        // Pokud je Super Admin, může mít vybraného zákazníka ze session
        if (isset($_SESSION['saw_selected_customer_id'])) {
            $customer_id = intval($_SESSION['saw_selected_customer_id']);
            $customer = $this->customer_model->find($customer_id);
            
            if ($customer) {
                return array(
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'ico' => $customer->ico,
                    'address' => $customer->address,
                    'logo_url' => $customer->logo_url,
                    'color' => $customer->color
                );
            }
        }
        
        // Fallback: první zákazník z databáze
        global $wpdb;
        $table = $wpdb->prefix . 'saw_customers';
        $first_customer = $wpdb->get_row("SELECT * FROM {$table} LIMIT 1");
        
        if ($first_customer) {
            return array(
                'id' => $first_customer->id,
                'name' => $first_customer->name,
                'ico' => $first_customer->ico,
                'address' => $first_customer->address,
                'logo_url' => $first_customer->logo_url ?: '',
                'color' => $first_customer->color ?: '#2563eb'
            );
        }
        
        // Ultimate fallback: demo data
        return array(
            'id' => 0,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'address' => 'Praha 1, Hlavní 123',
            'logo_url' => '',
            'color' => '#2563eb'
        );
    }
    
    /**
     * ✅ Enqueue assets
     */
    private function enqueue_assets() {
        // Data Tables CSS
        wp_enqueue_style(
            'saw-visitors-data-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-tables.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        // Data Tables JS
        wp_enqueue_script(
            'saw-visitors-data-tables',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-data-tables.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Customers specific JS
        wp_enqueue_script(
            'saw-visitors-customers',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-customers.js',
            array('jquery', 'saw-visitors-data-tables'),
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize script with config
        wp_localize_script(
            'saw-visitors-customers',
            'sawCustomersConfig',
            array(
                'editUrl' => home_url('/admin/settings/customers/edit/'),
                'deleteUrl' => home_url('/admin/settings/customers/delete/'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_customers_nonce')
            )
        );
    }
    
    /**
     * ✅ Custom cell rendering for customers
     */
    public function render_customer_cell($column_key, $item, $column) {
        switch ($column_key) {
            case 'logo':
                if (!empty($item->logo_url)) {
                    echo '<div class="saw-table-logo">';
                    echo '<img src="' . esc_url($item->logo_url) . '" alt="' . esc_attr($item->name) . '" style="max-width: 60px; height: auto; border-radius: 4px;">';
                    echo '</div>';
                } else {
                    echo '<span class="dashicons dashicons-building" style="font-size: 40px; color: #9ca3af;"></span>';
                }
                break;
                
            case 'color':
                if (!empty($item->color)) {
                    echo '<div style="display: flex; align-items: center; gap: 8px;">';
                    echo '<span style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; background: ' . esc_attr($item->color) . '; border: 1px solid rgba(0,0,0,0.1);"></span>';
                    echo '<code style="font-size: 12px;">' . esc_html($item->color) . '</code>';
                    echo '</div>';
                } else {
                    echo '—';
                }
                break;
                
            default:
                echo esc_html($item->$column_key);
                break;
        }
    }
    
    /**
     * Create new customer (form + handler)
     */
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_create_customer'])) {
            $this->handle_create();
            return;
        }
        
        // Show create form
        $this->render_form_page('create');
    }
    
    /**
     * Edit customer (form + handler)
     */
    public function edit($customer_id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_edit_customer'])) {
            $this->handle_edit($customer_id);
            return;
        }
        
        // Show edit form
        $this->render_form_page('edit', $customer_id);
    }
    
    /**
     * Delete customer
     */
    public function delete($customer_id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_customer_' . $customer_id)) {
            wp_die('Neplatný bezpečnostní token.', 'Chyba', array('response' => 403));
        }
        
        // Delete customer
        $result = $this->customer_model->delete($customer_id);
        
        if ($result) {
            $redirect_url = add_query_arg(array(
                'message' => 'Zákazník byl úspěšně smazán.',
                'message_type' => 'success'
            ), home_url('/admin/settings/customers/'));
        } else {
            $redirect_url = add_query_arg(array(
                'message' => 'Chyba při mazání zákazníka.',
                'message_type' => 'error'
            ), home_url('/admin/settings/customers/'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * ✅ Render form page (create/edit)
     */
    private function render_form_page($action, $customer_id = null) {
        $customer = null;
        $page_title = 'Přidat zákazníka';
        
        if ($action === 'edit' && $customer_id) {
            $customer = $this->customer_model->find($customer_id);
            if (!$customer) {
                wp_die('Zákazník nebyl nalezen.', 'Chyba 404', array('response' => 404));
            }
            $page_title = 'Upravit zákazníka';
        }
        
        // Build form HTML
        ob_start();
        ?>
        <div class="saw-page-wrapper">
            <div class="saw-page-header">
                <h1 class="saw-page-title"><?php echo esc_html($page_title); ?></h1>
                <p class="saw-page-subtitle">
                    <a href="<?php echo esc_url(home_url('/admin/settings/customers/')); ?>">
                        ← Zpět na seznam
                    </a>
                </p>
            </div>
            
            <div class="saw-card">
                <form method="POST" enctype="multipart/form-data">
                    <div class="saw-card-body">
                        
                        <!-- Název -->
                        <div class="saw-form-group">
                            <label for="name">Název firmy *</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?php echo $customer ? esc_attr($customer->name) : ''; ?>" 
                                   required>
                        </div>
                        
                        <!-- IČO -->
                        <div class="saw-form-group">
                            <label for="ico">IČO *</label>
                            <input type="text" 
                                   id="ico" 
                                   name="ico" 
                                   value="<?php echo $customer ? esc_attr($customer->ico) : ''; ?>" 
                                   maxlength="8" 
                                   required>
                        </div>
                        
                        <!-- Adresa -->
                        <div class="saw-form-group">
                            <label for="address">Adresa</label>
                            <input type="text" 
                                   id="address" 
                                   name="address" 
                                   value="<?php echo $customer ? esc_attr($customer->address) : ''; ?>">
                        </div>
                        
                        <!-- Logo -->
                        <div class="saw-form-group">
                            <label for="logo">Logo</label>
                            <?php if ($customer && $customer->logo_url): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo esc_url($customer->logo_url); ?>" 
                                         alt="Current logo" 
                                         style="max-width: 200px; height: auto;">
                                </div>
                            <?php endif; ?>
                            <input type="file" 
                                   id="logo" 
                                   name="logo" 
                                   accept="image/*">
                            <small>Podporované formáty: JPG, PNG, GIF. Max 2MB.</small>
                        </div>
                        
                        <!-- Primary Color -->
                        <div class="saw-form-group">
                            <label for="color">Primární barva</label>
                            <input type="color" 
                                   id="color" 
                                   name="color" 
                                   value="<?php echo $customer && $customer->color ? esc_attr($customer->color) : '#2563eb'; ?>">
                        </div>
                        
                    </div>
                    
                    <div class="saw-card-footer">
                        <button type="submit" 
                                name="<?php echo $action === 'create' ? 'saw_create_customer' : 'saw_edit_customer'; ?>" 
                                class="saw-btn saw-btn-primary">
                            <?php echo $action === 'create' ? 'Vytvořit zákazníka' : 'Uložit změny'; ?>
                        </button>
                        <a href="<?php echo esc_url(home_url('/admin/settings/customers/')); ?>" 
                           class="saw-btn saw-btn-secondary">
                            Zrušit
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        // ✅ Use layout
        $current_user = $this->get_current_user_data();
        $current_customer = $this->get_current_customer_data();
        
        $layout = new SAW_App_Layout();
        $layout->render(
            $content,
            $page_title,
            'settings-customers',
            $current_user,
            $current_customer
        );
        
        exit;
    }
    
    /**
     * Handle create form submission
     */
    private function handle_create() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'saw_create_customer')) {
            wp_die('Neplatný bezpečnostní token.', 'Chyba', array('response' => 403));
        }
        
        // Validate + sanitize data
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'ico' => sanitize_text_field($_POST['ico']),
            'address' => sanitize_text_field($_POST['address']),
            'color' => sanitize_hex_color($_POST['color'])
        );
        
        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->handle_logo_upload($_FILES['logo']);
            if ($upload['success']) {
                $data['logo_url'] = $upload['url'];
            }
        }
        
        // Create customer
        $result = $this->customer_model->create($data);
        
        if ($result) {
            $redirect_url = add_query_arg(array(
                'message' => 'Zákazník byl úspěšně vytvořen.',
                'message_type' => 'success'
            ), home_url('/admin/settings/customers/'));
        } else {
            $redirect_url = add_query_arg(array(
                'message' => 'Chyba při vytváření zákazníka.',
                'message_type' => 'error'
            ), home_url('/admin/settings/customers/'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle edit form submission
     */
    private function handle_edit($customer_id) {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'saw_edit_customer_' . $customer_id)) {
            wp_die('Neplatný bezpečnostní token.', 'Chyba', array('response' => 403));
        }
        
        // Validate + sanitize data
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'ico' => sanitize_text_field($_POST['ico']),
            'address' => sanitize_text_field($_POST['address']),
            'color' => sanitize_hex_color($_POST['color'])
        );
        
        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->handle_logo_upload($_FILES['logo']);
            if ($upload['success']) {
                $data['logo_url'] = $upload['url'];
            }
        }
        
        // Update customer
        $result = $this->customer_model->update($customer_id, $data);
        
        if ($result) {
            $redirect_url = add_query_arg(array(
                'message' => 'Zákazník byl úspěšně upraven.',
                'message_type' => 'success'
            ), home_url('/admin/settings/customers/'));
        } else {
            $redirect_url = add_query_arg(array(
                'message' => 'Chyba při úpravě zákazníka.',
                'message_type' => 'error'
            ), home_url('/admin/settings/customers/'));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle logo upload
     */
    private function handle_logo_upload($file) {
        $upload_dir = wp_upload_dir();
        $saw_upload_dir = $upload_dir['basedir'] . '/saw-customers';
        
        // Create directory if not exists
        if (!file_exists($saw_upload_dir)) {
            wp_mkdir_p($saw_upload_dir);
        }
        
        // Generate unique filename
        $filename = time() . '-' . sanitize_file_name($file['name']);
        $filepath = $saw_upload_dir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $file_url = $upload_dir['baseurl'] . '/saw-customers/' . $filename;
            return array('success' => true, 'url' => $file_url);
        }
        
        return array('success' => false);
    }
}