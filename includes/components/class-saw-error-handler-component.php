<?php
/**
 * SAW Error Handler Component
 * 
 * Provides user-friendly error pages instead of wp_die()
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Error_Handler_Component {
    
    /**
     * Render permission denied error
     * 
     * @param string $action Action that was denied (create, edit, delete, etc.)
     * @param string $module Module name
     * @param string $back_url URL to go back to
     */
    public static function render_permission_denied($action, $module = '', $back_url = null) {
        $action_labels = [
            'list' => 'zobrazit seznam',
            'view' => 'zobrazit detail',
            'create' => 'vytvořit',
            'edit' => 'upravit',
            'delete' => 'smazat',
        ];
        
        $action_text = $action_labels[$action] ?? $action;
        
        $title = 'Nedostatečná oprávnění';
        $message = $module 
            ? "Nemáte oprávnění <strong>{$action_text}</strong> v modulu <strong>{$module}</strong>."
            : "Nemáte oprávnění provést akci <strong>{$action_text}</strong>.";
        
        if (!$back_url) {
            $back_url = $_SERVER['HTTP_REFERER'] ?? home_url('/admin/');
        }
        
        self::render_error_page([
            'icon' => '🔒',
            'title' => $title,
            'message' => $message,
            'type' => 'permission',
            'back_url' => $back_url,
            'actions' => [
                [
                    'label' => '← Zpět',
                    'url' => $back_url,
                    'class' => 'saw-btn-primary'
                ],
                [
                    'label' => '🏠 Dashboard',
                    'url' => home_url('/admin/'),
                    'class' => 'saw-btn-secondary'
                ]
            ]
        ]);
    }
    
    /**
     * Render module access denied error
     * 
     * @param string $module Module name
     */
    public static function render_module_access_denied($module) {
        self::render_error_page([
            'icon' => '🚫',
            'title' => 'Přístup zamítnut',
            'message' => "Nemáte přístup k modulu <strong>{$module}</strong>. Kontaktujte administrátora pro přidělení oprávnění.",
            'type' => 'access',
            'actions' => [
                [
                    'label' => '← Zpět na Dashboard',
                    'url' => home_url('/admin/'),
                    'class' => 'saw-btn-primary'
                ]
            ]
        ]);
    }
    
    /**
     * Render not found error
     * 
     * @param string $entity Entity name
     * @param int $id Entity ID
     */
    public static function render_not_found($entity = 'Záznam', $id = null) {
        $message = $id 
            ? "<strong>{$entity}</strong> s ID <strong>{$id}</strong> nebyl nalezen."
            : "<strong>{$entity}</strong> nebyl nalezen.";
        
        self::render_error_page([
            'icon' => '🔍',
            'title' => 'Nenalezeno',
            'message' => $message,
            'type' => 'not_found',
            'actions' => [
                [
                    'label' => '← Zpět',
                    'url' => $_SERVER['HTTP_REFERER'] ?? home_url('/admin/'),
                    'class' => 'saw-btn-primary'
                ]
            ]
        ]);
    }
    
    /**
     * Render generic error page
     * 
     * @param array $args Error page arguments
     */
    public static function render_error_page($args) {
        $defaults = [
            'icon' => '⚠️',
            'title' => 'Chyba',
            'message' => 'Něco se pokazilo.',
            'type' => 'error',
            'actions' => [],
            'back_url' => null,
        ];
        
        $args = array_merge($defaults, $args);
        
        ob_start();
        ?>
        <div class="saw-error-page">
            <div class="saw-error-container">
                <div class="saw-error-icon saw-error-<?php echo esc_attr($args['type']); ?>">
                    <?php echo $args['icon']; ?>
                </div>
                
                <h1 class="saw-error-title">
                    <?php echo esc_html($args['title']); ?>
                </h1>
                
                <div class="saw-error-message">
                    <?php echo wp_kses_post($args['message']); ?>
                </div>
                
                <?php if (!empty($args['actions'])): ?>
                    <div class="saw-error-actions">
                        <?php foreach ($args['actions'] as $action): ?>
                            <a href="<?php echo esc_url($action['url']); ?>" 
                               class="saw-btn <?php echo esc_attr($action['class'] ?? 'saw-btn-secondary'); ?>">
                                <?php echo esc_html($action['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="saw-error-help">
                    <p>💡 <strong>Potřebujete pomoc?</strong></p>
                    <p>Kontaktujte administrátora systému nebo zkuste:</p>
                    <ul>
                        <li>Vrátit se na předchozí stránku</li>
                        <li>Přejít na dashboard</li>
                        <li>Zkontrolovat své oprávnění</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
        .saw-error-page {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .saw-error-container {
            max-width: 600px;
            width: 100%;
            text-align: center;
            background: #ffffff;
            border-radius: 12px;
            padding: 48px 32px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .saw-error-icon {
            font-size: 80px;
            line-height: 1;
            margin-bottom: 24px;
            animation: errorBounce 0.6s ease-in-out;
        }
        
        @keyframes errorBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .saw-error-title {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 16px 0;
        }
        
        .saw-error-message {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        .saw-error-message strong {
            color: #374151;
            font-weight: 600;
        }
        
        .saw-error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        
        .saw-error-actions .saw-btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .saw-error-actions .saw-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
        }
        
        .saw-error-actions .saw-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .saw-error-actions .saw-btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .saw-error-actions .saw-btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .saw-error-help {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid #e5e7eb;
            text-align: left;
        }
        
        .saw-error-help p {
            margin: 0 0 12px 0;
            color: #6b7280;
            font-size: 14px;
        }
        
        .saw-error-help ul {
            margin: 12px 0 0 0;
            padding-left: 24px;
            color: #6b7280;
            font-size: 14px;
        }
        
        .saw-error-help li {
            margin-bottom: 8px;
        }
        
        .saw-error-permission .saw-error-icon {
            color: #ef4444;
        }
        
        .saw-error-access .saw-error-icon {
            color: #f59e0b;
        }
        
        .saw-error-not_found .saw-error-icon {
            color: #3b82f6;
        }
        
        @media (max-width: 640px) {
            .saw-error-container {
                padding: 32px 24px;
            }
            
            .saw-error-icon {
                font-size: 60px;
            }
            
            .saw-error-title {
                font-size: 24px;
            }
            
            .saw-error-actions {
                flex-direction: column;
            }
            
            .saw-error-actions .saw-btn {
                width: 100%;
            }
        }
        </style>
        <?php
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $layout->render($content, $args['title'], '');
        } else {
            echo $content;
        }
        
        exit;
    }
}