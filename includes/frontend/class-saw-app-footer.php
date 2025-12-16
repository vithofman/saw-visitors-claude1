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
        <footer class="sa-app-footer saw-app-footer">
            <div class="sa-footer-content saw-footer-content">
                <div class="sa-footer-left saw-footer-left">
                    <span class="sa-footer-brand saw-footer-brand">SAW Visitors</span>
                    <span class="sa-footer-version saw-footer-version">v<?php echo esc_html(SAW_VISITORS_VERSION); ?></span>
                    <span class="sa-footer-copy saw-footer-copy">© <?php echo esc_html($current_year); ?> SAW</span>
                </div>
                
                <div class="sa-footer-right saw-footer-right">
                    <a href="https://sawuh.cz" target="_blank" rel="noopener" class="sa-footer-link saw-footer-link">
                        sawuh.cz
                    </a>
                    <a href="https://visitors.sawuh.cz/docs" target="_blank" rel="noopener" class="sa-footer-link saw-footer-link">
                        Dokumentace
                    </a>
                    <a href="https://visitors.sawuh.cz/support" target="_blank" rel="noopener" class="sa-footer-link saw-footer-link">
                        Podpora
                    </a>
                </div>
            </div>
        </footer>
        <?php
    }
}