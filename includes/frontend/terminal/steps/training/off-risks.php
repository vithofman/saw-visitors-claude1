<?php
/**
 * Terminal Training Step - General Risks
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

error_log("[RISKS.PHP] Template started");

// Get data from flow
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;

error_log("[RISKS.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[RISKS.PHP] Risks text: " . (isset($risks_text) ? substr($risks_text, 0, 100) : 'NOT SET'));

// Check if risks text exists
$has_risks = !empty($risks_text);

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_risks FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_risks);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'title' => 'Informace o rizicích',
        'subtitle' => 'Bezpečnostní informace',
        'confirm' => 'Potvrzuji, že jsem si přečetl/a informace o rizicích',
        'continue' => 'Pokračovat',
        'no_risks' => 'Informace o rizicích nejsou k dispozici',
    ),
    'en' => array(
        'title' => 'Risk Information',
        'subtitle' => 'Safety information',
        'confirm' => 'I confirm that I have read the risk information',
        'continue' => 'Continue',
        'no_risks' => 'Risk information not available',
    ),
    'sk' => array(
        'title' => 'Informácie o rizikách',
        'subtitle' => 'Bezpečnostné informácie',
        'confirm' => 'Potvrdzujem, že som si prečítal/a informácie o rizikách',
        'continue' => 'Pokračovať',
        'no_risks' => 'Informácie o rizikách nie sú k dispozícii',
    ),
    'uk' => array(
        'title' => 'Інформація про ризики',
        'subtitle' => 'Інформація про безпеку',
        'confirm' => 'Підтверджую, що прочитав інформацію про ризики',
        'continue' => 'Продовжити',
        'no_risks' => 'Інформація про ризики недоступна',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #f7fafc; z-index: 9999; overflow: hidden;">
    
    <!-- Header -->
    <div style="background: white; border-bottom: 2px solid #e2e8f0; padding: 1.5rem 2rem;">
        <h2 style="margin: 0; font-size: 1.75rem; color: #2d3748; font-weight: 700;">
            ⚠️ <?php echo esc_html($t['title']); ?>
        </h2>
        <p style="margin: 0.5rem 0 0 0; color: #718096;">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <?php if (!$has_risks): ?>
    <!-- Error: No risks -->
    <div style="padding: 2rem; text-align: center;">
        <div style="background: #fff5f5; border: 2px solid #fc8181; border-radius: 12px; padding: 2rem; max-width: 600px; margin: 0 auto;">
            <p style="margin: 0; font-size: 1.25rem; color: #c53030; font-weight: 600;">
                ⚠️ <?php echo esc_html($t['no_risks']); ?>
            </p>
        </div>
        
        <form method="POST" style="margin-top: 2rem;">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_risks">
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
                    <?php echo wp_kses_post($risks_text); ?>
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
        <form method="POST" id="training-risks-form" style="max-width: 900px; margin: 0 auto; display: flex; align-items: center; gap: 2rem;">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_risks">
            
            <?php if (!$completed): ?>
            <label style="flex: 1; display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: 1rem; background: #fffaf0; border: 2px solid #f6ad55; border-radius: 8px;">
                <input type="checkbox" 
                       name="risks_confirmed" 
                       id="risks-confirmed" 
                       value="1"
                       style="width: 24px; height: 24px; cursor: pointer;"
                       required>
                <span style="color: #744210; font-weight: 600; font-size: 1.05rem;">
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

<?php if ($has_risks): ?>
<script>
// Enable continue button when checkbox is checked
jQuery(document).ready(function($) {
    $('#risks-confirmed').on('change', function() {
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
error_log("[RISKS.PHP] Template finished");
?>