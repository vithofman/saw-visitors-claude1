<?php
/**
 * SAW PWA - Head Tags Include
 *
 * Přidej tento soubor do <head> sekce všech layout souborů:
 * <?php include SAW_VISITORS_PLUGIN_DIR . 'includes/pwa/pwa-head-tags.php'; ?>
 *
 * @package    SAW_Visitors
 * @subpackage PWA
 * @since      1.0.0
 * @version    2.0.0 - Added mobile recovery script
 */

if (!defined('ABSPATH')) {
    exit;
}

// Získej PWA instanci pokud existuje
$saw_pwa = class_exists('SAW_PWA') ? SAW_PWA::instance() : null;
$pwa_enabled = $saw_pwa && $saw_pwa->is_enabled();

if (!$pwa_enabled) {
    return;
}

// URLs
$manifest_url = $saw_pwa->get_manifest_url();
$pwa_url = $saw_pwa->get_pwa_url();
$theme_color = $saw_pwa->get_theme_color();
?>

<!-- PWA Meta Tags -->
<meta name="theme-color" content="<?php echo esc_attr($theme_color); ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SAW Visitors">
<meta name="application-name" content="SAW Visitors">
<meta name="msapplication-TileColor" content="<?php echo esc_attr($theme_color); ?>">
<meta name="msapplication-config" content="none">

<!-- PWA Manifest -->
<link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">

<!-- Apple Touch Icons -->
<link rel="apple-touch-icon" href="<?php echo esc_url($pwa_url . 'icons/icon-192x192.png'); ?>">
<link rel="apple-touch-icon" sizes="144x144" href="<?php echo esc_url($pwa_url . 'icons/icon-144x144.png'); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url($pwa_url . 'icons/icon-192x192.png'); ?>">
<link rel="apple-touch-icon" sizes="167x167" href="<?php echo esc_url($pwa_url . 'icons/icon-192x192.png'); ?>">

<!-- Microsoft Tiles -->
<meta name="msapplication-TileImage" content="<?php echo esc_url($pwa_url . 'icons/icon-144x144.png'); ?>">

<!-- Favicon fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url($pwa_url . 'icons/icon-96x96.png'); ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url($pwa_url . 'icons/icon-72x72.png'); ?>">

<!-- PWA Registration Script -->
<script src="<?php echo esc_url($pwa_url . 'pwa-register.js'); ?>?v=<?php echo SAW_VISITORS_VERSION; ?>" defer></script>

<!-- Mobile Recovery Script (fixes frozen pages on mobile devices) -->
<script src="<?php echo esc_url($pwa_url . 'mobile-recovery.js'); ?>?v=<?php echo SAW_VISITORS_VERSION; ?>" defer></script>

<?php
// Apple Splash Screens (volitelné - pro lepší iOS experience)
// Tyto by bylo potřeba vygenerovat pro různé velikosti obrazovek
?>

<!-- iOS Splash Screen Color -->
<style>
    /* Prevent iOS zoom on input focus */
    @supports (-webkit-touch-callout: none) {
        input, select, textarea {
            font-size: 16px !important;
        }
    }
    
    /* PWA Standalone mode adjustments */
    @media all and (display-mode: standalone) {
        body {
            /* Přidej padding pro notch na iPhone X+ */
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }
    }
</style>