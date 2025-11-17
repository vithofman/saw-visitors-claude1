<?php
/**
 * Terminal Training Step - Map & Evacuation
 * 
 * Display facility map with evacuation routes
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
$visitor_id = $flow['visitor_id'] ?? null;
$branch_id = $flow['branch_id'] ?? SAW_Context::get_branch_id();

// TODO: Load map PDF/image from branch settings
$map_url = ''; // Branch-specific map
$has_map = !empty($map_url);

// Check if already completed
$completed = false;
if ($visitor_id) {
    global $wpdb;
    $visitor = $wpdb->get_row($wpdb->prepare(
        "SELECT training_step_map FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    $completed = !empty($visitor['training_step_map']);
}

$translations = [
    'cs' => [
        'title' => 'Mapa objektu',
        'subtitle' => 'Seznamte se s evakuačními cestami',
        'assembly_point' => 'Shromaždiště v případě evakuace',
        'emergency_exits' => 'Nouzové východy',
        'fire_extinguishers' => 'Umístění hasicích přístrojů',
        'first_aid' => 'Lékárničky',
        'confirm_read' => 'Potvrzuji, že jsem se seznámil/a s mapou',
        'continue' => 'Pokračovat',
        'map_not_available' => 'Mapa není k dispozici - pokračujte',
    ],
    'en' => [
        'title' => 'Facility Map',
        'subtitle' => 'Familiarize yourself with evacuation routes',
        'assembly_point' => 'Assembly point in case of evacuation',
        'emergency_exits' => 'Emergency exits',
        'fire_extinguishers' => 'Fire extinguisher locations',
        'first_aid' => 'First aid kits',
        'confirm_read' => 'I confirm that I have familiarized myself with the map',
        'continue' => 'Continue',
        'map_not_available' => 'Map not available - continue',
    ],
    'uk' => [
        'title' => 'Карта об\'єкту',
        'subtitle' => 'Ознайомтеся з евакуаційними маршрутами',
        'assembly_point' => 'Місце збору у разі евакуації',
        'emergency_exits' => 'Аварійні виходи',
        'fire_extinguishers' => 'Розміщення вогнегасників',
        'first_aid' => 'Аптечки',
        'confirm_read' => 'Підтверджую, що ознайомився з картою',
        'continue' => 'Продовжити',
        'map_not_available' => 'Карта недоступна - продовжити',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
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
        
        <!-- Progress indicator -->
        <div class="saw-terminal-progress" style="margin-bottom: 2rem;">
            <div class="saw-terminal-progress-step completed">1</div>
            <div class="saw-terminal-progress-step completed">2</div>
            <div class="saw-terminal-progress-step active">3</div>
            <div class="saw-terminal-progress-step">4</div>
            <div class="saw-terminal-progress-step">5</div>
        </div>
        
        <?php if ($has_map): ?>
        
        <!-- Map display -->
        <div class="saw-training-map-container" style="margin-bottom: 2rem;">
            <div class="saw-training-map-wrapper" style="background: #f7fafc; border: 2px solid #e2e8f0; border-radius: 12px; padding: 1rem; max-height: 500px; overflow: auto;">
                <img src="<?php echo esc_url($map_url); ?>" 
                     alt="<?php echo esc_attr($t['title']); ?>"
                     style="width: 100%; height: auto; border-radius: 8px;"
                     id="facility-map">
            </div>
            
            <!-- Zoom controls -->
            <div style="display: flex; gap: 1rem; margin-top: 1rem; justify-content: center;">
                <button type="button" class="saw-terminal-btn saw-terminal-btn-secondary" 
                        onclick="zoomMap(1.2)" 
                        style="width: auto; padding: 0.75rem 1.5rem;">
                    🔍 Přiblížit
                </button>
                <button type="button" class="saw-terminal-btn saw-terminal-btn-secondary" 
                        onclick="zoomMap(0.8)" 
                        style="width: auto; padding: 0.75rem 1.5rem;">
                    🔍 Oddálit
                </button>
                <button type="button" class="saw-terminal-btn saw-terminal-btn-secondary" 
                        onclick="resetZoom()" 
                        style="width: auto; padding: 0.75rem 1.5rem;">
                    ↺ Reset
                </button>
            </div>
        </div>
        
        <!-- Legend -->
        <div style="background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1rem 0; font-size: 1.125rem; font-weight: 700; color: #0369a1;">
                Legenda:
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">🚪</span>
                    <span style="color: #0369a1; font-weight: 600;"><?php echo esc_html($t['emergency_exits']); ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">🧯</span>
                    <span style="color: #0369a1; font-weight: 600;"><?php echo esc_html($t['fire_extinguishers']); ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">📍</span>
                    <span style="color: #0369a1; font-weight: 600;"><?php echo esc_html($t['assembly_point']); ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">⛑️</span>
                    <span style="color: #0369a1; font-weight: 600;"><?php echo esc_html($t['first_aid']); ?></span>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        
        <!-- No map available -->
        <div style="text-align: center; padding: 3rem 0; background: #fffaf0; border: 2px solid #f6ad55; border-radius: 12px; margin-bottom: 2rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">🗺️</div>
            <p style="margin: 0; font-size: 1.125rem; color: #c05621; font-weight: 600;">
                <?php echo esc_html($t['map_not_available']); ?>
            </p>
        </div>
        
        <?php endif; ?>
        
        <!-- Confirmation form -->
        <form method="POST" id="training-map-form">
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
            <input type="hidden" name="terminal_action" value="complete_training_map">
            
            <?php if ($has_map && !$completed): ?>
            <div class="saw-terminal-form-checkbox" style="margin-bottom: 1.5rem;">
                <input type="checkbox" 
                       name="map_confirmed" 
                       id="map-confirmed" 
                       value="1"
                       required>
                <label for="map-confirmed">
                    <?php echo esc_html($t['confirm_read']); ?>
                </label>
            </div>
            <?php endif; ?>
            
            <button type="submit" 
                    class="saw-terminal-btn saw-terminal-btn-success"
                    <?php echo ($has_map && !$completed) ? 'id="continue-btn" disabled' : ''; ?>>
                <?php echo esc_html($t['continue']); ?> →
            </button>
        </form>
        
    </div>
</div>

<?php if ($has_map): ?>
<script>
jQuery(document).ready(function($) {
    let currentZoom = 1;
    
    window.zoomMap = function(factor) {
        currentZoom *= factor;
        currentZoom = Math.max(0.5, Math.min(3, currentZoom));
        $('#facility-map').css('transform', 'scale(' + currentZoom + ')');
    };
    
    window.resetZoom = function() {
        currentZoom = 1;
        $('#facility-map').css('transform', 'scale(1)');
    };
    
    // Enable continue button when checkbox is checked
    $('#map-confirmed').on('change', function() {
        $('#continue-btn').prop('disabled', !$(this).is(':checked'));
    });
});
</script>
<?php endif; ?>
