<?php
/**
 * Terminal Training Step - Additional Information
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - Fullscreen with documents
 */

if (!defined('ABSPATH')) {
    exit;
}

error_log("[ADDITIONAL.PHP] Template started");

// Get data from flow
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;

error_log("[ADDITIONAL.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[ADDITIONAL.PHP] Additional text: " . (isset($additional_text) ? substr($additional_text, 0, 100) : 'NOT SET'));
error_log("[ADDITIONAL.PHP] Documents: " . (isset($documents) ? count($documents) : 0));

// Check if additional text exists
$has_content = !empty($additional_text);

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_additional FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_additional);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'title' => 'Další informace',
        'subtitle' => 'Doplňující informace pro návštěvníky',
        'confirm' => 'Potvrzuji, že jsem si přečetl/a další informace',
        'continue' => 'Pokračovat',
        'no_content' => 'Další informace nejsou k dispozici',
    ),
    'en' => array(
        'title' => 'Additional Information',
        'subtitle' => 'Supplementary information for visitors',
        'confirm' => 'I confirm that I have read the additional information',
        'continue' => 'Continue',
        'no_content' => 'Additional information not available',
    ),
    'sk' => array(
        'title' => 'Ďalšie informácie',
        'subtitle' => 'Doplňujúce informácie pre návštevníkov',
        'confirm' => 'Potvrdzujem, že som si prečítal/a ďalšie informácie',
        'continue' => 'Pokračovať',
        'no_content' => 'Ďalšie informácie nie sú k dispozícii',
    ),
    'uk' => array(
        'title' => 'Додаткова інформація',
        'subtitle' => 'Додаткова інформація для відвідувачів',
        'confirm' => 'Підтверджую, що прочитав додаткову інформацію',
        'continue' => 'Продовжити',
        'no_content' => 'Додаткова інформація недоступна',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #f7fafc; z-index: 9999; overflow: hidden;">
    
    <!-- Header -->
    <div style="background: white; border-bottom: 2px solid #e2e8f0; padding: 1.5rem 2rem;">
        <h2 style="margin: 0; font-size: 1.75rem; color: #2d3748; font-weight: 700;">
            ℹ️ <?php echo esc_html($t['title']); ?>
        </h2>
        <p style="margin: 0.5rem 0 0 0; color: #718096;">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <?php if (!$has_content): ?>
    <!-- Error: No content -->
    <div style="padding: 2rem; text-align: center;">
        <div style="background: #fffaf0; border: 2px solid #f6ad55; border-radius: 12px; padding: 2rem; max-width: 600px; margin: 0 auto;">
            <p style="margin: 0; font-size: 1.25rem; color: #c05621; font-weight: 600;">
                ℹ️ <?php echo esc_html($t['no_content']); ?>
            </p>
        </div>
        
        <form method="POST" style="margin-top: 2rem;">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_additional">
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
    </div>
    
    <?php else: ?>
    
    <!-- Two column layout -->
    <div style="display: flex; height: calc(100vh - 120px); overflow: hidden;">
        
        <!-- LEFT: Content -->
        <div style="flex: 1; padding: 2rem; overflow-y: auto; background: white;">
            <div style="max-width: 900px; margin: 0 auto;">
                <div style="color: #2d3748; line-height: 1.8; font-size: 1.05rem;">
                    <?php echo wp_kses_post($additional_text); ?>
                </div>
            </div>
        </div>
        
        <!-- RIGHT: Documents -->
        <div style="width: 350px; background: #f7fafc; border-left: 2px solid #e2e8f0; padding: 2rem; overflow-y: auto;">
            <h3 style="margin: 0 0 1.5rem 0; font-size: 1.125rem; color: #2d3748; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.5rem;">📄</span>
                Dokumenty
            </h3>
            
            <?php if (empty($documents)): ?>
                <p style="color: #718096; font-style: italic;">
                    Žádné dokumenty nejsou k dispozici
                </p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($documents as $doc): ?>
                    <a href="<?php echo esc_url(content_url() . '/uploads' . $doc['file_path']); ?>" 
                       target="_blank"
                       style="display: block; padding: 1rem; background: white; border: 2px solid #e2e8f0; border-radius: 8px; text-decoration: none; transition: all 0.2s; color: inherit;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="font-size: 2rem; flex-shrink: 0;">
                                <?php 
                                $ext = pathinfo($doc['file_name'], PATHINFO_EXTENSION);
                                $icons = [
                                    'pdf' => '📕',
                                    'doc' => '📘', 'docx' => '📘',
                                    'xls' => '📗', 'xlsx' => '📗',
                                    'ppt' => '📙', 'pptx' => '📙',
                                    'txt' => '📄'
                                ];
                                echo isset($icons[$ext]) ? $icons[$ext] : '📄';
                                ?>
                            </span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 600; color: #2d3748; font-size: 0.95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo esc_html($doc['file_name']); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #718096; margin-top: 0.25rem;">
                                    <?php echo size_format($doc['file_size']); ?>
                                </div>
                            </div>
                            <span style="font-size: 1.25rem; color: #667eea;">→</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <!-- Bottom bar with checkbox and button -->
    <div style="position: absolute; bottom: 0; left: 0; right: 0; background: white; border-top: 2px solid #e2e8f0; padding: 1.5rem 2rem;">
        <form method="POST" id="training-additional-form" style="max-width: 900px; margin: 0 auto; display: flex; align-items: center; gap: 2rem;">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_additional">
            
            <?php if (!$completed): ?>
            <label style="flex: 1; display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: 1rem; background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 8px;">
                <input type="checkbox" 
                       name="additional_confirmed" 
                       id="additional-confirmed" 
                       value="1"
                       style="width: 24px; height: 24px; cursor: pointer;"
                       required>
                <span style="color: #0369a1; font-weight: 600; font-size: 1.05rem;">
                    <?php echo esc_html($t['confirm']); ?>
                </span>
            </label>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-terminal-btn saw-terminal-btn-success <?php echo !$completed ? 'saw-terminal-btn-disabled' : ''; ?>"
                    id="continue-btn"
                    style="<?php echo !$completed ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?> width: auto; padding: 1rem 3rem; font-size: 1.125rem;"
                    <?php echo !$completed ? 'disabled' : ''; ?>>
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
    </div>
    
    <?php endif; ?>
    
</div>

<?php if ($has_content): ?>
<script>
// Enable continue button when checkbox is checked
jQuery(document).ready(function($) {
    $('#additional-confirmed').on('change', function() {
        if ($(this).is(':checked')) {
            $('#continue-btn')
                .prop('disabled', false)
                .removeClass('saw-terminal-btn-disabled')
                .css({
                    'opacity': '1',
                    'cursor': 'pointer'
                });
        } else {
            $('#continue-btn')
                .prop('disabled', true)
                .addClass('saw-terminal-btn-disabled')
                .css({
                    'opacity': '0.5',
                    'cursor': 'not-allowed'
                });
        }
    });
});
</script>
<?php endif; ?>

<?php
error_log("[ADDITIONAL.PHP] Template finished");
?>