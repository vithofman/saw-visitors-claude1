<?php
/**
 * Terminal Training Step - Map & Evacuation
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - PDF.js viewer
 */

if (!defined('ABSPATH')) {
    exit;
}

error_log("[MAP.PHP] Template started");

// Get data from flow
$lang = isset($flow['language']) ? $flow['language'] : 'cs';
$visitor_id = isset($flow['visitor_id']) ? $flow['visitor_id'] : null;

error_log("[MAP.PHP] Language: {$lang}, Visitor ID: {$visitor_id}");
error_log("[MAP.PHP] PDF path: " . (isset($pdf_path) ? $pdf_path : 'NOT SET'));

// Check if PDF exists
$has_pdf = !empty($pdf_path);

// Build full URL to PDF
$pdf_url = '';
if ($has_pdf) {
    // PDF path v DB: /saw-training/pdfs/file.pdf
    // Fyzická cesta: wp-content/uploads/saw-training/pdfs/file.pdf
    // Potřebujeme: https://domain.com/wp-content/uploads/saw-training/pdfs/file.pdf
    $pdf_url = content_url() . '/uploads' . $pdf_path;
    error_log("[MAP.PHP] Full PDF URL: {$pdf_url}");
}

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_map FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    if ($visitor) {
        $completed = !empty($visitor->training_step_map);
    }
}

// Translations
$translations = array(
    'cs' => array(
        'title' => 'Mapa objektu',
        'subtitle' => 'Seznamte se s evakuačními cestami',
        'confirm_read' => 'Potvrzuji, že jsem se seznámil/a s mapou',
        'continue' => 'Pokračovat',
        'map_not_available' => 'Mapa není k dispozici',
        'prev_page' => 'Předchozí',
        'next_page' => 'Další',
        'page' => 'Stránka',
    ),
    'en' => array(
        'title' => 'Facility Map',
        'subtitle' => 'Familiarize yourself with evacuation routes',
        'confirm_read' => 'I confirm that I have familiarized myself with the map',
        'continue' => 'Continue',
        'map_not_available' => 'Map not available',
        'prev_page' => 'Previous',
        'next_page' => 'Next',
        'page' => 'Page',
    ),
    'sk' => array(
        'title' => 'Mapa objektu',
        'subtitle' => 'Zoznámte sa s evakuačnými cestami',
        'confirm_read' => 'Potvrdzujem, že som sa zoznámil/a s mapou',
        'continue' => 'Pokračovať',
        'map_not_available' => 'Mapa nie je k dispozícii',
        'prev_page' => 'Predchádzajúca',
        'next_page' => 'Ďalšia',
        'page' => 'Stránka',
    ),
    'uk' => array(
        'title' => 'Карта об\'єкту',
        'subtitle' => 'Ознайомтеся з евакуаційними маршрутами',
        'confirm_read' => 'Підтверджую, що ознайомився з картою',
        'continue' => 'Продовжити',
        'map_not_available' => 'Карта недоступна',
        'prev_page' => 'Попередня',
        'next_page' => 'Наступна',
        'page' => 'Сторінка',
    ),
);

$t = isset($translations[$lang]) ? $translations[$lang] : $translations['cs'];
?>

<div class="saw-terminal-card">
    <div class="saw-terminal-card-header">
        <h2 class="saw-terminal-card-title">
            🗺️ <?php echo esc_html($t['title']); ?>
        </h2>
        <p class="saw-terminal-card-subtitle">
            <?php echo esc_html($t['subtitle']); ?>
        </p>
    </div>
    
    <div class="saw-terminal-card-body">
        
        <?php if (!$has_pdf): ?>
        <!-- Error: No PDF -->
        <div style="background: #fff5f5; border: 2px solid #fc8181; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; text-align: center;">
            <p style="margin: 0 0 1rem 0; font-size: 1.25rem; color: #c53030; font-weight: 600;">
                ⚠️ <?php echo esc_html($t['map_not_available']); ?>
            </p>
        </div>
        
        <form method="POST">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_map">
            <button type="submit" class="saw-terminal-btn saw-terminal-btn-success">
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
        
        <?php else: ?>
        
        <!-- PDF Viewer -->
        <div class="saw-training-pdf-container" style="margin-bottom: 2rem;">
            <!-- Embed PDF directly -->
            <div style="background: #525659; border-radius: 12px; padding: 1rem; position: relative; height: 70vh;">
                <iframe 
                    src="<?php echo esc_url($pdf_url); ?>#toolbar=0&navpanes=0&scrollbar=1" 
                    style="width: 100%; height: 100%; border: 0; border-radius: 8px;"
                    type="application/pdf">
                </iframe>
            </div>
            
            <!-- Link to open in new tab -->
            <div style="text-align: center; margin-top: 1rem;">
                <a href="<?php echo esc_url($pdf_url); ?>" 
                   target="_blank" 
                   class="saw-terminal-btn saw-terminal-btn-secondary"
                   style="display: inline-block; width: auto; padding: 0.75rem 1.5rem; text-decoration: none;">
                    📄 Otevřít PDF v novém okně
                </a>
            </div>
        </div>
        
        <!-- Confirmation form -->
        <form method="POST" id="training-map-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_map">
            
            <?php if (!$completed): ?>
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: #fffaf0; border: 2px solid #f6ad55; border-radius: 8px;">
                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                    <input type="checkbox" 
                           name="map_confirmed" 
                           id="map-confirmed" 
                           value="1"
                           style="width: 20px; height: 20px; cursor: pointer;"
                           required>
                    <span style="color: #744210; font-weight: 600;">
                        <?php echo esc_html($t['confirm_read']); ?>
                    </span>
                </label>
            </div>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-terminal-btn saw-terminal-btn-success <?php echo !$completed ? 'saw-terminal-btn-disabled' : ''; ?>"
                    id="continue-btn"
                    style="<?php echo !$completed ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                    <?php echo !$completed ? 'disabled' : ''; ?>>
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
        
        <?php endif; ?>
        
    </div>
</div>

<?php if ($has_pdf): ?>
<script>
// Enable continue button when checkbox is checked
jQuery(document).ready(function($) {
    $('#map-confirmed').on('change', function() {
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
error_log("[MAP.PHP] Template finished");
?>