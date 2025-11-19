<?php
/**
 * Companies Merge Modal Template
 * @version 1.1.0 - FIXED: Odstranění onclick z overlay + správná ajaxurl
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

<style>
:root {
    --p307-primary: #005A8C;
    --p307-accent: #0077B5;
    --p307-dark: #1a1a1a;
    --p307-gray: #f4f6f8;
    --p307-border: #dce1e5;
}
.saw-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.saw-merge-modal {
    background: white;
    border-radius: 8px;
    max-width: 700px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
}
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.saw-modal-header {
    background: var(--p307-primary);
    color: white;
    padding: 24px;
    border-radius: 8px 8px 0 0;
    position: relative;
}
.saw-modal-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 8px;
    background-image: repeating-linear-gradient(
        -45deg,
        white,
        white 10px,
        transparent 10px,
        transparent 20px
    );
}
.saw-modal-header h2 {
    font-family: 'Oswald', sans-serif;
    font-size: 24px;
    font-weight: 700;
    text-transform: uppercase;
    margin: 0 0 8px 0;
    letter-spacing: 1px;
}
.saw-modal-header p {
    margin: 0;
    font-size: 14px;
    opacity: 0.9;
}
.saw-modal-header strong {
    font-weight: 700;
    font-size: 16px;
}
.saw-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s;
}
.saw-modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}
.saw-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}
.saw-help-text {
    background: #fff9e6;
    border-left: 4px solid #ffc107;
    padding: 12px 16px;
    margin: 0 0 20px 0;
    font-size: 14px;
    color: #666;
    border-radius: 4px;
}
.saw-no-duplicates {
    text-align: center;
    padding: 40px 20px;
    color: #28a745;
    font-size: 16px;
    font-weight: 600;
}
.saw-duplicate-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
}
.saw-duplicate-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border: 2px solid var(--p307-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}
.saw-duplicate-item:hover {
    border-color: var(--p307-accent);
    background: #f0f8ff;
    transform: translateX(4px);
}
.saw-duplicate-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-top: 2px;
    cursor: pointer;
    flex-shrink: 0;
}
.saw-dup-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.saw-dup-info strong {
    font-size: 16px;
    color: var(--p307-dark);
    font-weight: 700;
}
.saw-dup-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.saw-similarity-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid #81c784;
}
.saw-visit-count {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--p307-gray);
    color: #666;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.saw-ico-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #999;
    font-size: 12px;
}
.saw-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 2px solid var(--p307-gray);
}
.saw-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Roboto', sans-serif;
    letter-spacing: 0.5px;
}
.saw-btn-primary {
    background: var(--p307-accent);
    color: white;
}
.saw-btn-primary:hover {
    background: var(--p307-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 119, 181, 0.3);
}
.saw-btn-primary:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}
.saw-btn-secondary {
    background: white;
    color: #666;
    border: 2px solid var(--p307-border);
}
.saw-btn-secondary:hover {
    background: var(--p307-gray);
    border-color: #999;
}
.saw-merge-warning {
    background: #fff3e0;
    border: 2px solid #ffb74d;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.saw-merge-warning-icon {
    font-size: 24px;
    flex-shrink: 0;
}
.saw-merge-warning-text {
    flex: 1;
}
.saw-merge-warning-text strong {
    display: block;
    color: #e65100;
    margin-bottom: 4px;
    font-size: 14px;
}
.saw-merge-warning-text p {
    margin: 0;
    font-size: 13px;
    color: #666;
}
@media (max-width: 600px) {
    .saw-modal-overlay {
        padding: 10px;
    }
    .saw-merge-modal {
        max-height: 95vh;
    }
    .saw-modal-header {
        padding: 20px;
    }
    .saw-modal-header h2 {
        font-size: 20px;
    }
    .saw-modal-body {
        padding: 16px;
    }
    .saw-duplicate-item {
        padding: 12px;
    }
    .saw-dup-info strong {
        font-size: 14px;
    }
    .saw-modal-actions {
        flex-direction: column;
    }
    .saw-btn {
        width: 100%;
    }
}
</style>

<!-- ✅ OVERLAY BEZ onclick -->
<div class="saw-modal-overlay" id="sawMergeModalOverlay">
    <!-- ✅ MODAL s onclick pro zastavení propagace -->
    <div class="saw-merge-modal" onclick="event.stopPropagation()">
        
        <div class="saw-modal-header">
            <button class="saw-modal-close" onclick="closeMergeModal()" type="button">×</button>
            <h2>🔗 Sloučit duplicitní firmy</h2>
            <p>Hlavní firma: <strong><?php echo esc_html($master['name']); ?></strong></p>
        </div>
        
        <div class="saw-modal-body">
            
            <?php if (!empty($suggestions)): ?>
                
                <div class="saw-help-text">
                    💡 <strong>Našli jsme <?php echo count($suggestions); ?> podobných firem.</strong><br>
                    Vyberte firmy, které chcete sloučit pod hlavní záznam. Všechny návštěvy budou přesunuty.
                </div>
                
                <div class="saw-merge-warning">
                    <div class="saw-merge-warning-icon">⚠️</div>
                    <div class="saw-merge-warning-text">
                        <strong>Tato akce je nevratná!</strong>
                        <p>Vybrané firmy budou trvale smazány a všechny jejich návštěvy budou přesunuty pod hlavní firmu.</p>
                    </div>
                </div>
                
                <div class="saw-duplicate-list">
                    <?php foreach ($suggestions as $company): ?>
                    <label class="saw-duplicate-item">
                        <input type="checkbox" 
                               name="duplicate_ids[]" 
                               value="<?php echo intval($company['id']); ?>"
                               onchange="updateMergeButton()">
                        
                        <div class="saw-dup-info">
                            <strong><?php echo esc_html($company['name']); ?></strong>
                            
                            <div class="saw-dup-meta">
                                <span class="saw-similarity-badge">
                                    ✓ <?php echo intval($company['similarity']); ?>% shoda
                                </span>
                                
                                <span class="saw-visit-count">
                                    📋 <?php echo intval($company['visit_count']); ?> 
                                    <?php echo $company['visit_count'] == 1 ? 'návštěva' : 'návštěv'; ?>
                                </span>
                                
                                <?php if (!empty($company['ico'])): ?>
                                <span class="saw-ico-badge">
                                    🏢 IČO: <?php echo esc_html($company['ico']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="saw-modal-actions">
                    <button class="saw-btn saw-btn-secondary" onclick="closeMergeModal()" type="button">
                        Zrušit
                    </button>
                    <button class="saw-btn saw-btn-primary" 
                            id="sawMergeButton"
                            onclick="confirmMerge()" 
                            type="button"
                            disabled>
                        Sloučit vybrané
                    </button>
                </div>
                
            <?php else: ?>
                
                <div class="saw-no-duplicates">
                    ✓ Nebyly nalezeny žádné podobné firmy k sloučení
                </div>
                
                <div class="saw-modal-actions">
                    <button class="saw-btn saw-btn-secondary" onclick="closeMergeModal()" type="button">
                        Zavřít
                    </button>
                </div>
                
            <?php endif; ?>
            
        </div>
        
    </div>
</div>

<script>
function closeMergeModal() {
    const overlay = document.getElementById('sawMergeModalOverlay');
    if (overlay) {
        overlay.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => overlay.remove(), 300);
    }
}

// ✅ KLIK NA OVERLAY ZAVŘE MODAL
document.getElementById('sawMergeModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMergeModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMergeModal();
    }
});

function updateMergeButton() {
    const selected = document.querySelectorAll('input[name="duplicate_ids[]"]:checked');
    const button = document.getElementById('sawMergeButton');
    
    if (button) {
        button.disabled = selected.length === 0;
        
        if (selected.length > 0) {
            button.textContent = `Sloučit ${selected.length} ${selected.length === 1 ? 'firmu' : selected.length < 5 ? 'firmy' : 'firem'}`;
        } else {
            button.textContent = 'Sloučit vybrané';
        }
    }
}

function confirmMerge() {
    const selected = document.querySelectorAll('input[name="duplicate_ids[]"]:checked');
    
    if (selected.length === 0) {
        alert('Vyberte alespoň jednu firmu ke sloučení');
        return;
    }
    
    const count = selected.length;
    const totalVisits = Array.from(selected).reduce((sum, checkbox) => {
        const visitCount = checkbox.closest('.saw-duplicate-item')
            .querySelector('.saw-visit-count').textContent.match(/\d+/)[0];
        return sum + parseInt(visitCount);
    }, 0);
    
    const message = `Opravdu chcete sloučit ${count} ${count === 1 ? 'firmu' : count < 5 ? 'firmy' : 'firem'}?\n\n` +
                    `Bude přesunuto celkem ${totalVisits} návštěv.\n\n` +
                    `TATO AKCE JE NEVRATNÁ!`;
    
    if (!confirm(message)) {
        return;
    }
    
    const button = document.getElementById('sawMergeButton');
    button.disabled = true;
    button.textContent = 'Slučuji...';
    
    const duplicateIds = Array.from(selected).map(cb => cb.value);
    
    // ✅ SPRÁVNÁ CESTA
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'saw_merge_companies',
            nonce: '<?php echo wp_create_nonce('saw_admin_nonce'); ?>',
            master_id: <?php echo intval($master['id']); ?>,
            duplicate_ids: JSON.stringify(duplicateIds)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ ' + data.data.message);
            closeMergeModal();
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert('❌ Chyba: ' + (data.data.message || 'Neznámá chyba'));
            button.disabled = false;
            button.textContent = `Sloučit ${count} ${count === 1 ? 'firmu' : count < 5 ? 'firmy' : 'firem'}`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Chyba při sloučení');
        button.disabled = false;
        button.textContent = `Sloučit ${count} ${count === 1 ? 'firmu' : count < 5 ? 'firmy' : 'firem'}`;
    });
}

const style = document.createElement('style');
style.textContent = '@keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }';
document.head.appendChild(style);
</script>