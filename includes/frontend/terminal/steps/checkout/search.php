<?php
/**
 * Checkout - Search Method (Aurora Design v3.7)
 * 
 * @package     SAW_Visitors
 * @subpackage  Terminal
 * @version     3.7.0 - Centered form with slide animation
 */

if (!defined('ABSPATH')) exit;

$flow = SAW_Session_Manager::instance()->get('terminal_flow');
$language = $flow['language'] ?? 'cs';
$customer_id = $flow['customer_id'] ?? 0;
$branch_id = $flow['branch_id'] ?? 0;

// ✅ Generate fresh nonce
$search_nonce = wp_create_nonce('saw_terminal_search');
?>

<style>
/* === AURORA ULTRA MODERN DESIGN === */
:root {
    --theme-color: #667eea;
    --theme-color-hover: #764ba2;
    --bg-dark: #0f172a;
    --bg-dark-medium: #1e293b;
    --text-primary: #FFFFFF;
    --text-secondary: #e2e8f0;
    --text-muted: #94a3b8;
    --color-success: #10b981;
}

*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.saw-terminal-footer {
    display: none !important;
}

/* ✅ Main Container */
.saw-search-aurora {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    
    /* ✅ Animated gradient */
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    background-size: 200% 200%;
    animation: gradientShift 15s ease infinite;
    
    overflow-y: auto;
    padding: 2rem;
    
    /* ✅ Flexbox pro centrování */
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes gradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* ===================================
   CONTAINER - CENTERED → TWO COLUMN
=================================== */
.saw-checkout-search-container {
    width: 100%;
    max-width: 600px;
    
    /* ✅ Transition pro animaci */
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ✅ Po vyhledání - přepni na 2 sloupce */
.saw-checkout-search-container.has-results {
    max-width: 1400px;
    display: grid;
    grid-template-columns: 480px 1fr;
    gap: 3rem;
    align-items: start;
}

/* ===================================
   SEARCH CARD - GLASS CONTAINER
=================================== */
.saw-checkout-search-card {
    /* ✅ Glass morphism */
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.12) 0%,
        rgba(255, 255, 255, 0.06) 100%
    );
    backdrop-filter: blur(40px) saturate(180%);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 32px;
    padding: 3rem;
    
    box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    
    /* ✅ Smooth slide animation */
    animation: slideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Header */
.saw-checkout-search-header {
    text-align: center;
    margin-bottom: 2.5rem;
}



.saw-checkout-search-icon {
    font-size: 3.5rem;
    filter: drop-shadow(0 8px 16px rgba(102, 126, 234, 0.4));
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
}

.saw-checkout-search-title {
    font-size: 3rem;
    font-weight: 900;
    background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.04em;
    line-height: 1;
}

.saw-checkout-search-subtitle {
    font-size: 1.125rem;
    color: rgba(148, 163, 184, 0.9);
    font-weight: 500;
}

/* ===================================
   FORM
=================================== */
.saw-checkout-search-form {
    /* Žádný další wrapper */
}

.saw-checkout-form-group {
    margin-bottom: 1.5rem;
}

.saw-checkout-form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

/* ✅ GLOSSY INPUT */
.saw-checkout-search-input {
    width: 100%;
    padding: 1.5rem 1.75rem;
    font-size: 1.375rem;
    font-weight: 700;
    color: var(--text-primary);
    
    background: 
        linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06)),
        linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
    backdrop-filter: blur(20px) saturate(150%);
    
    border: 2px solid rgba(255, 255, 255, 0.25);
    border-radius: 20px;
    
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    
    box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.25),
        inset 0 -1px 0 rgba(0, 0, 0, 0.15);
}

.saw-checkout-search-input:focus {
    outline: none;
    background: 
        linear-gradient(180deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08)),
        linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.1));
    border-color: var(--theme-color);
    box-shadow: 
        0 8px 32px rgba(102, 126, 234, 0.6),
        0 0 0 4px rgba(102, 126, 234, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.saw-checkout-search-input::placeholder {
    color: rgba(148, 163, 184, 0.6);
    font-weight: 600;
}

/* ✅ BUTTON */
.saw-checkout-search-button {
    width: 100%;
    padding: 1.25rem 2rem;
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--text-primary);
    
    background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-hover) 100%);
    border: none;
    border-radius: 16px;
    cursor: pointer;
    
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    box-shadow: 
        0 6px 24px rgba(102, 126, 234, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    
    position: relative;
    overflow: hidden;
}

.saw-checkout-search-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.saw-checkout-search-button:hover::before {
    left: 100%;
}

.saw-checkout-search-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 36px rgba(102, 126, 234, 0.6);
}

.saw-checkout-button-icon {
    font-size: 1.5rem;
}

/* ✅ Helper text */
.saw-checkout-helper-text {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    font-size: 0.875rem;
    color: #93c5fd;
    text-align: center;
    line-height: 1.6;
}

/* ===================================
   RESULTS CARD
=================================== */
.saw-checkout-results-card {
    /* ✅ Glass morphism */
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.12) 0%,
        rgba(255, 255, 255, 0.06) 100%
    );
    backdrop-filter: blur(40px) saturate(180%);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 32px;
    padding: 2.5rem;
    
    box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    
    /* ✅ Hidden by default */
    display: none;
    
    /* ✅ Slide in animation */
    animation: slideInRight 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.saw-checkout-search-container.has-results .saw-checkout-results-card {
    display: block;
}

/* Status */
.saw-checkout-search-status {
    padding: 1.25rem 2rem;
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 2rem;
    display: none;
    backdrop-filter: blur(20px);
}

.saw-checkout-search-status.searching {
    display: block;
    background: rgba(59, 130, 246, 0.15);
    border: 2px solid rgba(59, 130, 246, 0.5);
    color: #93c5fd;
}

.saw-checkout-search-status.no-results {
    display: block;
    background: rgba(251, 191, 36, 0.15);
    border: 2px solid rgba(251, 191, 36, 0.5);
    color: #fcd34d;
}

.saw-checkout-search-status.error {
    display: block;
    background: rgba(239, 68, 68, 0.15);
    border: 2px solid rgba(239, 68, 68, 0.5);
    color: #fca5a5;
}

/* ✅ Results Header */
.saw-checkout-results-header {
    margin-bottom: 2rem;
}

.saw-checkout-results-title {
    font-size: 1.75rem;
    font-weight: 900;
    background: linear-gradient(135deg, #ffffff 0%, #94a3b8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
    letter-spacing: -0.03em;
}

.saw-checkout-results-count {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 600;
}

/* Results List */
.saw-checkout-search-results {
    display: grid;
    gap: 1rem;
    margin-bottom: 1.5rem;
    max-height: 400px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

/* Scrollbar styling */
.saw-checkout-search-results::-webkit-scrollbar {
    width: 6px;
}

.saw-checkout-search-results::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.saw-checkout-search-results::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.5);
    border-radius: 10px;
}

/* ✅ VISITOR CARD */
.saw-checkout-visitor-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.1) 0%,
        rgba(255, 255, 255, 0.05) 100%
    );
    backdrop-filter: blur(30px);
    border: 2px solid rgba(255, 255, 255, 0.15);
    border-radius: 16px;
    
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    box-shadow: 
        0 4px 16px rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

.saw-checkout-visitor-card:hover {
    border-color: rgba(102, 126, 234, 0.5);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    transform: translateY(-2px);
}

.saw-checkout-visitor-card.selected {
    background: linear-gradient(
        135deg,
        rgba(102, 126, 234, 0.25) 0%,
        rgba(118, 75, 162, 0.2) 100%
    );
    border-color: var(--theme-color);
    box-shadow: 
        0 8px 24px rgba(102, 126, 234, 0.5),
        0 0 0 3px rgba(102, 126, 234, 0.15);
}

.saw-checkout-visitor-checkbox {
    width: 24px;
    height: 24px;
    cursor: pointer;
    accent-color: var(--theme-color);
}

.saw-checkout-visitor-info {
    flex: 1;
}

.saw-checkout-visitor-name {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 0.375rem;
    letter-spacing: -0.02em;
}

.saw-checkout-visitor-company {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.75rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 0.375rem;
}

.saw-checkout-visitor-company.physical {
    background: rgba(139, 92, 246, 0.2);
    border-color: rgba(139, 92, 246, 0.4);
    color: #c4b5fd;
}

.saw-checkout-visitor-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8125rem;
    color: var(--text-muted);
    font-weight: 600;
}

.saw-checkout-visitor-meta span {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

/* ✅ CHECKOUT BUTTON */
.saw-checkout-complete-form {
    display: none;
}

.saw-checkout-complete-form .saw-checkout-search-button {
    background: linear-gradient(135deg, var(--color-success) 0%, #059669 100%);
    box-shadow: 
        0 8px 24px rgba(16, 185, 129, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.saw-checkout-complete-form .saw-checkout-search-button:hover {
    box-shadow: 0 12px 36px rgba(16, 185, 129, 0.6);
}

.saw-checkout-selected-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    background: white;
    color: var(--color-success);
    border-radius: 14px;
    padding: 0 10px;
    font-weight: 900;
    font-size: 1rem;
    margin-left: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* ===================================
   MOBILE
=================================== */
@media (max-width: 1023px) {
    .saw-checkout-search-container.has-results {
        grid-template-columns: 1fr;
        max-width: 600px;
    }
    
    .saw-checkout-search-card {
        padding: 2rem;
    }
    
    .saw-checkout-results-card {
        padding: 2rem;
    }
    
    .saw-checkout-search-title {
        font-size: 2.25rem;
    }
    
    .saw-checkout-search-input {
        font-size: 1.125rem;
        padding: 1.25rem 1.5rem;
    }
}
</style>

<div class="saw-search-aurora">
    <div class="saw-checkout-search-container" id="search-container">
        
        <!-- ===================================
             SEARCH CARD (LEFT/CENTER)
        =================================== -->
        <div class="saw-checkout-search-card">
            
            <!-- Header -->
<div class="saw-checkout-search-header">
    <div class="saw-checkout-search-icon">🔍</div>
    <h1 class="saw-checkout-search-title">Odhlášení</h1>
    <p class="saw-checkout-search-subtitle">Najděte svou návštěvu</p>
</div>
            
            <!-- Form -->
            <div class="saw-checkout-search-form">
                <div class="saw-checkout-form-group">
                    <label class="saw-checkout-form-label">Celé jméno</label>
                    <input type="text" 
                           id="checkout-search-fullname"
                           class="saw-checkout-search-input"
                           placeholder="Jan Novák"
                           autocomplete="off"
                           required
                           autofocus>
                </div>
                
                <button type="button" id="checkout-search-btn" class="saw-checkout-search-button">
                    <span class="saw-checkout-button-icon">🔍</span>
                    Vyhledat
                </button>
                
                <div class="saw-checkout-helper-text">
                    💡 Zadejte jméno a příjmení tak, jak jste se přihlásili
                </div>
            </div>
            
        </div>
        
        <!-- ===================================
             RESULTS CARD (RIGHT - hidden initially)
        =================================== -->
        <div class="saw-checkout-results-card">
            
            <!-- Status -->
            <div id="checkout-search-status" class="saw-checkout-search-status"></div>
            
            <!-- Results Header -->
            <div class="saw-checkout-results-header">
                <h2 class="saw-checkout-results-title">Nalezené návštěvy</h2>
                <p class="saw-checkout-results-count" id="results-count"></p>
            </div>
            
            <!-- Results List -->
            <div id="checkout-search-results" class="saw-checkout-search-results"></div>
            
            <!-- Checkout Button -->
            <form method="post" id="checkout-complete-form" class="saw-checkout-complete-form">
                <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
                <input type="hidden" name="terminal_action" value="checkout_complete">
                <input type="hidden" name="visitor_ids" id="checkout-selected-ids" value="">
                
                <button type="submit" class="saw-checkout-search-button">
                    <span class="saw-checkout-button-icon">✓</span>
                    Odhlásit
                    <span id="checkout-selected-count" class="saw-checkout-selected-count"></span>
                </button>
            </form>
            
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const selectedVisitors = new Set();
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const nonce = '<?php echo $search_nonce; ?>';
    const customerId = <?php echo intval($customer_id); ?>;
    const branchId = <?php echo intval($branch_id); ?>;
    
    $('#checkout-search-btn').on('click', performSearch);
    
    $('#checkout-search-fullname').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            performSearch();
        }
    });
    
    function performSearch() {
        const fullName = $('#checkout-search-fullname').val().trim();
        
        if (!fullName || fullName.indexOf(' ') === -1) {
            showStatus('error', '⚠️ Zadejte celé jméno i příjmení');
            return;
        }
        
        const parts = fullName.split(/\s+/);
        const firstName = parts[0];
        const lastName = parts.slice(1).join(' ');
        
        showStatus('searching', '🔍 Vyhledávám...');
        $('#checkout-search-results').empty();
        $('#checkout-complete-form').hide();
        selectedVisitors.clear();
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'saw_terminal_search_by_name',
                nonce: nonce,
                first_name: firstName,
                last_name: lastName,
                customer_id: customerId,
                branch_id: branchId
            },
            success: function(response) {
                if (response.success && response.data?.visitors?.length > 0) {
                    // ✅ ANIMACE: Přidej třídu pro 2-column layout
                    $('#search-container').addClass('has-results');
                    
                    renderResults(response.data.visitors);
                    hideStatus();
                    
                    const count = response.data.visitors.length;
                    $('#results-count').text(count + ' ' + (count === 1 ? 'návštěva' : count < 5 ? 'návštěvy' : 'návštěv'));
                } else {
                    showStatus('no-results', '❌ Žádná aktivní návštěva nenalezena');
                    $('#search-container').removeClass('has-results');
                }
            },
            error: function() {
                showStatus('error', '⚠️ Chyba při vyhledávání');
                $('#search-container').removeClass('has-results');
            }
        });
    }
    
    function renderResults(visitors) {
        const $results = $('#checkout-search-results');
        $results.empty();
        
        visitors.forEach(function(v) {
            const company = v.company_name 
                ? `<div class="saw-checkout-visitor-company">🏢 ${v.company_name}</div>`
                : `<div class="saw-checkout-visitor-company physical">👤 Fyzická osoba</div>`;
            
            $results.append(`
                <div class="saw-checkout-visitor-card" data-id="${v.id}">
                    <input type="checkbox" class="saw-checkout-visitor-checkbox" data-id="${v.id}">
                    <div class="saw-checkout-visitor-info">
                        <div class="saw-checkout-visitor-name">${v.first_name} ${v.last_name}</div>
                        ${company}
                        <div class="saw-checkout-visitor-meta">
                            <span>⏰ ${v.checkin_time}</span>
                            <span>⏱️ ${v.minutes_inside} min</span>
                        </div>
                    </div>
                </div>
            `);
        });
        
        if (visitors.length === 1) {
            $('.saw-checkout-visitor-checkbox').prop('checked', true).trigger('change');
        }
    }
    
    function showStatus(type, msg) {
        $('#checkout-search-status')
            .removeClass('searching no-results error')
            .addClass(type)
            .text(msg)
            .show();
    }
    
    function hideStatus() {
        $('#checkout-search-status').hide();
    }
    
    $(document).on('change', '.saw-checkout-visitor-checkbox', function() {
        const id = $(this).data('id');
        const $card = $(this).closest('.saw-checkout-visitor-card');
        
        if ($(this).is(':checked')) {
            selectedVisitors.add(id);
            $card.addClass('selected');
        } else {
            selectedVisitors.delete(id);
            $card.removeClass('selected');
        }
        
        updateButton();
    });
    
    $(document).on('click', '.saw-checkout-visitor-card', function(e) {
        if (!$(e.target).is('.saw-checkout-visitor-checkbox')) {
            $(this).find('.saw-checkout-visitor-checkbox').click();
        }
    });
    
    function updateButton() {
        if (selectedVisitors.size > 0) {
            $('#checkout-complete-form').show();
            $('#checkout-selected-count').text(selectedVisitors.size);
            $('#checkout-selected-ids').val(Array.from(selectedVisitors).join(','));
        } else {
            $('#checkout-complete-form').hide();
        }
    }
});
</script>