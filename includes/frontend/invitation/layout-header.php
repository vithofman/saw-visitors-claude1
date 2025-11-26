<?php
if (!defined('ABSPATH')) exit;

$flow = $this->session->get('invitation_flow');
$token = $flow['token'] ?? $this->token ?? '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo get_bloginfo('name'); ?> - Registrace návštěvy</title>
    <?php wp_head(); ?>
    <style>
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%) !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }
        body { overflow-x: hidden; overflow-y: auto; }
        
        .saw-terminal-home-btn {
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 9999;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s ease;
        }
        .saw-terminal-home-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 48px rgba(102, 126, 234, 0.4);
        }
        .saw-terminal-home-btn svg {
            width: 1.25rem;
            height: 1.25rem;
        }
        
        .saw-terminal-wrapper {
            min-height: 100vh;
            padding: 2rem 1.5rem;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }
        .saw-terminal-content {
            width: 100%;
            max-width: 1400px;
        }
    </style>
</head>
<body>

<a href="<?php echo esc_url(home_url('/visitor-invitation/' . $token . '/')); ?>" class="saw-terminal-home-btn" title="Domů">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9,22 9,12 15,12 15,22"/>
    </svg>
</a>

<div class="saw-terminal-wrapper">
    <div class="saw-terminal-content">
        <?php 
        if (isset($flow['error']) && !empty($flow['error'])): 
        ?>
        <div style="background:#fee2e2;color:#991b1b;padding:1rem;border-radius:8px;margin-bottom:1.5rem;border-left:4px solid #dc2626;">
            <?php echo esc_html($flow['error']); ?>
        </div>
        <?php 
            unset($flow['error']);
            $this->session->set('invitation_flow', $flow);
        endif; 
        ?>
