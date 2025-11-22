<?php
/**
 * Companies Merge Modal Template
 * @version 2.0.0 - REFACTORED: JavaScript moved to assets
 */

if (!defined('ABSPATH')) {
    exit;
}
?>


<!-- ✅ OVERLAY BEZ onclick -->
<div class="saw-modal-overlay" id="sawMergeModalOverlay">
    <!-- ✅ MODAL s onclick pro zastavení propagace -->
    <div class="saw-merge-modal" 
         onclick="event.stopPropagation()"
         data-master-id="<?php echo intval($master['id']); ?>">
        
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

<!-- JavaScript moved to assets/js/modules/companies/companies-merge.js -->
<!-- Asset is enqueued automatically by SAW_Asset_Loader -->