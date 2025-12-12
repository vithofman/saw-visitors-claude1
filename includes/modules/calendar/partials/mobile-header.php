<?php
/**
 * Mobile Header Component
 * 
 * Sticky header for mobile calendar view.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar/Partials
 * @version     1.0.0
 * @since       3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$create_url = home_url('/admin/visits/create');
?>

<header class="saw-mobile-header">
    <div class="saw-mobile-header__left">
        <h1 class="saw-mobile-header__title">
            <span class="saw-mobile-header__icon">📅</span>
            Kalendář
        </h1>
    </div>
    <div class="saw-mobile-header__right">
        <a href="<?php echo esc_url($create_url); ?>" class="saw-mobile-header__btn">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            <span class="saw-mobile-header__btn-text">Nová</span>
        </a>
    </div>
</header>
