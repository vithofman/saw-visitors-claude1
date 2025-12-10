<?php
/**
 * Calendar Buttons Email Partial
 *
 * Renders "Add to Calendar" buttons for email templates.
 * Uses inline styles for maximum email client compatibility.
 *
 * @package     SAW_Visitors
 * @subpackage  Templates/Emails/Partials
 * @version     1.0.0
 * @since       1.0.0
 * 
 * @var int    $visit_id Visit ID
 * @var string $style    Button style: 'full', 'compact', 'minimal'
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load calendar export component if not loaded
if (!class_exists('SAW_Component_Calendar_Export')) {
    $component_file = SAW_VISITORS_PLUGIN_DIR . 'includes/components/calendar-export/class-saw-component-calendar-export.php';
    if (file_exists($component_file)) {
        require_once $component_file;
    }
}

// Default values
$visit_id = $visit_id ?? 0;
$style = $style ?? 'full';

if (!$visit_id || !class_exists('SAW_Calendar_Links')) {
    return;
}

// Get calendar links
$links = SAW_Calendar_Links::for_visit($visit_id);

if (empty($links)) {
    return;
}

// Get public ICS URL
$calendar_export = SAW_Component_Calendar_Export::instance();
$links['ics']['url'] = $calendar_export->get_public_ics_url($visit_id);

// Inline styles for email compatibility
$container_style = 'margin: 24px 0; text-align: center;';
$box_style = 'display: inline-block; padding: 20px 24px; background-color: #f8f9fa; border-radius: 8px; text-align: center;';
$label_style = 'margin: 0 0 16px 0; font-size: 14px; color: #6b7280; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';
$buttons_style = 'margin: 0;';

// Button base style
$btn_base = 'display: inline-block; padding: 10px 20px; margin: 4px; text-decoration: none; ' .
            'border-radius: 6px; font-size: 14px; font-weight: 500; ' .
            'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;';

// Button colors
$btn_colors = [
    'google' => 'background-color: #4285f4; color: #ffffff;',
    'outlook' => 'background-color: #0078d4; color: #ffffff;',
    'office365' => 'background-color: #d83b01; color: #ffffff;',
    'ics' => 'background-color: #333333; color: #ffffff;',
];

// Select which buttons to show
$show_buttons = ['google', 'outlook', 'ics'];
if ($style === 'minimal') {
    $show_buttons = ['google', 'ics'];
}

?>

<?php if ($style === 'full'): ?>
<!-- Full style with box -->
<div style="<?php echo esc_attr($container_style); ?>">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
        <tr>
            <td style="<?php echo esc_attr($box_style); ?>">
                <p style="<?php echo esc_attr($label_style); ?>">
                    📅 Přidejte si návštěvu do kalendáře:
                </p>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                    <tr>
                        <?php foreach ($show_buttons as $key): ?>
                            <?php if (isset($links[$key])): ?>
                                <td style="padding: 0 4px;">
                                    <a href="<?php echo esc_url($links[$key]['url']); ?>" 
                                       style="<?php echo esc_attr($btn_base . $btn_colors[$key]); ?>"
                                       target="_blank">
                                        <?php echo esc_html($links[$key]['label']); ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<?php elseif ($style === 'compact'): ?>
<!-- Compact style inline -->
<p style="margin: 16px 0; font-size: 13px; color: #6b7280; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    📅 Přidat do kalendáře:
    <?php foreach ($show_buttons as $key): ?>
        <?php if (isset($links[$key])): ?>
            <a href="<?php echo esc_url($links[$key]['url']); ?>" 
               style="color: #2563eb; text-decoration: none; margin-left: 8px;"
               target="_blank">
                <?php echo esc_html($links[$key]['label']); ?>
            </a>
            <?php if ($key !== end($show_buttons)): ?> | <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
</p>

<?php else: ?>
<!-- Minimal style - just links -->
<p style="margin: 12px 0; font-size: 12px; color: #9ca3af;">
    Přidat do kalendáře:
    <?php foreach ($show_buttons as $key): ?>
        <?php if (isset($links[$key])): ?>
            <a href="<?php echo esc_url($links[$key]['url']); ?>" 
               style="color: #6b7280; text-decoration: underline;"
               target="_blank"><?php echo esc_html($links[$key]['label']); ?></a><?php if ($key !== end($show_buttons)): ?>, <?php endif; ?>
        <?php endif; ?>
    <?php endforeach; ?>
</p>
<?php endif; ?>
