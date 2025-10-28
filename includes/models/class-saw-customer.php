<?php
/**
 * Customer Model - FIXED VERSION WITH DEBUG
 * 
 * Správa zákazníků - CRUD operace, logo upload, validace
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAW_Customer {
    
    /**
     * Databázová tabulka
     */
    private $table_name;
    
    /**
     * Upload složka pro loga
     */
    private $upload_dir;
    private $upload_url;
    
    /**
     * Povolené MIME typy pro logo
     */
    private $allowed_mime_types = array(
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    );
    
    /**
     * Maximální velikost souboru (5MB)
     */
    private $max_file_size = 5242880; // 5 * 1024 * 1024
    
    /**
     * Konstruktor
     */
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'saw_customers';
        
        // Upload složka: wp-content/uploads/saw-visitors/saw-customers/
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/saw-visitors/saw-customers/';
        $this->upload_url = $upload['baseurl'] . '/saw-visitors/saw-customers/';
        
        // Vytvoř složku pokud neexistuje
        $this->ensure_upload_directory();
    }
    
    /**
     * Zajistí existenci upload složky
     */
    private function ensure_upload_directory() {
        if ( ! file_exists( $this->upload_dir ) ) {
            wp_mkdir_p( $this->upload_dir );
            
            // Vytvoř .htaccess pro bezpečnost
            $htaccess_file = $this->upload_dir . '.htaccess';
            if ( ! file_exists( $htaccess_file ) ) {
                file_put_contents( $htaccess_file, "# Povolit jen obrázky\n<FilesMatch \"\\.(jpg|jpeg|png|gif|webp|svg)$\">\n    Order allow,deny\n    Allow from all\n</FilesMatch>\n<FilesMatch \"^(?!(.*\\.(jpg|jpeg|png|gif|webp|svg)$)).*$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>" );
            }
            
            // Vytvoř index.php pro bezpečnost
            $index_file = $this->upload_dir . 'index.php';
            if ( ! file_exists( $index_file ) ) {
                file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
            }
        }
    }
    
    /**
     * Získání všech zákazníků
     * 
     * @param array $args Filtry (search, orderby, order, limit, offset)
     * @return array
     */
    public function get_all( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'search'  => '',
            'orderby' => 'name',
            'order'   => 'ASC',
            'limit'   => 999,
            'offset'  => 0
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        // Sanitizace
        $orderby = in_array( $args['orderby'], array( 'id', 'name', 'ico', 'created_at' ) ) 
            ? $args['orderby'] 
            : 'name';
        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );
        
        // SQL - START
        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        
        // Search
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= $wpdb->prepare( " AND (name LIKE %s OR ico LIKE %s OR address LIKE %s)", $search, $search, $search );
        }
        
        $sql .= " ORDER BY {$orderby} {$order}";
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        // 🔍 DEBUG: Log SQL dotaz
        error_log( 'SAW Customer Model SQL: ' . $sql );
        
        // Spusť dotaz
        $customers = $wpdb->get_results( $sql, ARRAY_A );
        
        // 🔍 DEBUG: Log výsledek
        if ( $wpdb->last_error ) {
            error_log( 'SAW Customer Model ERROR: ' . $wpdb->last_error );
            return array(); // Vrať prázdné pole při chybě
        }
        
        error_log( 'SAW Customer Model: Found ' . count( (array) $customers ) . ' customers' );
        
        // Kontrola jestli je to pole
        if ( ! is_array( $customers ) ) {
            error_log( 'SAW Customer Model WARNING: get_results returned non-array: ' . gettype( $customers ) );
            $customers = array();
        }
        
        // Přidej URL loga
        foreach ( $customers as &$customer ) {
            $customer['logo_url_full'] = $this->get_logo_url( $customer['logo_url'] );
        }
        
        return $customers;
    }
    
    /**
     * Získání počtu zákazníků
     * 
     * @param string $search Hledaný text
     * @return int
     */
    public function count( $search = '' ) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
        
        if ( ! empty( $search ) ) {
            $search_term = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
            $sql .= $wpdb->prepare( " AND (name LIKE %s OR ico LIKE %s OR address LIKE %s)", $search_term, $search_term, $search_term );
        }
        
        // 🔍 DEBUG: Log SQL dotaz
        error_log( 'SAW Customer Model COUNT SQL: ' . $sql );
        
        $count = $wpdb->get_var( $sql );
        
        // 🔍 DEBUG: Log výsledek
        if ( $wpdb->last_error ) {
            error_log( 'SAW Customer Model COUNT ERROR: ' . $wpdb->last_error );
            return 0;
        }
        
        error_log( 'SAW Customer Model COUNT: ' . (int) $count );
        
        return (int) $count;
    }
    
    /**
     * Získání jednoho zákazníka podle ID
     * 
     * @param int $id ID zákazníka
     * @return array|null
     */
    public function get_by_id( $id ) {
        global $wpdb;
        
        $customer = $wpdb->get_row( 
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
            ARRAY_A
        );
        
        if ( $customer ) {
            $customer['logo_url_full'] = $this->get_logo_url( $customer['logo_url'] );
        }
        
        return $customer;
    }
    
    /**
     * Vytvoření nového zákazníka
     * 
     * @param array $data Data zákazníka
     * @return int|WP_Error ID nového zákazníka nebo chyba
     */
    public function create( $data ) {
        global $wpdb;
        
        // Validace
        $validation = $this->validate( $data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        
        // Příprava dat
        $insert_data = array(
            'name'          => sanitize_text_field( $data['name'] ),
            'ico'           => ! empty( $data['ico'] ) ? sanitize_text_field( $data['ico'] ) : null,
            'address'       => ! empty( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : null,
            'notes'         => ! empty( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
            'primary_color' => ! empty( $data['primary_color'] ) ? sanitize_hex_color( $data['primary_color'] ) : '#1e40af',
            'logo_url'      => null, // Nastavíme později po uploadu
            'created_at'    => current_time( 'mysql' ),
        );
        
        // Handle logo upload
        if ( isset( $_FILES['logo'] ) && ! empty( $_FILES['logo']['name'] ) ) {
            $logo_result = $this->upload_logo( $_FILES['logo'] );
            if ( is_wp_error( $logo_result ) ) {
                return $logo_result;
            }
            $insert_data['logo_url'] = $logo_result;
        }
        
        // Insert do DB
        $result = $wpdb->insert( $this->table_name, $insert_data );
        
        if ( $result === false ) {
            error_log( 'SAW Customer Model CREATE ERROR: ' . $wpdb->last_error );
            return new WP_Error( 'db_error', 'Chyba při vytváření zákazníka: ' . $wpdb->last_error );
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Aktualizace zákazníka
     * 
     * @param int   $id   ID zákazníka
     * @param array $data Data k aktualizaci
     * @return bool|WP_Error True při úspěchu, WP_Error při chybě
     */
    public function update( $id, $data ) {
        global $wpdb;
        
        // Validace
        $validation = $this->validate( $data, $id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        
        // Příprava dat
        $update_data = array();
        
        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
        }
        
        if ( isset( $data['ico'] ) ) {
            $update_data['ico'] = ! empty( $data['ico'] ) ? sanitize_text_field( $data['ico'] ) : null;
        }
        
        if ( isset( $data['address'] ) ) {
            $update_data['address'] = ! empty( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : null;
        }
        
        if ( isset( $data['notes'] ) ) {
            $update_data['notes'] = ! empty( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null;
        }
        
        if ( isset( $data['primary_color'] ) ) {
            $update_data['primary_color'] = sanitize_hex_color( $data['primary_color'] );
        }
        
        // Handle logo upload
        if ( isset( $_FILES['logo'] ) && ! empty( $_FILES['logo']['name'] ) ) {
            // Načti stávající logo pro případné smazání
            $old_customer = $this->get_by_id( $id );
            
            $logo_result = $this->upload_logo( $_FILES['logo'] );
            if ( is_wp_error( $logo_result ) ) {
                return $logo_result;
            }
            
            // Smaž staré logo
            if ( ! empty( $old_customer['logo_url'] ) ) {
                $this->delete_logo( $old_customer['logo_url'] );
            }
            
            $update_data['logo_url'] = $logo_result;
        }
        
        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_data', 'Žádná data k aktualizaci.' );
        }
        
        // Update v DB
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $id )
        );
        
        if ( $result === false ) {
            error_log( 'SAW Customer Model UPDATE ERROR: ' . $wpdb->last_error );
            return new WP_Error( 'db_error', 'Chyba při aktualizaci zákazníka: ' . $wpdb->last_error );
        }
        
        return true;
    }
    
    /**
     * Smazání zákazníka
     * 
     * @param int $id ID zákazníka
     * @return bool|WP_Error True při úspěchu, WP_Error při chybě
     */
    public function delete( $id ) {
        global $wpdb;
        
        // Načti zákazníka pro smazání loga
        $customer = $this->get_by_id( $id );
        if ( ! $customer ) {
            return new WP_Error( 'not_found', 'Zákazník nenalezen.' );
        }
        
        // Smaž logo
        if ( ! empty( $customer['logo_url'] ) ) {
            $this->delete_logo( $customer['logo_url'] );
        }
        
        // Smaž z DB
        $result = $wpdb->delete( $this->table_name, array( 'id' => $id ) );
        
        if ( $result === false ) {
            error_log( 'SAW Customer Model DELETE ERROR: ' . $wpdb->last_error );
            return new WP_Error( 'db_error', 'Chyba při mazání zákazníka: ' . $wpdb->last_error );
        }
        
        return true;
    }
    
    /**
     * Validace dat zákazníka
     * 
     * @param array $data Data k validaci
     * @param int   $id   ID zákazníka (pro update)
     * @return bool|WP_Error True při úspěchu, WP_Error při chybě
     */
    private function validate( $data, $id = null ) {
        // Název je povinný
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'name_required', 'Název zákazníka je povinný.' );
        }
        
        // IČO validace (pokud je vyplněno)
        if ( ! empty( $data['ico'] ) ) {
            $ico = sanitize_text_field( $data['ico'] );
            if ( ! preg_match( '/^\d{8}$/', $ico ) ) {
                return new WP_Error( 'ico_invalid', 'IČO musí být 8 číslic.' );
            }
        }
        
        // Barva validace
        if ( ! empty( $data['primary_color'] ) && ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $data['primary_color'] ) ) {
            return new WP_Error( 'color_invalid', 'Neplatný formát barvy.' );
        }
        
        return true;
    }
    
    /**
     * Upload loga
     * 
     * @param array $file $_FILES['logo']
     * @return string|WP_Error Relativní cesta k logu nebo chyba
     */
    private function upload_logo( $file ) {
        // Validace
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Chyba při nahrávání souboru.' );
        }
        
        if ( $file['size'] > $this->max_file_size ) {
            return new WP_Error( 'file_too_large', 'Soubor je příliš velký. Maximum je 5 MB.' );
        }
        
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );
        
        if ( ! in_array( $mime_type, $this->allowed_mime_types ) ) {
            return new WP_Error( 'invalid_file_type', 'Neplatný typ souboru. Povolené jsou pouze obrázky.' );
        }
        
        // Generuj unikátní název
        $extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $filename = 'logo-' . uniqid() . '.' . $extension;
        $filepath = $this->upload_dir . $filename;
        
        // Přesuň soubor
        if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
            return new WP_Error( 'move_failed', 'Nepodařilo se uložit soubor.' );
        }
        
        // Vrať relativní cestu
        return $filename;
    }
    
    /**
     * Smazání loga
     * 
     * @param string $logo_url Relativní cesta k logu
     * @return bool True při úspěchu
     */
    private function delete_logo( $logo_url ) {
        if ( empty( $logo_url ) ) {
            return false;
        }
        
        $filepath = $this->upload_dir . $logo_url;
        
        if ( file_exists( $filepath ) ) {
            return unlink( $filepath );
        }
        
        return false;
    }
    
    /**
     * Získání plné URL loga
     * 
     * @param string|null $logo_url Relativní cesta
     * @return string|null Plná URL nebo null
     */
    private function get_logo_url( $logo_url ) {
        if ( empty( $logo_url ) ) {
            return null;
        }
        
        return $this->upload_url . $logo_url;
    }
}