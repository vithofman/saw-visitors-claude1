<?php
/**
 * Terminal Layout Header
 * 
 * Fullscreen layout WITHOUT WordPress header/footer
 * Clean terminal interface with home button
 * 
 * @package SAW_Visitors
 * @version 3.9.5
 * 
 * ZMĚNA v 3.9.5:
 * - Přidáno admin tlačítko pod home button (viditelné jen pro přihlášené adminy/managery)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get current user role for admin button visibility
 * 
 * Uses same logic as SAW_App_Sidebar for reliability
 */
function saw_terminal_get_user_role() {
    // Must be logged in
    if (!is_user_logged_in()) {
        return null;
    }
    
    // Check WordPress super admin first
    if (current_user_can('manage_options')) {
        // Try SAW_Context for actual role
        if (class_exists('SAW_Context')) {
            $role = SAW_Context::get_role();
            if ($role) {
                return $role;
            }
        }
        return 'super_admin';
    }
    
    // Check SAW Context
    if (class_exists('SAW_Context')) {
        $role = SAW_Context::get_role();
        if ($role) {
            return $role;
        }
    }
    
    // Fallback: database lookup
    global $wpdb;
    
    $saw_user = $wpdb->get_row($wpdb->prepare(
        "SELECT role FROM {$wpdb->prefix}saw_users 
         WHERE wp_user_id = %d AND is_active = 1",
        get_current_user_id()
    ));
    
    return $saw_user ? $saw_user->role : null;
}

// Check if user has admin/manager role for admin button visibility
$show_admin_button = false;
$current_role = saw_terminal_get_user_role();

if ($current_role) {
    $admin_roles = array('super_admin', 'admin', 'super_manager', 'manager');
    $show_admin_button = in_array($current_role, $admin_roles, true);
}

// Debug log (remove in production)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log(sprintf('[Terminal Layout] User logged in: %s, Role: %s, Show admin: %s', 
        is_user_logged_in() ? 'YES' : 'NO',
        $current_role ?? 'NULL',
        $show_admin_button ? 'YES' : 'NO'
    ));
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
        
        /* ===== NAVIGATION BUTTONS CONTAINER ===== */
        .saw-terminal-nav-buttons {
            position: fixed;
            top: 2.5rem;
            left: 2.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .saw-terminal-nav-buttons {
                top: 1.5rem;
                left: 1.5rem;
                gap: 0.75rem;
            }
        }
        
        /* ===== HOME BUTTON ===== */
        .saw-terminal-nav-buttons .saw-terminal-home-btn {
            position: static;
            width: 5rem;
            height: 5rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 3px solid rgba(102, 126, 234, 0.1);
        }
        
        .saw-terminal-nav-buttons .saw-terminal-home-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 48px rgba(102, 126, 234, 0.4);
            background: white;
            border-color: #667eea;
            color: #5a67d8;
        }
        
        .saw-terminal-nav-buttons .saw-terminal-home-btn:active {
            transform: scale(0.95);
        }
        
        .saw-terminal-nav-buttons .saw-terminal-home-btn svg {
            width: 2rem;
            height: 2rem;
            stroke-width: 2.5;
        }
        
        @media (max-width: 768px) {
            .saw-terminal-nav-buttons .saw-terminal-home-btn {
                width: 4rem;
                height: 4rem;
            }
            
            .saw-terminal-nav-buttons .saw-terminal-home-btn svg {
                width: 1.5rem;
                height: 1.5rem;
            }
        }
        
        /* ===== ADMIN BUTTON ===== */
        .saw-terminal-admin-btn {
            width: 5rem;
            height: 5rem;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #94a3b8;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 2px solid rgba(148, 163, 184, 0.2);
        }
        
        .saw-terminal-admin-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 48px rgba(99, 102, 241, 0.4);
            background: rgba(99, 102, 241, 0.9);
            border-color: #818cf8;
            color: white;
        }
        
        .saw-terminal-admin-btn:active {
            transform: scale(0.95);
        }
        
        .saw-terminal-admin-btn svg {
            width: 1.75rem;
            height: 1.75rem;
            stroke-width: 2;
        }
        
        @media (max-width: 768px) {
            .saw-terminal-admin-btn {
                width: 3.5rem;
                height: 3.5rem;
            }
            
            .saw-terminal-admin-btn svg {
                width: 1.25rem;
                height: 1.25rem;
            }
        }
    </style>
    <?php 
    if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/pwa/pwa-head-tags.php')) {
        include SAW_VISITORS_PLUGIN_DIR . 'includes/pwa/pwa-head-tags.php'; 
    }
    ?>
</head>
<body class="saw-terminal-body">

<!-- Navigation Buttons Container -->
<div class="saw-terminal-nav-buttons">
    <!-- Home Button (kulaté tlačítko) -->
    <a href="<?php echo esc_url(home_url('/terminal/')); ?>" class="saw-terminal-home-btn" title="Začít znovu">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
    </a>
    
    <?php if ($show_admin_button): ?>
    <!-- Admin Button (pouze pro přihlášené adminy/managery) -->
    <a href="<?php echo esc_url(home_url('/admin/dashboard')); ?>" class="saw-terminal-admin-btn" title="Administrace">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>
    </a>
    <?php endif; ?>
</div>

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