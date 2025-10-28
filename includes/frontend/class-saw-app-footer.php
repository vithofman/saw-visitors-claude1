<?php
/**
 * SAW App Footer Component
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Footer {
    
    /**
     * Render footer
     * 
     * @return void
     */
    public function render() {
        ?>
        <footer class="saw-app-footer">
            <div class="saw-footer-content">
                <div class="saw-footer-left">
                    <span class="saw-footer-brand">SAW Visitors</span>
                    <span class="saw-footer-version">v<?php echo esc_html(SAW_VISITORS_VERSION); ?></span>
                    <span class="saw-footer-copy">© <?php echo date('Y'); ?> SAW</span>
                </div>
                
                <div class="saw-footer-right">
                    <a href="https://sawuh.cz" target="_blank" rel="noopener" class="saw-footer-link">
                        sawuh.cz
                    </a>
                    <a href="https://visitors.sawuh.cz/docs" target="_blank" rel="noopener" class="saw-footer-link">
                        Dokumentace
                    </a>
                    <a href="https://visitors.sawuh.cz/support" target="_blank" rel="noopener" class="saw-footer-link">
                        Podpora
                    </a>
                </div>
            </div>
        </footer>
        <?php
    }
}