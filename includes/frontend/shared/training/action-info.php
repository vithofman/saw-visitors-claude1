<?php
/**
 * Shared Training Step - Action-Specific Information
 * 
 * Displays action-specific instructions and documents.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Context detection (same as other steps)
$context = 'terminal';
if (isset($is_invitation) && $is_invitation === true) {
    $context = 'invitation';
}
if (isset($is_visitor_info) && $is_visitor_info === true) {
    $context = 'visitor_info';
}

// Get context settings
$context_settings = array(
    'terminal' => array(
        'nonce_name' => 'saw_terminal_step',
        'nonce_field' => 'terminal_nonce',
        'action_name' => 'terminal_action',
        'complete_action' => 'complete_training_action_info',
    ),
    'invitation' => array(
        'nonce_name' => 'saw_invitation_step',
        'nonce_field' => 'invitation_nonce',
        'action_name' => 'invitation_action',
        'complete_action' => 'complete_training',
    ),
    'visitor_info' => array(
        'nonce_name' => 'saw_visitor_info_step',
        'nonce_field' => 'visitor_info_nonce',
        'action_name' => 'visitor_info_action',
        'complete_action' => 'complete_training_action_info',
    ),
);

$ctx = $context_settings[$context];
$free_mode = ($context === 'invitation');

// Get data
$action_name = $action_name ?? '';
$content_text = $content_text ?? '';
$documents = $documents ?? [];
$lang = $lang ?? 'cs';

// Translations
$t = array(
    'cs' => array(
        'title' => 'Specifické informace pro akci',
        'subtitle' => 'Důležité pokyny pro tuto konkrétní akci',
        'documents_title' => 'Dokumenty ke stažení',
        'confirm' => 'Potvrzuji, že jsem se seznámil/a s pokyny',
        'continue' => 'Pokračovat',
    ),
    'en' => array(
        'title' => 'Action-Specific Information',
        'subtitle' => 'Important instructions for this specific action',
        'documents_title' => 'Documents to download',
        'confirm' => 'I confirm that I have read the instructions',
        'continue' => 'Continue',
    ),
);

$texts = $t[$lang] ?? $t['cs'];
?>

<div class="saw-training-step saw-training-action-info">
    
    <!-- Header -->
    <div class="saw-training-header">
        <div class="saw-training-icon">🎯</div>
        <h1 class="saw-training-title">
            <?php echo esc_html($texts['title']); ?>
        </h1>
        <?php if (!empty($action_name)): ?>
            <h2 class="saw-training-action-name">
                "<?php echo esc_html($action_name); ?>"
            </h2>
        <?php endif; ?>
        <p class="saw-training-subtitle">
            <?php echo esc_html($texts['subtitle']); ?>
        </p>
    </div>
    
    <!-- Content Card -->
    <div class="saw-card">
        <div class="saw-card-body">
            
            <!-- Main Content -->
            <?php if (!empty($content_text)): ?>
                <div class="saw-action-content">
                    <?php echo wp_kses_post($content_text); ?>
                </div>
            <?php endif; ?>
            
            <!-- Documents -->
            <?php if (!empty($documents)): ?>
                <div class="saw-action-documents" style="margin-top: 24px;">
                    <h3 class="saw-docs-title">
                        📄 <?php echo esc_html($texts['documents_title']); ?>
                    </h3>
                    <div class="saw-docs-grid">
                        <?php foreach ($documents as $doc): 
                            $upload_dir = wp_upload_dir();
                            $file_url = $upload_dir['baseurl'] . '/' . $doc['file_path'];
                        ?>
                            <a href="<?php echo esc_url($file_url); ?>" 
                               class="saw-doc-card" 
                               target="_blank"
                               download>
                                <span class="saw-doc-icon">📄</span>
                                <span class="saw-doc-name"><?php echo esc_html($doc['file_name']); ?></span>
                                <span class="saw-doc-size"><?php echo esc_html(size_format($doc['file_size'])); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <!-- Confirmation Form -->
    <form method="post" class="saw-training-form">
        <?php wp_nonce_field($ctx['nonce_name'], $ctx['nonce_field']); ?>
        <input type="hidden" name="<?php echo esc_attr($ctx['action_name']); ?>" value="<?php echo esc_attr($ctx['complete_action']); ?>">
        
        <div class="saw-form-actions">
            <?php if (!$free_mode): ?>
                <label class="saw-checkbox-large">
                    <input type="checkbox" name="confirmed" id="action-info-confirm" required>
                    <span class="saw-checkbox-label">
                        <?php echo esc_html($texts['confirm']); ?>
                    </span>
                </label>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-btn saw-btn-primary saw-btn-lg"
                    <?php echo !$free_mode ? 'disabled' : ''; ?>>
                <?php echo esc_html($texts['continue']); ?>
                <span class="saw-btn-arrow">→</span>
            </button>
        </div>
    </form>
    
</div>

<?php if (!$free_mode): ?>
<script>
document.getElementById('action-info-confirm').addEventListener('change', function() {
    document.querySelector('.saw-training-form button[type="submit"]').disabled = !this.checked;
});
</script>
<?php endif; ?>

