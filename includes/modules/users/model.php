<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Users Module Model
 * 
 * Handles all database operations for users including:
 * - Proper customer filtering (admins see only their customer's users)
 * - Super_admin exclusion from regular admin view
 * - Department relationships
 * - Email uniqueness validation
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */
class SAW_Module_Users_Model extends SAW_Base_Model 
{
    /**
     * Constructor - nastaví tabulku a konfiguraci
     * 
     * @param array $config Configuration from config.php
     */
    public function __construct($config) {
        global $wpdb;
        
        $this->table = $wpdb->prefix . $config['table'];
        $this->config = $config;
        $this->cache_ttl = $config['cache']['ttl'] ?? 1800;
    }
    
    /**
     * Override get_all() to properly filter by customer
     * 
     * Tato metoda zajišťuje:
     * 1. Super_admin vidí všechny uživatele
     * 2. Admin vidí pouze uživatele svého customera (KROMĚ super_adminů)
     * 3. Respektuje filter_by_customer z configu
     * 
     * @param array $args Query arguments (filters, pagination, etc.)
     * @return array List of users
     */
    public function get_all($args = []) {
        global $wpdb;
        
        // Spustíme session pro přístup k customer_id
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Získáme customer_id z session
        $session_customer_id = $_SESSION['saw_current_customer_id'] ?? null;
        
        // Zjistíme, jestli je aktuální uživatel super_admin
        $current_user = wp_get_current_user();
        $is_super_admin = in_array('administrator', $current_user->roles, true);
        
        // KRITICKÁ ČÁST: Filtrování podle customer_id
        // ================================================
        
        // Pokud je zapnuté filtrování podle customera v configu
        if (!empty($this->config['filter_by_customer'])) {
            
            // Super_admin vidí všechny uživatele (bez omezení)
            if ($is_super_admin) {
                // Neaplikujeme žádný filtr na customer_id
                // Super admin uvidí všechny včetně ostatních super_adminů
            } 
            // Admin (ne super_admin) vidí pouze své uživatele
            else {
                if ($session_customer_id) {
                    // Admin vidí pouze uživatele se STEJNÝM customer_id
                    $args['customer_id'] = (int) $session_customer_id;
                    
                    // DŮLEŽITÉ: Admin NESMÍ vidět super_adminy!
                    // Super_admini mají customer_id = NULL, takže je automaticky nevidí
                    // díky filtru customer_id = X
                    
                    // Ale pro jistotu ještě explicitně vyloučíme role super_admin
                    $args['_raw_where'][] = "role != 'super_admin'";
                } else {
                    // Pokud admin nemá nastaveného customera, nevidí nikoho
                    $args['customer_id'] = -1;
                }
            }
        }
        
        // Zavoláme parent metodu, která zpracuje všechny filtry
        return parent::get_all($args);
    }
    
    /**
     * Validace dat před uložením
     * 
     * Kontroluje:
     * - Povinná pole (email, first_name, last_name, role)
     * - Validitu emailu
     * - Unikátnost emailu
     * - Formát PIN kódu (pro terminály)
     * 
     * @param array $data Data to validate
     * @param int $id User ID (0 for new user)
     * @return true|WP_Error True if valid, WP_Error if validation fails
     */
    public function validate($data, $id = 0) {
        $errors = [];
        
        // Kontrola emailu
        if (empty($data['email'])) {
            $errors['email'] = 'Email je povinný';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Neplatný formát emailu';
        } elseif ($this->email_exists($data['email'], $id)) {
            $errors['email'] = 'Uživatel s tímto emailem již existuje';
        }
        
        // Kontrola jména
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'Jméno je povinné';
        }
        
        // Kontrola příjmení
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Příjmení je povinné';
        }
        
        // Kontrola role
        if (empty($data['role'])) {
            $errors['role'] = 'Role je povinná';
        }
        
        // Kontrola PIN (pouze pro terminály)
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                $errors['pin'] = 'PIN musí být 4 čísla';
            }
        }
        
        return empty($errors) ? true : new WP_Error('validation_error', 'Validace selhala', $errors);
    }
    
    /**
     * Kontrola existence emailu v databázi
     * 
     * @param string $email Email to check
     * @param int $exclude_id User ID to exclude from check (for updates)
     * @return bool True if email exists
     */
    private function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        
        if (empty($email)) {
            return false;
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE email = %s AND id != %d",
            $email,
            $exclude_id
        );
        
        return (bool) $wpdb->get_var($query);
    }
    
    /**
     * Získání uživatele podle ID včetně jeho oddělení
     * 
     * Pro managery navíc načte seznam přiřazených oddělení
     * 
     * @param int $id User ID
     * @return array|null User data or null if not found
     */
    public function get_by_id($id) {
        // Načteme základní data uživatele
        $user = parent::get_by_id($id);
        
        if (!$user) {
            return null;
        }
        
        // Pro managery načteme jejich oddělení
        if ($user['role'] === 'manager') {
            global $wpdb;
            $departments = $wpdb->get_results($wpdb->prepare(
                "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                $id
            ), ARRAY_A);
            
            // Přidáme pole department_ids do uživatelských dat
            $user['department_ids'] = array_column($departments, 'department_id');
        }
        
        return $user;
    }
    
    /**
     * Kontrola, jestli je uživatel použit v jiných tabulkách
     * 
     * Zabraňuje smazání uživatele, který má záznamy v systému
     * 
     * @param int $id User ID
     * @return bool True if user is used somewhere in the system
     */
    public function is_used_in_system($id) {
        global $wpdb;
        
        // Tabulky, kde kontrolujeme použití uživatele
        $tables_to_check = [
            'saw_visits' => 'created_by',
            'saw_invitations' => 'created_by',
        ];
        
        foreach ($tables_to_check as $table => $column) {
            $full_table = $wpdb->prefix . $table;
            
            // Zkontrolujeme, jestli tabulka existuje
            if ($wpdb->get_var("SHOW TABLES LIKE '{$full_table}'") !== $full_table) {
                continue;
            }
            
            // Spočítáme záznamy
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$full_table} WHERE {$column} = %d",
                $id
            ));
            
            if ($count > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Získání uživatelů podle zákazníka
     * 
     * Helper metoda pro rychlé načtení všech uživatelů daného zákazníka
     * 
     * @param int $customer_id Customer ID
     * @param bool $active_only Return only active users
     * @return array List of users
     */
    public function get_by_customer($customer_id, $active_only = false) {
        $filters = [
            'customer_id' => $customer_id,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        if ($active_only) {
            $filters['is_active'] = 1;
        }
        
        return $this->get_all($filters);
    }
}