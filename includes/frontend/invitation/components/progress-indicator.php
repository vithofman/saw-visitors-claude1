<?php
/**
 * Progress Indicator Component
 * 
 * Zobrazuje vizuální progres skrze invitation flow.
 * Ukazuje dokončené, aktuální a zbývající kroky.
 * 
 * @package SAW_Visitors
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

$flow = $this->session->get('invitation_flow');
$completed = $flow['completed_steps'] ?? [];
$current = $this->current_step;

// Definuj všechny možné kroky
$all_steps = [
    'language' => [
        'icon' => '🌐',
        'title_cs' => 'Jazyk',
        'title_en' => 'Language'
    ],
    'risks' => [
        'icon' => '⚠️',
        'title_cs' => 'Rizika',
        'title_en' => 'Risks'
    ],
    'visitors' => [
        'icon' => '👥',
        'title_cs' => 'Návštěvníci',
        'title_en' => 'Visitors'
    ],
];

// Přidej training kroky pokud existují
if ($this->has_training_content()) {
    $training_steps = [
        'training-video' => [
            'icon' => '🎥',
            'title_cs' => 'Video',
            'title_en' => 'Video'
        ],
        'training-map' => [
            'icon' => '🗺️',
            'title_cs' => 'Mapa',
            'title_en' => 'Map'
        ],
        'training-risks' => [
            'icon' => '⚠️',
            'title_cs' => 'Školení rizika',
            'title_en' => 'Training Risks'
        ],
        'training-department' => [
            'icon' => '🏢',
            'title_cs' => 'Oddělení',
            'title_en' => 'Department'
        ],
        'training-additional' => [
            'icon' => 'ℹ️',
            'title_cs' => 'Další info',
            'title_en' => 'Additional'
        ],
    ];
    
    $all_steps = array_merge($all_steps, $training_steps);
}

$all_steps['success'] = [
    'icon' => '✅',
    'title_cs' => 'Hotovo',
    'title_en' => 'Done'
];

// Zjisti jazyk pro UI
$lang = $flow['language'] ?? 'cs';
$title_key = ($lang === 'en') ? 'title_en' : 'title_cs';
?>

<div class="progress-indicator" style="
    position: fixed;
    top: 6rem;
    left: 1.5rem;
    z-index: 9998;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    max-width: 200px;
    max-height: calc(100vh - 8rem);
    overflow-y: auto;
">
    <div style="
        font-size: 0.75rem;
        font-weight: 600;
        color: #666;
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    ">
        <?php echo $lang === 'en' ? 'Progress' : 'Průběh'; ?>
    </div>
    
    <?php foreach ($all_steps as $step => $data): 
        $is_completed = in_array($step, $completed);
        $is_current = ($step === $current);
        
        // Určit opacity
        if ($is_completed) {
            $opacity = '1';
            $font_weight = '500';
        } elseif ($is_current) {
            $opacity = '1';
            $font_weight = '700';
        } else {
            $opacity = '0.4';
            $font_weight = '400';
        }
        
        // Background pro current step
        $background = $is_current ? 'background: rgba(102, 126, 234, 0.1); border-left: 3px solid #667eea; padding-left: 0.5rem;' : '';
    ?>
    <div style="
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0;
        opacity: <?php echo $opacity; ?>;
        font-weight: <?php echo $font_weight; ?>;
        <?php echo $background; ?>
        transition: all 0.3s ease;
    ">
        <span style="font-size: 1.25rem; flex-shrink: 0;">
            <?php echo $is_completed ? '✅' : $data['icon']; ?>
        </span>
        <span style="font-size: 0.875rem;">
            <?php echo esc_html($data[$title_key]); ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Mobile: Hide progress indicator -->
<style>
@media (max-width: 768px) {
    .progress-indicator {
        display: none;
    }
}
</style>

