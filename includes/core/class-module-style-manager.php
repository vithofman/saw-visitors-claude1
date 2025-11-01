<?php
/**
 * Module Style Manager
 * Handles dynamic CSS injection for modules to prevent CSS conflicts
 * 
 * @package SAW_Visitors
 * @version 1.2.0
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
        add_action('admin_footer', [$this, 'inject_cleanup_script']);
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
    
    public function inject_cleanup_script() {
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            function cleanupModuleContent() {
                console.log('[SAW] Starting module cleanup...');
                
                // 1. Find active module wrapper
                const activeModuleWrapper = document.querySelector('[class*="saw-module-"]');
                
                if (!activeModuleWrapper) {
                    console.log('[SAW] No active module wrapper found');
                    return;
                }
                
                // 2. Extract active module slug
                const classes = activeModuleWrapper.className.split(' ');
                let activeModule = null;
                
                for (let className of classes) {
                    if (className.startsWith('saw-module-')) {
                        activeModule = className.replace('saw-module-', '');
                        break;
                    }
                }
                
                if (!activeModule) {
                    console.log('[SAW] Could not determine active module');
                    return;
                }
                
                console.log('[SAW] Active module:', activeModule);
                
                // 3. Remove CSS from inactive modules
                const allModuleStyles = document.querySelectorAll('style[data-saw-module]');
                let removedStyles = 0;
                
                allModuleStyles.forEach(function(styleTag) {
                    const moduleSlug = styleTag.getAttribute('data-saw-module');
                    
                    if (moduleSlug !== activeModule) {
                        console.log('[SAW] Removing CSS for module:', moduleSlug);
                        styleTag.remove();
                        removedStyles++;
                    }
                });
                
                console.log('[SAW] Removed', removedStyles, 'inactive module styles');
                
                // 4. CRITICAL: Remove ALL modals that are NOT part of active module
                const allModals = document.querySelectorAll('[id*="saw-modal-"], .saw-modal');
                let removedModals = 0;
                
                allModals.forEach(function(modal) {
                    // Check if modal is inside active module wrapper
                    if (!activeModuleWrapper.contains(modal)) {
                        console.log('[SAW] Removing orphaned modal:', modal.id || modal.className);
                        modal.remove();
                        removedModals++;
                    }
                });
                
                console.log('[SAW] Removed', removedModals, 'orphaned modals');
                
                // 5. Remove modal overlays/backdrops
                const overlays = document.querySelectorAll('.saw-modal-overlay, .modal-backdrop, [class*="overlay"]');
                overlays.forEach(function(overlay) {
                    if (!activeModuleWrapper.contains(overlay)) {
                        console.log('[SAW] Removing overlay:', overlay.className);
                        overlay.remove();
                    }
                });
                
                // 6. Clean up body classes
                document.body.classList.remove('modal-open', 'saw-modal-open');
                
                console.log('[SAW] Cleanup complete');
            }
            
            // Run cleanup immediately
            console.log('[SAW] Initial cleanup on page load');
            cleanupModuleContent();
            
            // Watch for DOM changes
            const observer = new MutationObserver(function(mutations) {
                for (let mutation of mutations) {
                    // Check if a new module wrapper was added
                    if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                        for (let node of mutation.addedNodes) {
                            if (node.nodeType === 1 && node.className && 
                                node.className.toString().includes('saw-module-')) {
                                console.log('[SAW] New module detected via MutationObserver');
                                setTimeout(cleanupModuleContent, 100);
                                return;
                            }
                        }
                    }
                }
            });
            
            // Observe content area
            const contentArea = document.getElementById('saw-admin-content') || 
                               document.querySelector('.saw-app-content') || 
                               document.body;
            
            if (contentArea) {
                observer.observe(contentArea, {
                    childList: true,
                    subtree: true
                });
                console.log('[SAW] MutationObserver attached to:', contentArea.id || contentArea.className);
            }
            
            // Listen for custom events
            document.addEventListener('sawModuleChanged', function() {
                console.log('[SAW] sawModuleChanged event triggered');
                setTimeout(cleanupModuleContent, 100);
            });
            
        })();
        </script>
        <?php
    }
    
    public function get_active_module() {
        return $this->active_module;
    }
}