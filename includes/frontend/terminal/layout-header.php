<?php
/**
 * Terminal Layout Header
 * 
 * Fullscreen layout WITHOUT WordPress header/footer
 * Clean terminal interface with home button
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo get_bloginfo('name'); ?> - Terminál</title>
    
    <?php wp_head(); ?>
    
    <style>
        /* Hide WordPress admin bar completely */
        body {
            margin: 0 !important;
            padding: 0 !important;
        }
        #wpadminbar {
            display: none !important;
        }
        html {
            margin-top: 0 !important;
        }
        
        /* Prevent text selection on touch devices */
        * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        input, textarea, select {
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }
    </style>
</head>
<body class="saw-terminal-body">

<!-- Home Button (kulaté tlačítko vlevo nahoře) -->
<?php
// ✅ NOVÉ: Podmíněný odkaz podle invitation mode
$flow = $flow ?? [];
$is_invitation = ($flow['mode'] ?? '') === 'invitation';

if ($is_invitation) {
    // V invitation mode - tlačítko domů přesměruje na začátek invitation flow
    $home_url = home_url('/terminal/?invitation=1');
} else {
    // Běžný terminal - vyžaduje přihlášení
    $home_url = home_url('/terminal/');
}
?>
<a href="<?php echo esc_url($home_url); ?>" class="saw-terminal-home-btn" title="Začít znovu">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        <polyline points="9 22 9 12 15 12 15 22"></polyline>
    </svg>
</a>

<!-- Main Terminal Container -->
<div class="saw-terminal-wrapper">
    <div class="saw-terminal-content">
        
        <?php if (isset($error) && !empty($error)): ?>
        <div class="saw-terminal-error">
            <span class="saw-terminal-error-icon">⚠️</span>
            <span class="saw-terminal-error-message"><?php echo esc_html($error); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Template content will be inserted here -->