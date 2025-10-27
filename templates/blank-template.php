<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    
    <?php
    /**
     * SAW Blank Template - Meta Tags Hook
     * 
     * Umožňuje přidat custom meta tags pro každou stránku
     * Použití: add_action( 'saw_blank_template_head', function() { ... } );
     */
    do_action( 'saw_blank_template_head' );
    ?>
    
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - SAW Visitors</title>
    
    <?php
    /**
     * Load WordPress head (pro správné fungování pluginů)
     */
    wp_head();
    ?>
    
    <!-- SAW Visitors Default Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        
        /* Container */
        .saw-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        p {
            margin-bottom: 1rem;
        }
        
        /* Links */
        a {
            color: #0073aa;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #005177;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: #0073aa;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Utility classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .mt-3 { margin-top: 1.5rem; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-3 { margin-bottom: 1.5rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .saw-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="saw-blank-template">
    
    <?php
    /**
     * Before content hook
     * 
     * Umožňuje přidat obsah před hlavní content area
     * Použití: add_action( 'saw_before_content', function() { ... } );
     */
    do_action( 'saw_before_content' );
    ?>
    
    <!-- Main Content Area -->
    <div id="saw-content" class="saw-container">
        <?php
        /**
         * Main content
         * 
         * Template file je načten zde
         * $template_file je definován v SAW_Router::render_template()
         */
        if ( isset( $template_file ) && file_exists( $template_file ) ) {
            // Extract data pro template
            if ( isset( $data ) && is_array( $data ) ) {
                extract( $data );
            }
            
            include $template_file;
        } else {
            ?>
            <div class="message message-error">
                <p><strong>Chyba:</strong> Template nenalezen.</p>
            </div>
            <?php
        }
        ?>
    </div>
    
    <?php
    /**
     * After content hook
     * 
     * Umožňuje přidat obsah za hlavní content area
     * Použití: add_action( 'saw_after_content', function() { ... } );
     */
    do_action( 'saw_after_content' );
    ?>
    
    <?php
    /**
     * WordPress footer (pro správné fungování pluginů)
     */
    wp_footer();
    ?>
</body>
</html>
