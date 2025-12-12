<?php
/**
 * SAW PWA - Head Tags
 *
 * @version 4.0.0 - INLINE SCRIPT FIX
 * 
 * Na mobilech používáme INLINE script (ne externí soubor)
 * který se spustí OKAMŽITĚ při parsování HTML.
 */

if (!defined('ABSPATH')) {
    exit;
}

$saw_pwa = class_exists('SAW_PWA') ? SAW_PWA::instance() : null;
$pwa_enabled = $saw_pwa && $saw_pwa->is_enabled();

if (!$pwa_enabled) {
    return;
}

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

<!-- PWA Manifest -->
<link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">

<!-- Icons -->
<link rel="apple-touch-icon" href="<?php echo esc_url($pwa_url . 'icons/icon-192x192.png'); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url($pwa_url . 'icons/icon-96x96.png'); ?>">

<!--
  CRITICAL: INLINE SCRIPT - spustí se OKAMŽITĚ při parsování HTML
  Na mobilech: žádný Service Worker, force reload při návratu z pozadí
-->
<script>
(function() {
    var ua = navigator.userAgent || '';
    var isMobile = /Android|iPhone|iPad|iPod/i.test(ua);
    var isPWA = window.matchMedia('(display-mode: standalone)').matches;
    
    // ============================================
    // MOBILE/PWA: Odstranit Service Worker + reload při resume
    // ============================================
    if (isMobile || isPWA) {
        
        // 1. OKAMŽITĚ odregistrovat Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(regs) {
                regs.forEach(function(r) { r.unregister(); });
            });
            // Vymazat všechny cache
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) { caches.delete(name); });
                });
            }
        }
        
        // 2. Reload při JAKÉMKOLIV návratu z pozadí
        var wasHidden = false;
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                wasHidden = true;
            } else if (wasHidden) {
                // Okamžitý reload - žádné čekání
                window.location.reload();
            }
        });
        
        // 3. Reload při obnovení z bfcache
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                window.location.reload();
            }
        });
        
        // KONEC - na mobilu nic dalšího
        return;
    }
    
    // ============================================
    // DESKTOP: Normální Service Worker
    // ============================================
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then(function(reg) {
                    // Check for updates every hour
                    setInterval(function() { reg.update(); }, 3600000);
                })
                .catch(function() {});
        });
    }
})();
</script>