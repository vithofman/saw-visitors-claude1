<?php
/**
 * Customers Controller - FIXED without SAW_App_Layout
 * 
 * @package SAW_Visitors
 * @version 4.6.1
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
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        // Get message from query string
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $message_type = isset($_GET['message_type']) ? sanitize_text_field($_GET['message_type']) : 'success';
        
        // Create data table
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
            'empty_icon' => 'dashicons-groups',
            'show_search' => true,
            'show_pagination' => true,
            'actions' => array('edit', 'delete'),
            'add_button' => array(
                'label' => 'Přidat zákazníka',
                'url' => home_url('/admin/settings/customers/new/')
            ),
            'custom_cell_callback' => array($this, 'render_customer_cell'),
            'row_actions_callback' => array($this, 'render_customer_actions')
        ));
        
        // Enqueue customers-specific JS
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
                'editUrl' => home_url('/admin/settings/customers/edit/')
            )
        );
        
        // ✅ OPRAVENO: Direct output instead of SAW_App_Layout
        $this->render_page($message, $message_type);
    }
    
    /**
     * ✅ NOVÁ METODA: Render celé stránky
     */
    private function render_page($message = '', $message_type = 'success') {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Správa zákazníků - SAW Visitors</title>
            <?php wp_head(); ?>
        </head>
        <body class="saw-app">
            
            <div class="saw-app-container">
                
                <!-- Page Header -->
                <div class="saw-page-header">
                    <div class="saw-page-header-content">
                        <h1 class="saw-page-title">Správa zákazníků</h1>
                        <p class="saw-page-subtitle">Zde můžete spravovat všechny zákazníky v systému</p>
                    </div>
                </div>
                
                <!-- Message Alert -->
                <?php if ($message): ?>
                    <div class="saw-alert saw-alert-<?php echo esc_attr($message_type); ?>">
                        <?php echo esc_html($message); ?>
                        <button type="button" class="saw-alert-close">&times;</button>
                    </div>
                <?php endif; ?>
                
                <!-- Data Table -->
                <?php echo $this->data_table->render(); ?>
                
            </div>
            
            <?php wp_footer(); ?>
            
            <script>
            // Alert close button
            document.querySelectorAll('.saw-alert-close').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            });
            </script>
            
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Custom cell rendering for customers
     */
    public function render_customer_cell($column_key, $item, $column) {
        switch ($column_key) {
            case 'logo':
                if (!empty($item->logo_url)) {
                    echo '<div class="saw-table-logo">';
                    echo '<img src="' . esc_url($item->logo_url) . '" alt="' . esc_attr($item->name) . '" style="max-width: 60px; height: auto; border-radius: 4px;">';
                    echo '</div>';
                } else {
                    echo '<div class="saw-table-logo saw-table-logo-empty">';
                    echo '<span class="dashicons dashicons-building" style="font-size: 40px; color: #9ca3af;"></span>';
                    echo '</div>';
                }
                break;
                
            case 'name':
                echo '<strong>' . esc_html($item->name) . '</strong>';
                break;
                
            case 'ico':
                echo esc_html($item->ico ?: '—');
                break;
                
            case 'address':
                echo esc_html($item->address ?: '—');
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
                if (isset($item->{$column_key})) {
                    echo esc_html($item->{$column_key});
                }
                break;
        }
    }
    
    /**
     * Custom row actions for customers
     */
    public function render_customer_actions($item) {
        $edit_url = add_query_arg('id', $item->id, home_url('/admin/settings/customers/edit/'));
        
        echo '<a href="' . esc_url($edit_url) . '" class="saw-btn saw-btn-sm saw-btn-secondary" title="Upravit">';
        echo '<span class="dashicons dashicons-edit"></span>';
        echo '</a>';
        
        echo '<button type="button" class="saw-btn saw-btn-sm saw-btn-danger saw-delete-customer" ';
        echo 'data-id="' . esc_attr($item->id) . '" ';
        echo 'data-name="' . esc_attr($item->name) . '" ';
        echo 'title="Smazat">';
        echo '<span class="dashicons dashicons-trash"></span>';
        echo '</button>';
    }
    
    /**
     * Create new customer
     */
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_create_customer')) {
                wp_die('Neplatný bezpečnostní token.', 'Chyba', array('response' => 403));
            }
            
            $data = array(
                'name' => sanitize_text_field($_POST['name']),
                'ico' => sanitize_text_field($_POST['ico']),
                'address' => sanitize_textarea_field($_POST['address']),
                'color' => sanitize_hex_color($_POST['color'])
            );
            
            // Handle logo upload
            if (!empty($_FILES['logo']['name'])) {
                $upload = $this->handle_logo_upload($_FILES['logo']);
                if ($upload['success']) {
                    $data['logo_url'] = $upload['url'];
                }
            }
            
            $customer_id = $this->customer_model->create($data);
            
            if ($customer_id) {
                $redirect = add_query_arg(
                    array(
                        'message' => 'Zákazník byl úspěšně vytvořen.',
                        'message_type' => 'success'
                    ),
                    home_url('/admin/settings/customers/')
                );
                wp_redirect($redirect);
                exit;
            }
        }
        
        // Render form
        echo '<h1>Create Customer Form</h1>';
        echo '<p>TODO: Implement form</p>';
        exit;
    }
    
    /**
     * Edit customer
     */
    public function edit() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $customer = $this->customer_model->get_by_id($customer_id);
        
        if (!$customer) {
            wp_die('Zákazník nebyl nalezen.', 'Chyba', array('response' => 404));
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_customer_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_customer_nonce'], 'saw_edit_customer_' . $customer_id)) {
                wp_die('Neplatný bezpečnostní token.', 'Chyba', array('response' => 403));
            }
            
            $data = array(
                'name' => sanitize_text_field($_POST['name']),
                'ico' => sanitize_text_field($_POST['ico']),
                'address' => sanitize_textarea_field($_POST['address']),
                'color' => sanitize_hex_color($_POST['color'])
            );
            
            // Handle logo upload
            if (!empty($_FILES['logo']['name'])) {
                $upload = $this->handle_logo_upload($_FILES['logo']);
                if ($upload['success']) {
                    $data['logo_url'] = $upload['url'];
                }
            }
            
            $updated = $this->customer_model->update($customer_id, $data);
            
            if ($updated) {
                $redirect = add_query_arg(
                    array(
                        'message' => 'Zákazník byl úspěšně aktualizován.',
                        'message_type' => 'success'
                    ),
                    home_url('/admin/settings/customers/')
                );
                wp_redirect($redirect);
                exit;
            }
        }
        
        // Render form
        echo '<h1>Edit Customer Form</h1>';
        echo '<p>Customer: ' . esc_html($customer->name) . '</p>';
        echo '<p>TODO: Implement form</p>';
        exit;
    }
    
    /**
     * Handle logo upload
     */
    private function handle_logo_upload($file) {
        $upload_dir = wp_upload_dir();
        $saw_upload_dir = $upload_dir['basedir'] . '/saw-visitors/logos/';
        
        if (!file_exists($saw_upload_dir)) {
            wp_mkdir_p($saw_upload_dir);
        }
        
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            return array('success' => false, 'message' => 'Nepovolený typ souboru.');
        }
        
        $filename = sanitize_file_name($file['name']);
        $filename = time() . '_' . $filename;
        $filepath = $saw_upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $url = $upload_dir['baseurl'] . '/saw-visitors/logos/' . $filename;
            return array('success' => true, 'url' => $url);
        }
        
        return array('success' => false, 'message' => 'Chyba při nahrávání souboru.');
    }
}