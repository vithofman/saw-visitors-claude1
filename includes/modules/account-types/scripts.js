/**
 * Account Types Module Scripts
 * 
 * JavaScript pro:
 * - Automatické generování slug z display_name
 * - Validaci interního názvu (slug)
 * - Features textarea helpers (emoji shortcuts)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since   4.9.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ================================================
        // DISPLAY NAME → SLUG AUTO-GENERATION
        // ================================================
        // Při vytváření nového typu (ne editaci) automaticky generuj slug z display_name
        
        const $nameField = $('#name');
        const $displayNameField = $('#display_name');
        const isEditMode = $nameField.prop('readonly');
        
        // Pokud NENÍ edit mode (readonly), generuj slug automaticky
        if (!isEditMode && $nameField.length && $displayNameField.length) {
            $displayNameField.on('input', function() {
                const displayName = $(this).val();
                const slug = generateSlug(displayName);
                $nameField.val(slug);
            });
        }
        
        /**
         * Generate slug from display name
         * 
         * @param {string} text Display name
         * @return {string} Slug (lowercase, no spaces, only a-z0-9-)
         */
        function generateSlug(text) {
            return text
                .toLowerCase()
                .normalize('NFD') // Rozloží diakritiku
                .replace(/[\u0300-\u036f]/g, '') // Odstraní diakritiku
                .replace(/[^a-z0-9\s\-]/g, '') // Jen písmena, číslice, mezery, pomlčky
                .trim()
                .replace(/\s+/g, '-') // Mezery → pomlčky
                .replace(/-+/g, '-'); // Vícenásobné pomlčky → jedna
        }
        
        // ================================================
        // NAME (SLUG) VALIDATION
        // ================================================
        // Validuje interní název při blur (ztráta focusu)
        
        $('#name').on('blur', function() {
            const name = $(this).val();
            
            // Check if empty
            if (!name) {
                return;
            }
            
            // Check pattern: jen lowercase, číslice, pomlčky
            if (!/^[a-z0-9\-]+$/.test(name)) {
                alert('Interní název může obsahovat jen malá písmena, číslice a pomlčky!');
                $(this).focus();
            }
        });
        
        // ================================================
        // FEATURES TEXTAREA HELPERS
        // ================================================
        // Přidává emoji shortcuts pro features textarea
        
        const $featuresTextarea = $('#features');
        
        if ($featuresTextarea.length) {
            // Tlačítka pro rychlé vložení emoji
            const emojiButtons = $('<div class="saw-emoji-shortcuts"></div>');
            
            const shortcuts = [
                { emoji: '✓', label: 'Checkmark' },
                { emoji: '✗', label: 'Cross' },
                { emoji: '🎯', label: 'Target' },
                { emoji: '⭐', label: 'Star' },
                { emoji: '💎', label: 'Diamond' },
                { emoji: '🚀', label: 'Rocket' },
            ];
            
            shortcuts.forEach(function(shortcut) {
                const btn = $('<button type="button" class="saw-emoji-btn" title="' + shortcut.label + '">' + shortcut.emoji + '</button>');
                
                btn.on('click', function(e) {
                    e.preventDefault();
                    insertAtCursor($featuresTextarea[0], shortcut.emoji + ' ');
                });
                
                emojiButtons.append(btn);
            });
            
            // Vlož emoji buttons před textarea
            $featuresTextarea.before(emojiButtons);
        }
        
        /**
         * Insert text at cursor position in textarea
         * 
         * @param {HTMLElement} textarea Textarea element
         * @param {string} text Text to insert
         */
        function insertAtCursor(textarea, text) {
            const startPos = textarea.selectionStart;
            const endPos = textarea.selectionEnd;
            const scrollTop = textarea.scrollTop;
            
            const value = textarea.value;
            
            textarea.value = value.substring(0, startPos) + 
                           text + 
                           value.substring(endPos, value.length);
            
            textarea.selectionStart = startPos + text.length;
            textarea.selectionEnd = startPos + text.length;
            textarea.scrollTop = scrollTop;
            
            textarea.focus();
        }
        
        // ================================================
        // PRICE FORMATTING
        // ================================================
        // Formátuje price input při blur (2 decimal places)
        
        $('#price').on('blur', function() {
            const value = parseFloat($(this).val());
            
            if (!isNaN(value)) {
                $(this).val(value.toFixed(2));
            }
        });
        
        // ================================================
        // DISPLAY NAME SYNC TO PREVIEW
        // ================================================
        // Aktualizuje preview badge při změně display_name
        
        $('#display_name').on('input', function() {
            const name = $(this).val() || 'Název';
            $('#color-preview-badge').text(name);
        });
        
    });
    
})(jQuery);

/* ================================================
   CSS PRO EMOJI SHORTCUTS
   ================================================ */

const emojiShortcutsStyles = `
.saw-emoji-shortcuts {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.saw-emoji-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.saw-emoji-btn:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.saw-emoji-btn:active {
    transform: translateY(0);
}
`;

// Inject styles
if (typeof document !== 'undefined') {
    const style = document.createElement('style');
    style.textContent = emojiShortcutsStyles;
    document.head.appendChild(style);
}
