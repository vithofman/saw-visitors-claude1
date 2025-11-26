<?php
if (!defined('ABSPATH')) exit;
?>
    </div><!-- .saw-terminal-content -->
</div><!-- .saw-terminal-wrapper -->

<?php
// CRITICAL: Print WordPress media templates (podle content modulu)
// Toto MUSÍ být tady pro media gallery
wp_print_media_templates();

wp_footer();
?>
</body>
</html>
