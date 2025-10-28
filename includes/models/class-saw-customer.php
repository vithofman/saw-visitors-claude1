<?php
/**
 * Customer Model
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
        
        // SQL
        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        
        // Search
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= $wpdb->prepare( " AND (name LIKE %s OR ico LIKE %s OR address LIKE %s)", $search, $search, $search );
        }
        
        $sql .= " ORDER BY {$orderby} {$order}";
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $customers = $wpdb->get_results( $sql, ARRAY_A );
        
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
        
        return (int) $wpdb->get_var( $sql );
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
            'created_at'    => current_time( 'mysql' )
        );
        
        // Logo upload
        if ( ! empty( $_FILES['logo']['name'] ) ) {
            $logo_result = $this->handle_logo_upload( $_FILES['logo'] );
            
            if ( is_wp_error( $logo_result ) ) {
                return $logo_result;
            }
            
            $insert_data['logo_url'] = $logo_result;
        }
        
        // Insert do DB
        $result = $wpdb->insert(
            $this->table_name,
            $insert_data,
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', 'Nepodařilo se vytvořit zákazníka.', array( 'error' => $wpdb->last_error ) );
        }
        
        $customer_id = $wpdb->insert_id;
        
        // Audit log
        if ( class_exists( 'SAW_Audit' ) ) {
            SAW_Audit::log( array(
                'action'      => 'customer_created',
                'customer_id' => $customer_id,
                'details'     => 'Vytvořen zákazník: ' . $insert_data['name'],
            ) );
        }
        
        return $customer_id;
    }
    
    /**
     * Aktualizace zákazníka
     * 
     * @param int $id ID zákazníka
     * @param array $data Data k aktualizaci
     * @return bool|WP_Error
     */
    public function update( $id, $data ) {
        global $wpdb;
        
        // Kontrola existence
        $existing = $this->get_by_id( $id );
        if ( ! $existing ) {
            return new WP_Error( 'not_found', 'Zákazník nenalezen.' );
        }
        
        // Validace
        $validation = $this->validate( $data, $id );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        
        // Příprava dat
        $update_data = array(
            'name'          => sanitize_text_field( $data['name'] ),
            'ico'           => ! empty( $data['ico'] ) ? sanitize_text_field( $data['ico'] ) : null,
            'address'       => ! empty( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : null,
            'notes'         => ! empty( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
            'primary_color' => ! empty( $data['primary_color'] ) ? sanitize_hex_color( $data['primary_color'] ) : '#1e40af',
            'updated_at'    => current_time( 'mysql' )
        );
        
        // Logo upload
        if ( ! empty( $_FILES['logo']['name'] ) ) {
            $logo_result = $this->handle_logo_upload( $_FILES['logo'] );
            
            if ( is_wp_error( $logo_result ) ) {
                return $logo_result;
            }
            
            // Smaž staré logo
            if ( ! empty( $existing['logo_url'] ) ) {
                $this->delete_logo( $existing['logo_url'] );
            }
            
            $update_data['logo_url'] = $logo_result;
        }
        
        // Update v DB
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', 'Nepodařilo se aktualizovat zákazníka.', array( 'error' => $wpdb->last_error ) );
        }
        
        // Audit log
        if ( class_exists( 'SAW_Audit' ) ) {
            SAW_Audit::log( array(
                'action'      => 'customer_updated',
                'customer_id' => $id,
                'details'     => 'Aktualizován zákazník: ' . $update_data['name'],
            ) );
        }
        
        return true;
    }
    
    /**
     * Smazání zákazníka
     * 
     * @param int $id ID zákazníka
     * @return bool|WP_Error
     */
    public function delete( $id ) {
        global $wpdb;
        
        // Kontrola existence
        $existing = $this->get_by_id( $id );
        if ( ! $existing ) {
            return new WP_Error( 'not_found', 'Zákazník nenalezen.' );
        }
        
        // Kontrola vazeb (nemůžeme smazat zákazníka s uživateli nebo daty)
        $user_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE customer_id = %d",
            $id
        ) );
        
        if ( $user_count > 0 ) {
            return new WP_Error( 'has_dependencies', 'Nelze smazat zákazníka, který má přiřazené uživatele nebo data.' );
        }
        
        // Smaž logo
        if ( ! empty( $existing['logo_url'] ) ) {
            $this->delete_logo( $existing['logo_url'] );
        }
        
        // Smaž z DB
        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%d' )
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', 'Nepodařilo se smazat zákazníka.' );
        }
        
        // Audit log
        if ( class_exists( 'SAW_Audit' ) ) {
            SAW_Audit::log( array(
                'action'      => 'customer_deleted',
                'customer_id' => null,
                'details'     => 'Smazán zákazník: ' . $existing['name'] . ' (ID: ' . $id . ')',
            ) );
        }
        
        return true;
    }
    
    /**
     * Validace dat zákazníka
     * 
     * @param array $data Data k validaci
     * @param int|null $id ID zákazníka (při update)
     * @return bool|WP_Error
     */
    private function validate( $data, $id = null ) {
        $errors = array();
        
        // Název je povinný
        if ( empty( $data['name'] ) || strlen( trim( $data['name'] ) ) < 2 ) {
            $errors[] = 'Název zákazníka musí mít alespoň 2 znaky.';
        }
        
        // IČO validace (pokud je vyplněno)
        if ( ! empty( $data['ico'] ) ) {
            $ico = preg_replace( '/[^0-9]/', '', $data['ico'] );
            if ( strlen( $ico ) < 6 || strlen( $ico ) > 12 ) {
                $errors[] = 'IČO musí mít 6-12 číslic.';
            }
        }
        
        // Primary color validace
        if ( ! empty( $data['primary_color'] ) ) {
            if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $data['primary_color'] ) ) {
                $errors[] = 'Primární barva musí být v HEX formátu (#RRGGBB).';
            }
        }
        
        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_error', implode( ' ', $errors ) );
        }
        
        return true;
    }
    
    /**
     * Zpracování uploadu loga
     * 
     * @param array $file $_FILES['logo']
     * @return string|WP_Error Relativní cesta k souboru nebo chyba
     */
    private function handle_logo_upload( $file ) {
        // Kontrola chyb uploadu
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Chyba při uploadu souboru.' );
        }
        
        // Kontrola velikosti
        if ( $file['size'] > $this->max_file_size ) {
            return new WP_Error( 'file_too_large', 'Soubor je příliš velký. Maximum je 5 MB.' );
        }
        
        // Kontrola MIME typu
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );
        
        if ( ! in_array( $mime_type, $this->allowed_mime_types ) ) {
            return new WP_Error( 'invalid_file_type', 'Neplatný typ souboru. Povolené typy: JPG, PNG, GIF, WebP, SVG.' );
        }
        
        // Sanitizace názvu souboru
        $filename = sanitize_file_name( $file['name'] );
        $filename = wp_unique_filename( $this->upload_dir, $filename );
        
        // Přesun souboru
        $destination = $this->upload_dir . $filename;
        
        if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
            return new WP_Error( 'move_error', 'Nepodařilo se uložit soubor.' );
        }
        
        // Nastav oprávnění
        chmod( $destination, 0644 );
        
        // Vrať relativní cestu (jen název souboru)
        return $filename;
    }
    
    /**
     * Smazání loga
     * 
     * @param string $logo_url Relativní cesta k logu
     * @return bool
     */
    private function delete_logo( $logo_url ) {
        if ( empty( $logo_url ) ) {
            return false;
        }
        
        $file_path = $this->upload_dir . basename( $logo_url );
        
        if ( file_exists( $file_path ) ) {
            return unlink( $file_path );
        }
        
        return false;
    }
    
    /**
     * Získání plné URL loga
     * 
     * @param string|null $logo_url Relativní cesta
     * @return string|null Plná URL nebo null
     */
    public function get_logo_url( $logo_url ) {
        if ( empty( $logo_url ) ) {
            return null;
        }
        
        // Pokud už je to plná URL, vrať ji
        if ( filter_var( $logo_url, FILTER_VALIDATE_URL ) ) {
            return $logo_url;
        }
        
        // Jinak vytvoř plnou URL
        return $this->upload_url . basename( $logo_url );
    }
    
    /**
     * Získání cesty k upload složce
     * 
     * @return string
     */
    public function get_upload_dir() {
        return $this->upload_dir;
    }
    
    /**
     * Získání URL k upload složce
     * 
     * @return string
     */
    public function get_upload_url() {
        return $this->upload_url;
    }
}
