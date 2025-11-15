<?php
/**
 * SAW Select-Create Component
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SelectCreate
 * @version     1.2.0
 * @since       13.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Select_Create {
    
    private $field_name;
    private $config;
    
    public function __construct($field_name, $config = array()) {
        $this->field_name = sanitize_key($field_name);
        $this->config = $this->parse_config($config);
        $this->enqueue_assets();
    }
    
    private function parse_config($config) {
        $defaults = array(
            'label' => '',
            'options' => array(),
            'selected' => '',
            'required' => false,
            'placeholder' => '-- Vyberte --',
            'custom_class' => '',
            'inline_create' => array(
                'enabled' => false,
                'target_module' => '',
                'button_text' => '+ Nový',
                'prefill' => array(),
            ),
        );
        
        $merged = wp_parse_args($config, $defaults);
        
        if (!empty($merged['inline_create'])) {
            $merged['inline_create'] = wp_parse_args(
                $merged['inline_create'],
                $defaults['inline_create']
            );
        }
        
        return $merged;
    }
    
    private function enqueue_assets() {
        if (!wp_script_is('saw-app', 'enqueued')) {
            wp_enqueue_script('saw-app');
        }
    }
    
    private function resolve_prefill_values($prefill) {
        if (empty($prefill)) {
            return array();
        }
        
        $resolved = array();
        
        foreach ($prefill as $key => $value) {
            if (is_string($value) && strpos($value, 'context.') === 0) {
                $context_key = str_replace('context.', '', $value);
                
                if (class_exists('SAW_Context')) {
                    switch ($context_key) {
                        case 'branch_id':
                            $resolved[$key] = SAW_Context::get_branch_id();
                            break;
                        case 'customer_id':
                            $resolved[$key] = SAW_Context::get_customer_id();
                            break;
                        default:
                            if (method_exists('SAW_Context', 'get')) {
                                $resolved[$key] = SAW_Context::get($context_key);
                            }
                            break;
                    }
                }
            } else {
                $resolved[$key] = $value;
            }
        }
        
        return $resolved;
    }
    
    public function render() {
        $field_name = $this->field_name;
        $config = $this->config;
        
        if (!empty($config['inline_create']['enabled'])) {
            $config['inline_create']['prefill'] = $this->resolve_prefill_values(
                $config['inline_create']['prefill']
            );
        }
        
        include __DIR__ . '/select-create-input.php';
    }
}