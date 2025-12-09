<?php
/**
 * Visitor Info Portal - Language Selection
 * 
 * ALL STYLES ARE INLINE - no external CSS dependency.
 * 
 * @package SAW_Visitors
 * @since 3.3.0
 * @version 3.6.0 - All styles inline
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get customer logo if available
global $wpdb;
$customer_logo = null;

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
    'vi' => '🇻🇳',
    'ru' => '🇷🇺',
);

$lang_count = count($languages);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a202c">
    <title><?php echo esc_html($t['select_language']); ?> - <?php echo esc_html($this->visitor['customer_name']); ?></title>
    
    <style>
    /* ============================================
       RESET & BASE
       ============================================ */
    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        font-size: 16px;
        line-height: 1.5;
        color: #e5e7eb;
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        -webkit-font-smoothing: antialiased;
    }
    
    /* Hide WP admin bar */
    #wpadminbar { display: none !important; }
    
    /* ============================================
       PAGE CONTAINER
       ============================================ */
    .lang-container {
        width: 100%;
        max-width: 600px;
        animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* ============================================
       HEADER
       ============================================ */
    .lang-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }
    
    .lang-logo {
        width: 100px;
        height: 100px;
        margin: 0 auto 1.5rem;
        border-radius: 20px;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(148, 163, 184, 0.2);
    }
    
    .lang-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    
    .lang-logo-placeholder {
        font-size: 3rem;
    }
    
    .lang-company {
        font-size: 1.75rem;
        font-weight: 700;
        color: #f9fafb;
        margin: 0 0 0.25rem;
    }
    
    .lang-branch {
        font-size: 1rem;
        color: rgba(148, 163, 184, 0.8);
        margin: 0;
    }
    
    /* ============================================
       PAGE HEADER (Title)
       ============================================ */
    .lang-page-header {
        text-align: center;
        margin-bottom: 2rem;
        padding: 2rem;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 20px;
    }
    
    .lang-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
    }
    
    .lang-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #f9fafb;
        margin: 0 0 0.5rem;
        line-height: 1.3;
    }
    
    .lang-subtitle {
        font-size: 0.9375rem;
        color: rgba(148, 163, 184, 0.8);
        margin: 0;
    }
    
    /* ============================================
       LANGUAGE GRID
       ============================================ */
    .lang-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(2, 1fr);
    }
    
    /* 3+ languages - 2 columns */
    /* 4 languages - 2x2 */
    
    .lang-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 1.5rem 1rem;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 2px solid rgba(148, 163, 184, 0.12);
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: center;
        min-height: 120px;
        font-family: inherit;
    }
    
    .lang-btn:hover {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(102, 126, 234, 0.5);
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(102, 126, 234, 0.3);
    }
    
    .lang-btn:active {
        transform: translateY(-2px);
    }
    
    .lang-btn-flag {
        font-size: 2.5rem;
    }
    
    .lang-btn-name {
        font-size: 1.125rem;
        font-weight: 700;
        color: #f9fafb;
    }
    
    /* ============================================
       EMPTY STATE
       ============================================ */
    .lang-empty {
        text-align: center;
        padding: 3rem 2rem;
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 16px;
    }
    
    .lang-empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
    }
    
    .lang-empty-text {
        color: rgba(148, 163, 184, 0.8);
        font-size: 0.9375rem;
        line-height: 1.6;
        margin: 0;
    }
    
    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 480px) {
        body {
            padding: 1rem;
        }
        
        .lang-logo {
            width: 80px;
            height: 80px;
        }
        
        .lang-company {
            font-size: 1.5rem;
        }
        
        .lang-title {
            font-size: 1.25rem;
        }
        
        .lang-btn {
            padding: 1.25rem 0.75rem;
            min-height: 100px;
        }
        
        .lang-btn-flag {
            font-size: 2rem;
        }
        
        .lang-btn-name {
            font-size: 1rem;
        }
    }
    </style>
</head>
<body>

<div class="lang-container">
    
    <!-- Company Header -->
    <div class="lang-header">
        <?php if ($customer_logo): ?>
        <div class="lang-logo">
            <img src="<?php echo esc_url($customer_logo); ?>" 
                 alt="<?php echo esc_attr($this->visitor['customer_name']); ?>">
        </div>
        <?php else: ?>
        <div class="lang-logo">
            <span class="lang-logo-placeholder">🏭</span>
        </div>
        <?php endif; ?>
        
        <h2 class="lang-company"><?php echo esc_html($this->visitor['customer_name']); ?></h2>
        
        <?php if (!empty($this->visitor['branch_name'])): ?>
        <p class="lang-branch"><?php echo esc_html($this->visitor['branch_name']); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Page Header -->
    <div class="lang-page-header">
        <span class="lang-icon">🌍</span>
        <h1 class="lang-title">Vyberte jazyk / Choose Language / Оберіть мову</h1>
        <p class="lang-subtitle">Select your preferred language for this session</p>
    </div>
    
    <?php if (empty($languages)): ?>
    
    <!-- No Languages -->
    <div class="lang-empty">
        <span class="lang-empty-icon">⚠️</span>
        <p class="lang-empty-text">
            Pro tuto pobočku nejsou nastaveny žádné jazyky.<br>
            Kontaktujte správce.
        </p>
    </div>
    
    <?php else: ?>
    
    <!-- Language Grid -->
    <form method="POST">
        <?php wp_nonce_field('saw_visitor_info_step', 'visitor_info_nonce'); ?>
        <input type="hidden" name="visitor_info_action" value="select_language">
        
        <div class="lang-grid">
            <?php foreach ($languages as $lang): 
                $flag = isset($language_flags[$lang['language_code']]) 
                    ? $language_flags[$lang['language_code']] 
                    : '🌐';
            ?>
            <button type="submit" 
                    name="language" 
                    value="<?php echo esc_attr($lang['language_code']); ?>"
                    class="lang-btn">
                <span class="lang-btn-flag"><?php echo $flag; ?></span>
                <span class="lang-btn-name"><?php echo esc_html($lang['language_name']); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </form>
    
    <?php endif; ?>
    
</div>

</body>
</html>