<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo get_bloginfo('name'); ?> - Terminal</title>
    
    <?php wp_head(); ?>
    
    <style>
        /* Prevent text selection on touch devices */
        * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        input, textarea {
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }
    </style>
</head>
<body class="saw-terminal-body">
    
    <div class="saw-terminal-wrapper">
        
        <!-- Terminal Header -->
        <div class="saw-terminal-header">
            <div class="saw-terminal-header-content">
                <h1 class="saw-terminal-logo">
                    <?php echo get_bloginfo('name'); ?>
                </h1>
                <button type="button" class="saw-terminal-reset-btn" onclick="location.href='<?php echo home_url('/terminal/'); ?>'">
                    🔄 <?php _e('Začít znovu', 'saw-visitors'); ?>
                </button>
            </div>
        </div>
        
        <!-- Terminal Content -->
        <div class="saw-terminal-content">
            
            <?php if (isset($error) && !empty($error)): ?>
            <div class="saw-terminal-error">
                <span class="saw-terminal-error-icon">⚠️</span>
                <span class="saw-terminal-error-message"><?php echo esc_html($error); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Template content will be inserted here -->
