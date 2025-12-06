<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - SAW Visitors</title>
    <?php include SAW_VISITORS_PLUGIN_DIR . 'includes/pwa/pwa-head-tags.php'; ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('saw-app-body'); ?>>
<?php wp_footer(); ?>
</body>
</html>