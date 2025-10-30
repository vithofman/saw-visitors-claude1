<?php
/**
 * Account Type Model
 *
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Model_Account_Type {
    
    protected $table_name;
    protected $db;
    
    protected $fillable = [
        'name',
        'display_name',
        'color',
        'price',
        'features',
        'sort_order',
        'is_active'
    ];
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . 'saw_account_types';
    }
    
    public function get_all($args = []) {
        $defaults = [
            'orderby' => 'sort_order',
            'order' => 'ASC',
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'is_active' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(name LIKE %s OR display_name LIKE %s)';
            $search_term = '%' . $this->db->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if ($args['is_active'] !== null) {
            $where_conditions[] = 'is_active = %d';
            $where_values[] = (int) $args['is_active'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $order_clause = sprintf(
            'ORDER BY %s %s',
            $this->sanitize_orderby($args['orderby']),
            $args['order'] === 'DESC' ? 'DESC' : 'ASC'
        );
        
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} {$order_clause} LIMIT %d OFFSET %d";
        
        if (!empty($where_values)) {
            $query = $this->db->prepare($sql, array_merge($where_values, [$args['per_page'], $offset]));
        } else {
            $query = $this->db->prepare($sql, $args['per_page'], $offset);
        }
        
        $results = $this->db->get_results($query);
        
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_query = $this->db->prepare($count_sql, $where_values);
        } else {
            $count_query = $count_sql;
        }
        $total = $this->db->get_var($count_query);
        
        return [
            'items' => $results,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page'])
        ];
    }
    
    public function get_by_id($id) {
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        return $this->db->get_row($sql);
    }
    
    public function create($data) {
        $validated = $this->validate($data);
        
        if (is_wp_error($validated)) {
            return $validated;
        }
        
        $insert_data = [
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'color' => $validated['color'],
            'price' => $validated['price'],
            'features' => $validated['features'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
            'created_at' => current_time('mysql')
        ];
        
        $result = $this->db->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            error_log('SAW Account Type Create Error: ' . $this->db->last_error);
            return new WP_Error('db_error', 'Failed to create account type: ' . $this->db->last_error);
        }
        
        return $this->db->insert_id;
    }
    
    public function update($id, $data) {
        $validated = $this->validate($data, $id);
        
        if (is_wp_error($validated)) {
            return $validated;
        }
        
        $update_data = [
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'color' => $validated['color'],
            'price' => $validated['price'],
            'features' => $validated['features'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
            'updated_at' => current_time('mysql')
        ];
        
        $result = $this->db->update(
            $this->table_name,
            $update_data,
            ['id' => $id]
        );
        
        if ($result === false) {
            error_log('SAW Account Type Update Error: ' . $this->db->last_error);
            return new WP_Error('db_error', 'Failed to update account type: ' . $this->db->last_error);
        }
        
        return true;
    }
    
    public function delete($id) {
        $result = $this->db->delete($this->table_name, ['id' => $id]);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete account type');
        }
        
        return true;
    }
    
    protected function validate($data, $id = null) {
        $errors = new WP_Error();
        
        if (empty($data['name'])) {
            $errors->add('name', 'Name is required');
        } elseif (strlen($data['name']) > 50) {
            $errors->add('name', 'Name cannot exceed 50 characters');
        }
        
        if (empty($data['display_name'])) {
            $errors->add('display_name', 'Display name is required');
        } elseif (strlen($data['display_name']) > 100) {
            $errors->add('display_name', 'Display name cannot exceed 100 characters');
        }
        
        if (empty($data['color'])) {
            $data['color'] = '#6b7280';
        } elseif (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
            $errors->add('color', 'Invalid color format');
        }
        
        if (!isset($data['price'])) {
            $data['price'] = 0.00;
        } elseif (!is_numeric($data['price']) || $data['price'] < 0) {
            $errors->add('price', 'Price must be a positive number');
        }
        
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        } elseif (!is_numeric($data['sort_order'])) {
            $errors->add('sort_order', 'Sort order must be a number');
        }
        
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        } else {
            $data['is_active'] = (int) (bool) $data['is_active'];
        }
        
        if (!empty($data['features']) && !is_string($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return $data;
    }
    
    private function sanitize_orderby($orderby) {
        $allowed = ['id', 'name', 'display_name', 'price', 'sort_order', 'is_active', 'created_at'];
        return in_array($orderby, $allowed) ? $orderby : 'sort_order';
    }
}