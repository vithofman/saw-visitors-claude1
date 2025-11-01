<?php
/**
 * Module Style Manager - ULTRA BRUTAL VERSION
 * 
 * @package SAW_Visitors
 * @version 3.0.0 - ULTRA BRUTAL
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Style_Manager 
{
    private static $instance = null;
    private $active_module = null;
    private $css_cache = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Cleanup OKAMŽITĚ při každém page load
        add_action('admin_head', [$this, 'inject_immediate_cleanup'], 1);
    }
    
    public function get_module_css($module_slug) {
        if (isset($this->css_cache[$module_slug])) {
            return $this->css_cache[$module_slug];
        }
        
        $css_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/' . $module_slug . '/styles.css';
        
        if (file_exists($css_path)) {
            $css_content = file_get_contents($css_path);
            $this->css_cache[$module_slug] = $css_content;
            return $css_content;
        }
        
        return '';
    }
    
    public function inject_module_css($module_slug) {
        $css_content = $this->get_module_css($module_slug);
        
        if (empty($css_content)) {
            return '';
        }
        
        $this->active_module = $module_slug;
        
        $output = sprintf(
            '<style id="saw-module-css-%s" data-saw-module="%s" type="text/css">%s</style>',
            esc_attr($module_slug),
            esc_attr($module_slug),
            $css_content
        );
        
        return $output;
    }
    
    /**
     * ULTRA BRUTAL IMMEDIATE CLEANUP
     * 
     * Běží IHNED v <head>, PŘED jakýmkoliv contentem
     */
    public function inject_immediate_cleanup() {
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            console.log('[SAW-ULTRA-BRUTAL] IMMEDIATE cleanup starting...');
            
            // Funkce která se spustí OKAMŽITĚ a OPAKOVANĚ
            function brutalCleanup() {
                // 1. SMAŽ všechny staré wrappery
                const allWrappers = document.querySelectorAll('[class*="saw-module-"]');
                if (allWrappers.length > 1) {
                    console.log('[SAW-ULTRA-BRUTAL] Found ' + allWrappers.length + ' wrappers, removing old ones');
                    for (let i = 0; i < allWrappers.length - 1; i++) {
                        console.log('[SAW-ULTRA-BRUTAL] Removing wrapper:', allWrappers[i].className);
                        allWrappers[i].remove();
                    }
                }
                
                // 2. SMAŽ VŠECHNY modaly
                const allModals = document.querySelectorAll('[id*="saw-modal-"], .saw-modal');
                if (allModals.length > 0) {
                    console.log('[SAW-ULTRA-BRUTAL] Removing ' + allModals.length + ' modals');
                    allModals.forEach(function(modal) {
                        modal.remove();
                    });
                }
                
                // 3. SMAŽ VŠECHNY overlays
                const allOverlays = document.querySelectorAll('.saw-modal-overlay, .modal-backdrop, [class*="overlay"]');
                if (allOverlays.length > 0) {
                    console.log('[SAW-ULTRA-BRUTAL] Removing ' + allOverlays.length + ' overlays');
                    allOverlays.forEach(function(overlay) {
                        overlay.remove();
                    });
                }
                
                // 4. VYČISTI body
                document.body.classList.remove('modal-open', 'saw-modal-open', 'saw-modal-active');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                document.body.style.height = '';
                document.body.style.position = '';
                
                console.log('[SAW-ULTRA-BRUTAL] Cleanup done');
            }
            
            // Spusť OKAMŽITĚ
            brutalCleanup();
            
            // Spusť po DOMContentLoaded
            document.addEventListener('DOMContentLoaded', function() {
                console.log('[SAW-ULTRA-BRUTAL] DOMContentLoaded cleanup');
                brutalCleanup();
            });
            
            // Spusť po window.load
            window.addEventListener('load', function() {
                console.log('[SAW-ULTRA-BRUTAL] window.load cleanup');
                brutalCleanup();
            });
            
            // Spusť po každé změně URL (pro SPA navigaci)
            let lastUrl = location.href;
            new MutationObserver(function() {
                const url = location.href;
                if (url !== lastUrl) {
                    lastUrl = url;
                    console.log('[SAW-ULTRA-BRUTAL] URL changed, cleaning up');
                    setTimeout(brutalCleanup, 100);
                }
            }).observe(document, {subtree: true, childList: true});
            
            console.log('[SAW-ULTRA-BRUTAL] Cleanup system initialized');
            
        })();
        </script>
        <?php
    }
    
    public function get_active_module() {
        return $this->active_module;
    }
}