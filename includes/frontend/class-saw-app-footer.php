<?php
/**
 * SAW App Footer Component
 *
 * Renders application footer with branding, version info, and links.
 *
 * @package SAW_Visitors
 * @version 4.6.1
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW App Footer Class
 *
 * Simple component for rendering application footer.
 * Displays plugin version, copyright, and external links.
 *
 * @since 4.6.1
 */
class SAW_App_Footer {
    
    /**
     * Render footer
     *
     * Outputs footer HTML with branding, version, copyright,
     * and links to documentation and support.
     *
     * @since 4.6.1
     * @return void
     */
    public function render() {
        $current_year = date('Y');
        ?>
        <footer class="saw-app-footer">
            <div class="saw-footer-content">
                <div class="saw-footer-left">
                    <span class="saw-footer-brand">SAW Visitors</span>
                    <span class="saw-footer-version">v<?php echo esc_html(SAW_VISITORS_VERSION); ?></span>
                    <span class="saw-footer-copy">© <?php echo esc_html($current_year); ?> SAW</span>
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