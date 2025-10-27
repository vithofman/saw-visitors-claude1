<?php
/**
 * Template Name: SAW Blank Template
 * 
 * Prázdný template pro frontend SAW aplikace
 * Používá se pro /admin/, /manager/, /terminal/
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - SAW Visitors</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php
    // Content will be rendered by the router
    ?>
    <?php wp_footer(); ?>
</body>
</html>
