    </div><!-- .saw-terminal-content -->
</div><!-- .saw-terminal-wrapper -->

<!-- Minimální footer -->
<footer class="saw-terminal-footer">
    <div class="saw-terminal-footer-content">
        <p class="saw-terminal-footer-text">
            © <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
        </p>
        <?php 
        $flow = $this->session->get('invitation_flow');
        if (isset($flow['language'])): 
        ?>
        <p class="saw-terminal-footer-lang">
            <?php 
            $lang_names = [
                'cs' => 'Čeština',
                'en' => 'English',
                'uk' => 'Українська',
            ];
            echo $lang_names[$flow['language']] ?? $flow['language']; 
            ?>
        </p>
        <?php endif; ?>
    </div>
</footer>

<?php 
// Print media templates for WYSIWYG editor (only on risks step)
$current_step = $this->current_step ?? '';
if ($current_step === 'risks') {
    if (function_exists('wp_print_media_templates')) {
        wp_print_media_templates();
    }
}
wp_footer(); 
?>

</body>
</html>

