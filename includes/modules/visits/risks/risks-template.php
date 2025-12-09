<?php
/**
 * Risks Editor Template
 * 
 * Compact editor for visitor risk information.
 * 
 * @package SAW_Visitors
 * @since 5.1.0
 * @version 5.1.4
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$visit_id = isset($visit_id) ? intval($visit_id) : 0;

// Fetch visit
$visit = $wpdb->get_row($wpdb->prepare("
    SELECT v.*, c.name as customer_name, b.name as branch_name, comp.name as company_name
    FROM {$wpdb->prefix}saw_visits v
    LEFT JOIN {$wpdb->prefix}saw_customers c ON v.customer_id = c.id
    LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
    LEFT JOIN {$wpdb->prefix}saw_companies comp ON v.company_id = comp.id
    WHERE v.id = %d
", $visit_id), ARRAY_A);

if (!$visit) {
    wp_die('Návštěva nenalezena');
}

// Get risks text - use correct column name from visits table
$risks_text = $visit['visitor_risks_text'] ?? $visit['risks_text'] ?? '';

// Get documents from invitation_materials table (existing table)
$documents = [];
$materials_table = $wpdb->prefix . 'saw_visit_invitation_materials';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$materials_table}'");

if ($table_exists) {
    $documents = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$materials_table}
        WHERE visit_id = %d AND material_type = 'document'
        ORDER BY uploaded_at DESC
    ", $visit_id), ARRAY_A);
    
    // Prepare URLs
    $upload_dir = wp_upload_dir();
    foreach ($documents as &$doc) {
        $doc['file_url'] = $upload_dir['baseurl'] . '/' . $doc['file_path'];
    }
    unset($doc);
}

// Display name
$display_name = !empty($visit['company_name']) ? $visit['company_name'] : '';
if (empty($display_name)) {
    $first_visitor = $wpdb->get_row($wpdb->prepare("
        SELECT first_name, last_name FROM {$wpdb->prefix}saw_visitors
        WHERE visit_id = %d ORDER BY id ASC LIMIT 1
    ", $visit_id), ARRAY_A);
    if ($first_visitor) {
        $display_name = trim($first_visitor['first_name'] . ' ' . $first_visitor['last_name']);
    }
}

$date_from = !empty($visit['planned_date_from']) ? date_i18n('d.m.Y', strtotime($visit['planned_date_from'])) : '';
$back_url = home_url('/admin/visits/' . $visit_id . '/');

$type_labels = ['planned' => 'PLÁNOVANÁ', 'walk_in' => 'WALK-IN'];
$status_labels = ['draft' => 'Koncept', 'pending' => 'Čeká', 'confirmed' => 'Potvrzeno', 'in_progress' => 'Probíhá', 'completed' => 'Dokončeno', 'cancelled' => 'Zrušeno'];

$visit_type = $visit['visit_type'] ?? 'planned';
$visit_status = $visit['status'] ?? 'draft';
$css_version = time();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Editace rizik - <?php echo esc_html($display_name); ?></title>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>includes/modules/visits/risks/risks.css?v=<?php echo $css_version; ?>">
    <style id="scroll-fix">
        html, body { overflow: visible !important; overflow-y: auto !important; height: auto !important; min-height: 100vh !important; }
        body.saw-risks-editor-page { overflow: visible !important; overflow-y: auto !important; background: #f7fafc !important; }
        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }
    </style>
</head>
<body class="saw-risks-editor-page">

<div class="saw-risks-wrapper">
    
    <!-- COMPACT HEADER -->
    <header class="saw-risks-header">
        <a href="<?php echo esc_url($back_url); ?>" class="saw-risks-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Zpět
        </a>
        <h1 class="saw-risks-title">⚠️ Editace rizik</h1>
        <div class="saw-risks-header-meta">
            <?php if (!empty($visit['company_name'])): ?>🏢<?php else: ?>👤<?php endif; ?>
            <?php echo esc_html($display_name); ?>
            <?php if ($date_from): ?> · <?php echo esc_html($date_from); ?><?php endif; ?>
        </div>
    </header>
    
    <!-- MAIN -->
    <main class="saw-risks-main">
        <form id="saw-risks-form" method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field('saw_risks_edit', 'risks_nonce'); ?>
            <input type="hidden" name="action" value="save_risks">
            <input type="hidden" name="visit_id" value="<?php echo esc_attr($visit_id); ?>">
            <input type="hidden" name="deleted_documents" id="deleted-documents" value="">
            
            <!-- TEXT EDITOR -->
            <div class="saw-risks-card">
                <div class="saw-risks-card-header">
                    <span class="saw-risks-card-icon">📝</span>
                    <div>
                        <h2>Popis rizik</h2>
                        <p>Informace o rizicích, která návštěvník přináší nebo kterým může být vystaven.</p>
                    </div>
                </div>
                <div class="saw-risks-card-body">
                    <div class="saw-risks-editor">
                        <?php 
                        wp_editor($risks_text, 'visitor_risks_text', [
                            'textarea_name' => 'visitor_risks_text',
                            'textarea_rows' => 10,
                            'media_buttons' => true,
                            'teeny' => false,
                            'quicktags' => true,
                            'tinymce' => [
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink',
                                'toolbar2' => '',
                            ],
                        ]);
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- DOCUMENTS -->
            <div class="saw-risks-card">
                <div class="saw-risks-card-header">
                    <span class="saw-risks-card-icon">📎</span>
                    <div>
                        <h2>Dokumenty</h2>
                        <p>Bezpečnostní listy, certifikáty, OOPP požadavky.</p>
                    </div>
                </div>
                <div class="saw-risks-card-body">
                    
                    <!-- Dropzone -->
                    <div class="saw-risks-dropzone" id="risks-dropzone">
                        <div class="saw-risks-dropzone-inner">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <span>Přetáhněte soubory nebo <label class="saw-risks-dropzone-link">vyberte<input type="file" name="risk_documents[]" id="risk-documents-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display:none;"></label></span>
                        </div>
                    </div>
                    
                    <!-- File List -->
                    <div class="saw-risks-files" id="risks-files-list">
                        <?php foreach ($documents as $doc): ?>
                        <div class="saw-risks-file" data-id="<?php echo esc_attr($doc['id']); ?>">
                            <span class="saw-risks-file-icon"><?php
                                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                echo $ext === 'pdf' ? '📕' : (in_array($ext, ['doc','docx']) ? '📘' : (in_array($ext, ['xls','xlsx']) ? '📗' : '📄'));
                            ?></span>
                            <span class="saw-risks-file-name"><?php echo esc_html($doc['file_name']); ?></span>
                            <a href="<?php echo esc_url($doc['file_url']); ?>" target="_blank" class="saw-risks-file-btn" title="Zobrazit">👁</a>
                            <button type="button" class="saw-risks-file-btn saw-risks-file-btn-del" onclick="deleteFile(<?php echo esc_attr($doc['id']); ?>, this)" title="Smazat">✕</button>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($documents)): ?>
                        <div class="saw-risks-files-empty" id="risks-files-empty">Žádné dokumenty</div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
            
            <!-- ACTIONS -->
            <div class="saw-risks-actions">
                <a href="<?php echo esc_url($back_url); ?>" class="saw-risks-btn saw-risks-btn-cancel">Zrušit</a>
                <button type="submit" class="saw-risks-btn saw-risks-btn-save" id="risks-submit-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20,6 9,17 4,12"/></svg>
                    Uložit
                </button>
            </div>
            
        </form>
    </main>
    
</div>

<!-- Loading -->
<div class="saw-risks-loading" id="risks-loading" style="display:none;">
    <div class="saw-risks-spinner"></div>
</div>

<!-- Toast -->
<div class="saw-risks-toast" id="risks-toast"></div>

<script>
(function() {
    const form = document.getElementById('saw-risks-form');
    const dropzone = document.getElementById('risks-dropzone');
    const fileInput = document.getElementById('risk-documents-input');
    const filesList = document.getElementById('risks-files-list');
    const filesEmpty = document.getElementById('risks-files-empty');
    const loading = document.getElementById('risks-loading');
    const toast = document.getElementById('risks-toast');
    const deletedInput = document.getElementById('deleted-documents');
    
    let pendingFiles = [];
    let deletedIds = [];
    
    // Drag & Drop
    ['dragenter','dragover','dragleave','drop'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); ev.stopPropagation(); }));
    ['dragenter','dragover'].forEach(e => dropzone.addEventListener(e, () => dropzone.classList.add('dragover')));
    ['dragleave','drop'].forEach(e => dropzone.addEventListener(e, () => dropzone.classList.remove('dragover')));
    dropzone.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
    fileInput.addEventListener('change', e => handleFiles(e.target.files));
    
    function handleFiles(files) {
        Array.from(files).forEach(file => {
            if (file.size > 10*1024*1024) { showToast('Max 10 MB: ' + file.name, 'error'); return; }
            pendingFiles.push(file);
            addFileUI(file);
        });
        updateEmpty();
    }
    
    function addFileUI(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        const icon = ext === 'pdf' ? '📕' : (['doc','docx'].includes(ext) ? '📘' : (['xls','xlsx'].includes(ext) ? '📗' : '📄'));
        const div = document.createElement('div');
        div.className = 'saw-risks-file saw-risks-file-pending';
        div.innerHTML = `<span class="saw-risks-file-icon">${icon}</span><span class="saw-risks-file-name">${file.name} <small>(čeká)</small></span><button type="button" class="saw-risks-file-btn saw-risks-file-btn-del">✕</button>`;
        div.querySelector('button').onclick = () => { pendingFiles.splice(pendingFiles.indexOf(file), 1); div.remove(); updateEmpty(); };
        if (filesEmpty) filesList.insertBefore(div, filesEmpty); else filesList.appendChild(div);
    }
    
    function updateEmpty() {
        if (filesEmpty) filesEmpty.style.display = filesList.querySelectorAll('.saw-risks-file').length ? 'none' : 'block';
    }
    
    window.deleteFile = function(id, btn) {
        if (!confirm('Smazat dokument?')) return;
        deletedIds.push(id);
        deletedInput.value = deletedIds.join(',');
        btn.closest('.saw-risks-file').style.opacity = '0.4';
        btn.disabled = true;
    };
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('visitor_risks_text')) tinyMCE.get('visitor_risks_text').save();
        loading.style.display = 'flex';
        
        try {
            const formData = new FormData(form);
            pendingFiles.forEach(f => formData.append('risk_documents[]', f));
            formData.append('action', 'saw_save_visit_risks');
            
            const resp = await fetch(ajaxurl, { method: 'POST', body: formData, credentials: 'same-origin' });
            const result = await resp.json();
            
            if (result.success) {
                showToast('Uloženo', 'success');
                setTimeout(() => window.location.href = '<?php echo esc_url($back_url); ?>', 800);
            } else {
                throw new Error(result.data || 'Chyba');
            }
        } catch (err) {
            showToast(err.message, 'error');
            loading.style.display = 'none';
        }
    });
    
    function showToast(msg, type) {
        toast.textContent = msg;
        toast.className = 'saw-risks-toast saw-risks-toast-' + type + ' saw-risks-toast-show';
        setTimeout(() => toast.classList.remove('saw-risks-toast-show'), 3000);
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>