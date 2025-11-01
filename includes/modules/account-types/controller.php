<?php
/**
 * Account Types Module Controller
 * 
 * Controller pro správu typů účtů. Dědí z Base Controller:
 * - index() - list view
 * - create() - formulář pro nový typ
 * - edit($id) - formulář pro editaci
 * 
 * Přidává jen:
 * - AJAX handlery (detail, search, delete)
 * - Before delete check (nelze smazat pokud používá zákazník)
 * 
 * @package SAW_Visitors
 * @version 1.0.1
 * @since   4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $color_picker;
    
    /**
     * Constructor
     * 
     * Načte config, inicializuje model, zaregistruje AJAX handlery.
     */
    public function __construct() {
        // Načti config
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        // Načti a inicializuj model
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Account_Types_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
        // === AJAX HANDLERY ===
        // Tyto handlery umožňují AJAX operace z frontendu
        
        // Modal detail (pro detail modal při kliknutí na řádek)
        add_action('wp_ajax_saw_get_account_types_detail', [$this, 'ajax_get_detail']);
        
        // Search (pro live search v tabulce)
        add_action('wp_ajax_saw_search_account_types', [$this, 'ajax_search']);
        
        // Delete (pro smazání přes AJAX)
        add_action('wp_ajax_saw_delete_account_types', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
        $this->color_picker->enqueue_assets();
    }
    
    /**
     * Format detail data pro modal
     * 
     * Připraví data pro zobrazení v modal detailu.
     * Přidá:
     * - Formátované datum vytvoření/aktualizace
     * - Features jako array (z JSON)
     * - Formátovanou cenu
     * - Badge label pro aktivní/neaktivní
     * 
     * @param array $item Surová data z DB
     * @return array Formátovaná data pro modal
     */
    protected function format_detail_data($item) {
        // === DATUM ===
        // Převeď created_at na čitelný formát (např. "31.10.2025 14:30")
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        // Převeď updated_at na čitelný formát
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        // === FEATURES ===
        // Převeď JSON string na array pro zobrazení
        $item['features_array'] = $this->model->get_features_as_array($item['features'] ?? null);
        
        // === CENA ===
        // Formátuj cenu s měnou (např. "1 990 Kč")
        if (isset($item['price'])) {
            $item['price_formatted'] = number_format($item['price'], 0, ',', ' ') . ' Kč';
        }
        
        // === AKTIVNÍ/NEAKTIVNÍ LABEL ===
        // Pro zobrazení jako badge
        $item['is_active_label'] = !empty($item['is_active']) ? 'Aktivní' : 'Neaktivní';
        $item['is_active_badge_class'] = !empty($item['is_active']) ? 'saw-badge-success' : 'saw-badge-secondary';
        
        return $item;
    }
    
    /**
     * Before delete hook
     * 
     * Kontroluje zda lze account type smazat.
     * Pokud ho používá nějaký zákazník, smazání NENÍ povoleno.
     * 
     * @param int $id ID account type k smazání
     * @return bool|WP_Error True pokud lze smazat, WP_Error pokud ne
     */
    protected function before_delete($id) {
        // Zkontroluj zda tento typ účtu nepoužívá nějaký zákazník
        if ($this->model->is_used_by_customers($id)) {
            return new WP_Error(
                'cannot_delete_in_use',
                'Tento typ účtu nelze smazat, protože ho používá jeden nebo více zákazníků. Nejdříve přiřaďte zákazníkům jiný typ účtu.'
            );
        }
        
        return true;
    }
    
    /**
     * After save hook
     * 
     * Po uložení smaž cache.
     * Protože account types se používají v customers selectu,
     * musíme smazat i customers cache.
     * 
     * @param int $id ID uloženého account type
     */
    protected function after_save($id) {
        // Smaž account types cache
        delete_transient('account_types_list');
        delete_transient('account_types_count');
        
        // Smaž i customers cache (protože customers zobrazují account type)
        delete_transient('customers_list');
        delete_transient('customers_count');
        
        // Debug log pokud je zapnutý debug režim
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log("SAW: Account Type saved - ID: {$id}");
        }
    }
}