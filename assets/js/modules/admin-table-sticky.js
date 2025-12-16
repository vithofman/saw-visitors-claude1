/**
 * SAW Admin Table - Sticky Header (Pure CSS Approach)
 * 
 * JEDNODUCHÝ PŘÍSTUP:
 * ===================
 * Tento modul pouze nastavuje CSS proměnnou --saw-toolbar-height,
 * která určuje top offset pro sticky thead.
 * 
 * ŽÁDNÉ JS klony, žádné position:fixed - čisté CSS sticky.
 * 
 * @package SAW_Visitors
 * @version 8.0.0 - REFACTORED: Pure CSS Sticky
 */
(function() {
    'use strict';
    
    /**
     * Měří výšku toolbaru a nastavuje CSS proměnné
     */
    function updateToolbarHeight() {
        const toolbar = document.querySelector('.sa-table-toolbar');
        const tableScroll = document.querySelector('.sa-table-scroll');
        
        if (!toolbar || !tableScroll) {
            console.log('[Sticky] Elements not found, skipping');
            return;
        }
        
        const height = toolbar.offsetHeight;
        
        // Nastavit CSS proměnnou na scroll container
        tableScroll.style.setProperty('--saw-toolbar-height', height + 'px');
        
        // Pro mobilní verzi (toolbar může být vyšší kvůli wrappingu)
        if (window.innerWidth <= 768) {
            tableScroll.style.setProperty('--saw-toolbar-height-mobile', height + 'px');
        }
        
        console.log('[Sticky] Toolbar height set:', height + 'px');
    }
    
    /**
     * Detekce "stuck" stavu toolbaru pro vizuální efekty
     * Přidá/odebere třídu .is-stuck na toolbar
     */
    function initStuckDetection() {
        const toolbar = document.querySelector('.sa-table-toolbar');
        const scrollArea = document.querySelector('.sa-table-scroll');
        
        if (!toolbar || !scrollArea) return;
        
        let ticking = false;
        
        const checkStuck = () => {
            const scrollTop = scrollArea.scrollTop;
            const isStuck = scrollTop > 0;
            
            if (isStuck) {
                toolbar.classList.add('is-stuck');
            } else {
                toolbar.classList.remove('is-stuck');
            }
            
            ticking = false;
        };
        
        scrollArea.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(checkStuck);
                ticking = true;
            }
        }, { passive: true });
        
        // Initial check
        checkStuck();
    }
    
    /**
     * Inicializace
     */
    function init() {
        console.log('[Sticky] Initializing Pure CSS Sticky...');
        
        // Počkat na DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', onReady);
        } else {
            onReady();
        }
    }
    
    function onReady() {
        // Malé zpoždění pro jistotu že toolbar je renderovaný
        setTimeout(() => {
            updateToolbarHeight();
            initStuckDetection();
        }, 10);
        
        // Aktualizovat při resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(updateToolbarHeight, 100);
        }, { passive: true });
        
        // Aktualizovat po dynamickém načtení obsahu (SPA navigace)
        document.addEventListener('saw:content-loaded', () => {
            setTimeout(updateToolbarHeight, 10);
        });
        
        document.addEventListener('saw:page-loaded', () => {
            setTimeout(() => {
                updateToolbarHeight();
                initStuckDetection();
            }, 10);
        });
        
        // MutationObserver pro změny v toolbaru
        setTimeout(() => {
            const toolbar = document.querySelector('.sa-table-toolbar');
            if (toolbar) {
                const observer = new MutationObserver(() => {
                    updateToolbarHeight();
                });
                observer.observe(toolbar, { 
                    childList: true, 
                    subtree: true,
                    attributes: true 
                });
            }
        }, 100);
        
        console.log('[Sticky] Pure CSS Sticky initialized ✓');
    }
    
    init();
    
    // Exportovat pro ruční volání
    window.sawUpdateToolbarHeight = updateToolbarHeight;
    
})();
