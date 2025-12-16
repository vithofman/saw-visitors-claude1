/**
 * SAW Admin Table - Sticky Header
 * 
 * Nastavuje CSS proměnnou --saw-toolbar-height pro správné pozicování
 * sticky thead pod toolbarem.
 * 
 * STICKY FIX: CSS proměnná se nastavuje na .sa-table-scroll,
 * který je scroll container pro sticky elementy.
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 */
(function() {
    'use strict';
    
    /**
     * Měří výšku toolbaru a nastavuje CSS proměnnou na scroll container
     */
    function updateToolbarHeight() {
        var toolbar = document.querySelector('.sa-table-toolbar');
        var tableScroll = document.querySelector('.sa-table-scroll');
        
        if (!toolbar || !tableScroll) return;
        
        var height = toolbar.offsetHeight;
        
        // Nastavit CSS proměnnou na scroll container
        // Tato proměnná se použije pro top hodnotu sticky thead
        tableScroll.style.setProperty('--saw-toolbar-height', height + 'px');
        
        // Pro mobilní verzi - menší toolbar
        if (window.innerWidth <= 768) {
            tableScroll.style.setProperty('--saw-toolbar-height-mobile', height + 'px');
        }
        
        console.log('📏 Toolbar height:', height + 'px');
    }
    
    /**
     * Inicializace
     */
    function init() {
        // Počkat na DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(updateToolbarHeight, 10); // Malé zpoždění pro jistotu
            });
        } else {
            setTimeout(updateToolbarHeight, 10);
        }
        
        // Aktualizovat při resize
        var resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(updateToolbarHeight, 100);
        });
        
        // Aktualizovat po dynamickém načtení obsahu (SPA navigace)
        document.addEventListener('saw:content-loaded', function() {
            setTimeout(updateToolbarHeight, 10);
        });
        
        // MutationObserver pro změny v toolbaru (např. změna záložek)
        setTimeout(function() {
            var toolbar = document.querySelector('.sa-table-toolbar');
            if (toolbar) {
                var observer = new MutationObserver(function() {
                    updateToolbarHeight();
                });
                observer.observe(toolbar, { 
                    childList: true, 
                    subtree: true,
                    attributes: true 
                });
            }
        }, 100);
    }
    
    init();
    
    // Exportovat pro ruční volání
    window.sawUpdateToolbarHeight = updateToolbarHeight;
})();
