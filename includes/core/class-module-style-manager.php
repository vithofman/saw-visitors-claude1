<?php
/**
 * Module Style Manager
 * Handles dynamic CSS injection for modules to prevent CSS conflicts
 * 
 * @package SAW_Visitors
 * @version 1.0.0
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
            
            function cleanupModuleStyles() {
                const activeModuleWrapper = document.querySelector('[class*="saw-module-"]');
                
                if (!activeModuleWrapper) {
                    return;
                }
                
                const classes = activeModuleWrapper.className.split(' ');
                let activeModule = null;
                
                for (let className of classes) {
                    if (className.startsWith('saw-module-')) {
                        activeModule = className.replace('saw-module-', '');
                        break;
                    }
                }
                
                if (!activeModule) {
                    return;
                }
                
                const allModuleStyles = document.querySelectorAll('style[data-saw-module]');
                
                allModuleStyles.forEach(function(styleTag) {
                    const moduleSlug = styleTag.getAttribute('data-saw-module');
                    
                    if (moduleSlug !== activeModule) {
                        styleTag.remove();
                    }
                });
            }
            
            cleanupModuleStyles();
            
            const observer = new MutationObserver(function(mutations) {
                for (let mutation of mutations) {
                    if (mutation.target.classList && 
                        mutation.target.classList.toString().includes('saw-module-')) {
                        cleanupModuleStyles();
                        break;
                    }
                }
            });
            
            const contentArea = document.getElementById('saw-admin-content');
            if (contentArea) {
                observer.observe(contentArea, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
            
            document.addEventListener('sawModuleChanged', cleanupModuleStyles);
            
        })();
        </script>
        <?php
    }
    
    public function get_active_module() {
        return $this->active_module;
    }
}