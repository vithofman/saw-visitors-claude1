        
        </div><!-- .saw-terminal-content -->
        
        <!-- Terminal Footer -->
        <div class="saw-terminal-footer">
            <div class="saw-terminal-footer-content">
                <p class="saw-terminal-footer-text">
                    © <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
                <?php if (isset($flow['language'])): ?>
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
        </div>
        
    </div><!-- .saw-terminal-wrapper -->
    
    <?php wp_footer(); ?>
    
</body>
</html>
