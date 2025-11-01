<?php
/**
 * AJAX Handlers Trait
 * 
 * Trait obsahuje základní AJAX handlery pro moduly:
 * - ajax_get_detail() - Načte detail záznamu pro modal
 * - ajax_search() - Vyhledávání v tabulce
 * - ajax_delete() - Smazání záznamu
 * 
 * DŮLEŽITÉ: Tento trait používá UNIVERZÁLNÍ nonce 'saw_ajax_nonce' místo
 * module-specific nonce, aby fungoval pro všechny moduly (customers, account-types, atd.)
 * 
 * @package SAW_Visitors
 * @version 1.0.2
 * @since   4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SAW_AJAX_Handlers 
{
    /**
     * AJAX: Search items
     * 
     * Vyhledá záznamy podle query stringu.
     * Používá model->search() metodu.
     */
    public function ajax_search() {
        // ✅ UNIVERZÁLNÍ NONCE - funguje pro všechny moduly
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        // Získej search query
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_error(['message' => 'Prázdný vyhledávací dotaz']);
        }
        
        // Proveď search přes model
        $results = $this->model->search($search);
        
        if (is_wp_error($results)) {
            wp_send_json_error(['message' => $results->get_error_message()]);
        }
        
        // Formátuj results
        $formatted_results = array_map(function($item) {
            return $this->format_search_result($item);
        }, $results);
        
        wp_send_json_success([
            'results' => $formatted_results,
            'count' => count($formatted_results),
        ]);
    }
    
    /**
     * AJAX: Delete item
     * 
     * Smaže záznam přes model->delete().
     * Volá before_delete() a after_delete() hooks.
     */
    public function ajax_delete() {
        // ✅ UNIVERZÁLNÍ NONCE - funguje pro všechny moduly
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        // Získej ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
        }
        
        // Zkontroluj jestli záznam existuje
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(['message' => ucfirst($this->entity) . ' not found']);
        }
        
        // Before delete hook (lze overridnout v controlleru)
        if (!$this->before_delete($id)) {
            wp_send_json_error(['message' => 'Cannot delete ' . $this->entity]);
        }
        
        // Proveď delete
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // After delete hook
        $this->after_delete($id);
        
        wp_send_json_success(['message' => ucfirst($this->entity) . ' deleted successfully']);
    }
    
    /**
     * AJAX: Get item detail for modal
     * 
     * Načte detail záznamu a pošle ho jako JSON pro modal.
     * Volá format_detail_data() pro formátování.
     * 
     * ✅ OPRAVA: Používá univerzální nonce 'saw_ajax_nonce' místo 'saw_customer_modal_nonce'
     */
    public function ajax_get_detail() {
        // ✅ UNIVERZÁLNÍ NONCE - funguje pro account-types, customers, i další moduly
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        // Získej ID z POST dat
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
        }
        
        // Načti záznam z DB
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(['message' => ucfirst($this->entity) . ' nenalezen']);
        }
        
        // Formátuj data pro modal (např. přidej formatted dates)
        $item = $this->format_detail_data($item);
        
        // Pošli data jako JSON
        // Posíláme item pod více klíči pro kompatibilitu s různými modal templates
        wp_send_json_success([
            $this->entity => $item,      // Pro account-types: 'account-types' => $item
            'customer' => $item,          // Pro backwards compatibility s customers
            'item' => $item,              // Univerzální klíč
        ]);
    }
    
    /**
     * Format detail data for modal
     * 
     * Override v child controlleru pro vlastní formátování.
     * Defaultně přidává formatted dates.
     * 
     * @param array $item Raw data z DB
     * @return array Formátovaná data
     */
    protected function format_detail_data($item) {
        // Formátuj created_at (např. "31.10.2025 14:30")
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        // Formátuj updated_at
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Format search result
     * 
     * Override v child controlleru pro vlastní formátování search results.
     * Defaultně vrací item beze změn.
     * 
     * @param array $item Search result item
     * @return array Formátovaný item
     */
    protected function format_search_result($item) {
        return $item;
    }
}