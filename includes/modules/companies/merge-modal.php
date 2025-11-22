<?php
/**
 * Companies Merge Modal Template
 * @version 1.1.0 - FIXED: Odstranění onclick z overlay + správná ajaxurl
 */

if (!defined('ABSPATH')) {
    exit;
}
?>


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
            nonce: '<?php echo wp_create_nonce('saw_ajax_nonce'); ?>',
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

// fadeOut animation is now in CSS file
</script>