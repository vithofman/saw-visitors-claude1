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
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-customer.php';
        $this->customer_model = new SAW_Customer();
        
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
        
        // Získej zákazníky
        $customers = $this->customer_model->get_all( array(
            'search'  => $search,
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => $per_page,
            'offset'  => $offset
        ) );
        
        // Celkový počet
        $total_customers = $this->customer_model->count( $search );
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
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-list.php';
    }
    
    /**
     * Formulář pro vytvoření/editaci zákazníka
     */
    public function form() {
        // Kontrola oprávnění
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Nemáte oprávnění k přístupu na tuto stránku.', 'Přístup zamítnut', array( 'response' => 403 ) );
        }
        
        $customer = null;
        $is_edit = false;
        
        // Editace existujícího zákazníka
        if ( isset( $_GET['id'] ) ) {
            $customer_id = intval( $_GET['id'] );
            $customer = $this->customer_model->get_by_id( $customer_id );
            
            if ( ! $customer ) {
                wp_die( 'Zákazník nenalezen.', 'Chyba', array( 'response' => 404 ) );
            }
            
            $is_edit = true;
        }
        
        // Zpracování formuláře
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $result = $this->handle_form_submission( $customer );
            
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                // Redirect s success message
                $redirect_url = add_query_arg(
                    array(
                        'message' => $is_edit ? 'updated' : 'created'
                    ),
                    home_url( '/admin/settings/customers/' )
                );
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
        
        // Render template
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/admin/customers-form.php';
    }
    
    /**
     * Zpracování formuláře (create/update)
     * 
     * @param array|null $existing_customer Existující zákazník (při update)
     * @return int|bool|WP_Error ID zákazníka nebo true při update, nebo WP_Error
     */
    private function handle_form_submission( $existing_customer = null ) {
        // Nonce check
        if ( ! isset( $_POST['saw_customer_nonce'] ) || ! wp_verify_nonce( $_POST['saw_customer_nonce'], 'saw_customer_form' ) ) {
            return new WP_Error( 'nonce_error', 'Bezpečnostní kontrola selhala.' );
        }
        
        // Sestavení dat
        $data = array(
            'name'          => $_POST['name'] ?? '',
            'ico'           => $_POST['ico'] ?? '',
            'address'       => $_POST['address'] ?? '',
            'notes'         => $_POST['notes'] ?? '',
            'primary_color' => $_POST['primary_color'] ?? '#1e40af'
        );
        
        // Create nebo Update
        if ( $existing_customer ) {
            // UPDATE
            $result = $this->customer_model->update( $existing_customer['id'], $data );
        } else {
            // CREATE
            $result = $this->customer_model->create( $data );
        }
        
        return $result;
    }
    
    /**
     * Smazání zákazníka
     */
    public function delete() {
        // Kontrola oprávnění
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Nemáte oprávnění k této akci.', 'Přístup zamítnut', array( 'response' => 403 ) );
        }
        
        // Nonce check
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'saw_delete_customer_' . intval( $_GET['id'] ) ) ) {
            wp_die( 'Bezpečnostní kontrola selhala.' );
        }
        
        $customer_id = intval( $_GET['id'] );
        
        $result = $this->customer_model->delete( $customer_id );
        
        if ( is_wp_error( $result ) ) {
            // Redirect s error
            $redirect_url = add_query_arg(
                array(
                    'message'   => 'error',
                    'error_msg' => urlencode( $result->get_error_message() )
                ),
                home_url( '/admin/settings/customers/' )
            );
        } else {
            // Redirect s success
            $redirect_url = add_query_arg(
                array( 'message' => 'deleted' ),
                home_url( '/admin/settings/customers/' )
            );
        }
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    /**
     * AJAX: Smazání zákazníka
     */
    public function ajax_delete_customer() {
        // Kontrola oprávnění
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Nemáte oprávnění.' ), 403 );
        }
        
        // Nonce check
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'saw_delete_customer' ) ) {
            wp_send_json_error( array( 'message' => 'Bezpečnostní kontrola selhala.' ), 403 );
        }
        
        $customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
        
        if ( ! $customer_id ) {
            wp_send_json_error( array( 'message' => 'Neplatné ID zákazníka.' ), 400 );
        }
        
        $result = $this->customer_model->delete( $customer_id );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }
        
        wp_send_json_success( array( 'message' => 'Zákazník byl úspěšně smazán.' ) );
    }
    
    /**
     * AJAX: Vyhledávání zákazníků
     */
    public function ajax_search_customers() {
        // Kontrola oprávnění
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Nemáte oprávnění.' ), 403 );
        }
        
        // Nonce check
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'saw_search_customers' ) ) {
            wp_send_json_error( array( 'message' => 'Bezpečnostní kontrola selhala.' ), 403 );
        }
        
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        
        $customers = $this->customer_model->get_all( array(
            'search'  => $search,
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => 50
        ) );
        
        wp_send_json_success( array( 'customers' => $customers ) );
    }
}
