/**
 * SAW Admin Table - Sticky Header
 * 
 * Nastavuje CSS proměnnou --saw-toolbar-height pro správné pozicování
 * sticky thead pod toolbarem.
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 */
(function() {
    'use strict';
    
    /**
     * Měří výšku toolbaru a nastavuje CSS proměnnou
     */
    function updateToolbarHeight() {
        var toolbar = document.querySelector('.sa-table-toolbar');
        if (!toolbar) return;
        
        var height = toolbar.offsetHeight;
        
        // Nastavit CSS proměnnou na scroll container
        var scrollContainer = document.querySelector('.sa-app-content, .saw-app-content');
        if (scrollContainer) {
            scrollContainer.style.setProperty('--saw-toolbar-height', height + 'px');
        }
        
        // Také nastavit na table-scroll pro jistotu
        var tableScroll = document.querySelector('.sa-table-scroll');
        if (tableScroll) {
            tableScroll.style.setProperty('--saw-toolbar-height', height + 'px');
        }
        
        // Pro mobilní verzi
        if (window.innerWidth <= 768) {
            if (scrollContainer) {
                scrollContainer.style.setProperty('--saw-toolbar-height-mobile', height + 'px');
            }
            if (tableScroll) {
                tableScroll.style.setProperty('--saw-toolbar-height-mobile', height + 'px');
            }
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
                updateToolbarHeight();
            });
        } else {
            updateToolbarHeight();
        }
        
        // Aktualizovat při resize
        var resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(updateToolbarHeight, 100);
        });
        
        // Aktualizovat po dynamickém načtení obsahu
        document.addEventListener('saw:content-loaded', updateToolbarHeight);
        
        // MutationObserver pro změny v toolbaru
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
    }
    
    init();
    
    // Exportovat pro ruční volání
    window.sawUpdateToolbarHeight = updateToolbarHeight;
})();
