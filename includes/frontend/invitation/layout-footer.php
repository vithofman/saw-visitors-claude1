<?php
if (!defined('ABSPATH')) exit;
?>
    </div><!-- .saw-terminal-content -->
</div><!-- .saw-terminal-wrapper -->

<?php 
// CRITICAL: This calls wp_footer() which triggers the wp_print_media_templates() hook!
wp_footer(); 
?>
</body>
</html>