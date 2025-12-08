<?php
/**
 * Visitor Info Portal - Language Selection Template
 * 
 * Displays language selection screen for visitors.
 * Variables are passed from SAW_Visitor_Info_Controller.
 * 
 * Available variables:
 * - $languages (array) - Available languages from get_available_languages()
 * - $t (array) - Translations array
 * - $this->visitor (array) - Visitor data with customer_name, branch_name
 * - $this->token (string) - Access token
 * 
 * @package SAW_Visitors
 * @since 3.3.0
 * @updated 3.3.1 - Fixed logo_path column check
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get customer logo if available (with column existence check)
$customer_logo = '';
if (!empty($this->visitor['customer_id'])) {
    global $wpdb;
    
    // Check if logo_path column exists in saw_customers table
    $column_exists = $wpdb->get_var(
        "SHOW COLUMNS FROM {$wpdb->prefix}saw_customers LIKE 'logo_path'"
    );
    
    if ($column_exists) {
        $logo_path = $wpdb->get_var($wpdb->prepare(
            "SELECT logo_path FROM {$wpdb->prefix}saw_customers WHERE id = %d",
            $this->visitor['customer_id']
        ));
        
        if ($logo_path) {
            $upload_dir = wp_upload_dir();
            $customer_logo = $upload_dir['baseurl'] . '/' . ltrim($logo_path, '/');
        }
    }
}

// Language flags mapping
$language_flags = array(
    'cs' => '🇨🇿',
    'en' => '🇬🇧',
    'sk' => '🇸🇰',
    'uk' => '🇺🇦',
    'de' => '🇩🇪',
    'pl' => '🇵🇱',
    'hu' => '🇭🇺',
    'ro' => '🇷🇴',
);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#667eea">
    <title><?php echo esc_html($t['select_language']); ?> - <?php echo esc_html($this->visitor['customer_name']); ?></title>
    <?php wp_head(); ?>
</head>
<body class="saw-visitor-info-page saw-language-select">

<div class="saw-language-container">
    <div class="saw-language-card">
        
        <!-- Header with Logo -->
        <div class="saw-language-header">
            <?php if ($customer_logo): ?>
            <div class="saw-language-logo">
                <img src="<?php echo esc_url($customer_logo); ?>" 
                     alt="<?php echo esc_attr($this->visitor['customer_name']); ?>">
            </div>
            <?php else: ?>
            <div class="saw-language-logo">
                <span class="saw-language-logo-placeholder">🏭</span>
            </div>
            <?php endif; ?>
            
            <h1 class="saw-company-name"><?php echo esc_html($this->visitor['customer_name']); ?></h1>
            
            <?php if (!empty($this->visitor['branch_name'])): ?>
            <p class="saw-branch-name"><?php echo esc_html($this->visitor['branch_name']); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Language Selection Title -->
        <h2 class="saw-language-title">
            <?php echo esc_html($t['select_language']); ?>
        </h2>
        
        <!-- Language Buttons Form -->
        <form method="POST" class="saw-language-form">
            <?php wp_nonce_field('saw_visitor_info_step', 'visitor_info_nonce'); ?>
            <input type="hidden" name="visitor_info_action" value="select_language">
            
            <div class="saw-language-buttons">
                <?php foreach ($languages as $lang): 
                    $flag = isset($language_flags[$lang['language_code']]) 
                        ? $language_flags[$lang['language_code']] 
                        : '🌐';
                ?>
                <button type="submit" 
                        name="language" 
                        value="<?php echo esc_attr($lang['language_code']); ?>"
                        class="saw-language-btn">
                    <span class="saw-language-flag"><?php echo $flag; ?></span>
                    <span class="saw-language-name"><?php echo esc_html($lang['language_name']); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </form>
        
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>