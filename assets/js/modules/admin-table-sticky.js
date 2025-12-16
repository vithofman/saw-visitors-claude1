/**
 * SAW Admin Table - Sticky Header Fix
 * 
 * Opravuje scroll container pro správné fungování CSS position: sticky.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */
(function() {
    'use strict';
    
    function fixStickyTable() {
        // 1. Zajistit, že .saw-app-content nescrolluje když obsahuje tabulku
        var content = document.querySelector('.saw-app-content, .sa-app-content');
        if (content) {
            var hasTable = content.querySelector('.sa-table-scroll') !== null;
            if (hasTable) {
                content.style.overflow = 'hidden';
                content.style.height = '100%';
                content.style.maxHeight = '100%';
            }
        }
        
        // 2. Zajistit správnou výšku scroll containeru
        var scrollContainers = document.querySelectorAll('.sa-table-scroll');
        if (scrollContainers.length === 0) return;
        
        scrollContainers.forEach(function(scrollContainer) {
            var panel = scrollContainer.closest('.sa-table-panel');
            if (panel) {
                // Vypočítat dostupnou výšku
                var panelRect = panel.getBoundingClientRect();
                var contentRect = content ? content.getBoundingClientRect() : { top: 0 };
                var availableHeight = window.innerHeight - panelRect.top;
                
                // Pokud je panel uvnitř .saw-app-content, použít jeho výšku
                if (content && panelRect.top >= contentRect.top) {
                    availableHeight = contentRect.height - (panelRect.top - contentRect.top);
                }
                
                if (availableHeight > 0) {
                    scrollContainer.style.height = availableHeight + 'px';
                    scrollContainer.style.maxHeight = availableHeight + 'px';
                    panel.style.height = '100%';
                    panel.style.maxHeight = '100%';
                }
            }
        });
    }
    
    // Spustit po načtení
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fixStickyTable);
    } else {
        fixStickyTable();
    }
    
    // Re-run při změnách (dynamický obsah, sidebar otevření/zavření)
    setTimeout(fixStickyTable, 100);
    setTimeout(fixStickyTable, 500);
    
    // Re-run při resize
    window.addEventListener('resize', function() {
        setTimeout(fixStickyTable, 100);
    });
    
    window.sawFixStickyTable = fixStickyTable;
})();
