<?php
/**
 * Customers Controller
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
            $customers = $this->customer_model->get_all( array(
                'search'  => $search,
                'orderby' => 'name',
                'order'   => 'ASC',
                'limit'   => $per_page,
                'offset'  => $offset
            ) );
            
            $total_customers = $this->customer_model->count( $search );
        }
        
        $total_pages = ceil( $total_customers / $per_page );
        
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
                echo $content;
            }
        } else {
            // Fallback pokud template neexistuje
            echo '<div class="saw-card">';
            echo '<h1>Správa zákazníků</h1>';
            echo '<p>Template soubor nenalezen: ' . esc_html($template_file) . '</p>';
            echo '<p>Celkem zákazníků: ' . count($customers) . '</p>';
            echo '</div>';
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
            
            // TODO: Zpracovat vytvoření zákazníka
            
            // Redirect po úspěchu
            wp_redirect( '/admin/settings/customers?message=created' );
            exit;
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
            echo '<div class="saw-card"><h1>Nový zákazník</h1><p>Template nenalezen.</p></div>';
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
            
            // TODO: Zpracovat update
            
            // Redirect
            wp_redirect( '/admin/settings/customers?message=updated' );
            exit;
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
            echo '<div class="saw-card"><h1>Upravit zákazníka</h1><p>Template nenalezen.</p></div>';
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
        
        // TODO: Delete customer
        
        wp_send_json_success( array( 'message' => 'Zákazník byl smazán.' ) );
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
        
        // TODO: Search customers
        
        wp_send_json_success( array( 'customers' => array() ) );
    }
}