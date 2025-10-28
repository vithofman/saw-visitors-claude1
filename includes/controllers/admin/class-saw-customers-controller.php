<?php
/**
 * Customers Controller - FIXED VERSION
 * 
 * Správa zákazníků - pouze pro SuperAdmina
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAW_Customers_Controller {
    
    /**
     * Customer model
     */
    private $customer_model;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Načti model
        $model_file = SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-customer.php';
        if (file_exists($model_file)) {
            require_once $model_file;
            $this->customer_model = new SAW_Customer();
        } else {
            error_log('[SAW Customers Controller] Model soubor nenalezen: ' . $model_file);
        }
        
        // AJAX hooky
        add_action( 'wp_ajax_saw_delete_customer', array( $this, 'ajax_delete_customer' ) );
        add_action( 'wp_ajax_saw_search_customers', array( $this, 'ajax_search_customers' ) );
    }
    
    /**
     * Seznam zákazníků
     */
    public function index() {
        // Kontrola oprávnění - JEN SUPER ADMIN!
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Nemáte oprávnění k přístupu na tuto stránku.', 'Přístup zamítnut', array( 'response' => 403 ) );
        }
        
        // Parametry pro stránkování
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset = ( $page - 1 ) * $per_page;
        
        // Search
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        
        // Získej zákazníky (pokud máme model)
        $customers = array();
        $total_customers = 0;
        
        if ($this->customer_model) {
            try {
                $customers = $this->customer_model->get_all( array(
                    'search'  => $search,
                    'orderby' => 'name',
                    'order'   => 'ASC',
                    'limit'   => $per_page,
                    'offset'  => $offset
                ) );
                
                $total_customers = $this->customer_model->count( $search );
            } catch (Exception $e) {
                error_log('[SAW Customers Controller] Chyba při načítání zákazníků: ' . $e->getMessage());
                $customers = array();
                $total_customers = 0;
            }
        }
        
        $total_pages = $total_customers > 0 ? ceil( $total_customers / $per_page ) : 1;
        
        // Success/Error zprávy
        $message = '';
        $message_type = '';
        
        if ( isset( $_GET['message'] ) ) {
            switch ( $_GET['message'] ) {
                case 'created':
                    $message = 'Zákazník byl úspěšně vytvořen.';
                    $message_type = 'success';
                    break;
                case 'updated':
                    $message = 'Zákazník byl úspěšně aktualizován.';
                    $message_type = 'success';
                    break;
                case 'deleted':
                    $message = 'Zákazník byl úspěšně smazán.';
                    $message_type = 'success';
                    break;
                case 'error':
                    $message = isset( $_GET['error_msg'] ) ? urldecode( $_GET['error_msg'] ) : 'Došlo k chybě.';
                    $message_type = 'error';
                    break;
            }
        }
        
        // Render template
        $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-list.php';
        
        if (file_exists($template_file)) {
            // Nastav proměnné pro template
            $data = compact('customers', 'total_customers', 'total_pages', 'page', 'search', 'message', 'message_type');
            
            // Render obsah
            ob_start();
            extract($data);
            include $template_file;
            $content = ob_get_clean();
            
            // Použij layout
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $customer = $this->get_current_customer_data();
                $layout->render($content, 'Správa zákazníků', 'customers', $user, $customer);
            } else {
                // Fallback - render přímo s minimálním HTML wrapperem
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Správa zákazníků</title>';
                echo '<link rel="stylesheet" href="' . SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app.css">';
                echo '</head><body>';
                echo '<div class="saw-app-content">';
                echo $content;
                echo '</div>';
                echo '</body></html>';
            }
        } else {
            // Fallback pokud template neexistuje - s layoutem!
            $error_content = '<div class="saw-card">';
            $error_content .= '<div class="saw-card-header"><h1>❌ Chyba: Template nenalezen</h1></div>';
            $error_content .= '<div class="saw-card-body">';
            $error_content .= '<div class="saw-alert saw-alert-error">';
            $error_content .= '<p><strong>Template soubor nenalezen:</strong></p>';
            $error_content .= '<code>' . esc_html($template_file) . '</code>';
            $error_content .= '</div>';
            
            // Debug info
            $error_content .= '<h3>Debug informace:</h3>';
            $error_content .= '<ul>';
            $error_content .= '<li><strong>Plugin DIR:</strong> ' . esc_html(SAW_VISITORS_PLUGIN_DIR) . '</li>';
            $error_content .= '<li><strong>Template očekáván v:</strong> ' . esc_html($template_file) . '</li>';
            $error_content .= '<li><strong>Soubor existuje:</strong> ' . (file_exists($template_file) ? '✅ ANO' : '❌ NE') . '</li>';
            $error_content .= '<li><strong>Celkem zákazníků v DB:</strong> ' . count($customers) . '</li>';
            $error_content .= '<li><strong>Model načten:</strong> ' . ($this->customer_model ? '✅ ANO' : '❌ NE') . '</li>';
            $error_content .= '</ul>';
            
            // Přímé zobrazení zákazníků
            if (!empty($customers)) {
                $error_content .= '<h3>Zákazníci v databázi:</h3>';
                $error_content .= '<table class="saw-table"><thead><tr><th>ID</th><th>Název</th><th>IČO</th></tr></thead><tbody>';
                foreach ($customers as $cust) {
                    $error_content .= '<tr>';
                    $error_content .= '<td>' . esc_html($cust['id']) . '</td>';
                    $error_content .= '<td>' . esc_html($cust['name']) . '</td>';
                    $error_content .= '<td>' . esc_html($cust['ico'] ?? '—') . '</td>';
                    $error_content .= '</tr>';
                }
                $error_content .= '</tbody></table>';
            }
            
            $error_content .= '</div>';
            $error_content .= '</div>';
            
            // Použij layout i pro error
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $customer = $this->get_current_customer_data();
                $layout->render($error_content, 'Správa zákazníků - Chyba', 'customers', $user, $customer);
            } else {
                echo $error_content;
            }
        }
    }
    
    /**
     * Vytvoření nového zákazníka
     */
    public function create() {
        // Kontrola oprávnění
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Nemáte oprávnění.', 'Přístup zamítnut', array( 'response' => 403 ) );
        }
        
        // POST handler
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['_wpnonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'saw_create_customer' ) ) {
                wp_die( 'Bezpečnostní kontrola selhala.' );
            }
            
            if ($this->customer_model) {
                $result = $this->customer_model->create($_POST);
                
                if (is_wp_error($result)) {
                    wp_redirect( '/admin/settings/customers/?message=error&error_msg=' . urlencode($result->get_error_message()) );
                    exit;
                }
                
                wp_redirect( '/admin/settings/customers/?message=created' );
                exit;
            } else {
                wp_redirect( '/admin/settings/customers/?message=error&error_msg=' . urlencode('Model není k dispozici') );
                exit;
            }
        }
        
        // Render formulář
        $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
        
        if (file_exists($template_file)) {
            ob_start();
            $customer = null; // nový zákazník
            include $template_file;
            $content = ob_get_clean();
            
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $current_customer = $this->get_current_customer_data();
                $layout->render($content, 'Nový zákazník', 'customers', $user, $current_customer);
            } else {
                echo $content;
            }
        } else {
            $error_content = '<div class="saw-card"><h1>Nový zákazník</h1><p>Template nenalezen: ' . esc_html($template_file) . '</p></div>';
            
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $customer = $this->get_current_customer_data();
                $layout->render($error_content, 'Nový zákazník', 'customers', $user, $customer);
            } else {
                echo $error_content;
            }
        }
    }
    
    /**
     * Editace zákazníka
     */
    public function edit( $customer_id ) {
        // Kontrola oprávnění
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Nemáte oprávnění.', 'Přístup zamítnut', array( 'response' => 403 ) );
        }
        
        // Načti zákazníka
        $customer = null;
        if ($this->customer_model) {
            $customer = $this->customer_model->get_by_id( $customer_id );
        }
        
        if ( ! $customer ) {
            wp_die( 'Zákazník nenalezen.', 'Chyba', array( 'response' => 404 ) );
        }
        
        // POST handler
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['_wpnonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'saw_edit_customer_' . $customer_id ) ) {
                wp_die( 'Bezpečnostní kontrola selhala.' );
            }
            
            if ($this->customer_model) {
                $result = $this->customer_model->update($customer_id, $_POST);
                
                if (is_wp_error($result)) {
                    wp_redirect( '/admin/settings/customers/?message=error&error_msg=' . urlencode($result->get_error_message()) );
                    exit;
                }
                
                wp_redirect( '/admin/settings/customers/?message=updated' );
                exit;
            }
        }
        
        // Render formulář
        $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
        
        if (file_exists($template_file)) {
            ob_start();
            include $template_file;
            $content = ob_get_clean();
            
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $current_customer = $this->get_current_customer_data();
                $layout->render($content, 'Upravit zákazníka', 'customers', $user, $current_customer);
            } else {
                echo $content;
            }
        } else {
            $error_content = '<div class="saw-card"><h1>Upravit zákazníka</h1><p>Template nenalezen.</p></div>';
            
            if (class_exists('SAW_App_Layout')) {
                $layout = new SAW_App_Layout();
                $user = $this->get_current_user_data();
                $current_customer = $this->get_current_customer_data();
                $layout->render($error_content, 'Upravit zákazníka', 'customers', $user, $current_customer);
            } else {
                echo $error_content;
            }
        }
    }
    
    /**
     * Helper: Get current user data
     */
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => 'admin',
            );
        }
        
        return array(
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@demo.cz',
            'role' => 'admin',
        );
    }
    
    /**
     * Helper: Get current customer data
     */
    private function get_current_customer_data() {
        // TODO: Load from DB based on session
        return array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'address' => 'Praha 1, Hlavní 123',
        );
    }
    
    /**
     * AJAX: Delete customer
     */
    public function ajax_delete_customer() {
        check_ajax_referer( 'saw_ajax', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Nemáte oprávnění.' ) );
        }
        
        $customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
        
        if ( ! $customer_id ) {
            wp_send_json_error( array( 'message' => 'Neplatné ID zákazníka.' ) );
        }
        
        if ($this->customer_model) {
            $result = $this->customer_model->delete($customer_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }
            
            wp_send_json_success( array( 'message' => 'Zákazník byl smazán.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Model není k dispozici.' ) );
        }
    }
    
    /**
     * AJAX: Search customers
     */
    public function ajax_search_customers() {
        check_ajax_referer( 'saw_ajax', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Nemáte oprávnění.' ) );
        }
        
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        
        if ($this->customer_model) {
            $customers = $this->customer_model->get_all( array(
                'search' => $search,
                'limit' => 50,
            ) );
            
            wp_send_json_success( array( 'customers' => $customers ) );
        } else {
            wp_send_json_error( array( 'message' => 'Model není k dispozici.' ) );
        }
    }
}