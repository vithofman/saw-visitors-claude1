<?php
/**
 * SAW Table - Main Orchestration Class
 * 
 * Renders table pages with list view, detail sidebar, and form sidebar.
 * Configuration-driven - no module-specific templates needed.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     1.0.0
 * @since       5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-saw-table-config.php';
require_once __DIR__ . '/renderers/class-badge-renderer.php';
require_once __DIR__ . '/renderers/class-section-renderer.php';
require_once __DIR__ . '/renderers/class-row-renderer.php';

class SAW_Table {
    
    /**
     * Module configuration
     * @var array
     */
    protected $config;
    
    /**
     * Table data (items, pagination, etc.)
     * @var array
     */
    protected $data;
    
    /**
     * Sidebar context (mode, items)
     * @var array
     */
    protected $sidebar;
    
    /**
     * Entity name
     * @var string
     */
    protected $entity;
    
    /**
     * Base URL for routes
     * @var string
     */
    protected $base_url;
    
    /**
     * Constructor
     * 
     * @param array $config Module configuration
     * @param array $data Table data
     * @param array $sidebar Sidebar context
     */
    public function __construct($config, $data = array(), $sidebar = array()) {
        $this->config = SAW_Table_Config::normalize($config);
        $this->data = $data;
        $this->sidebar = $sidebar;
        $this->entity = $config['entity'] ?? '';
        $this->base_url = home_url('/admin/' . ($config['route'] ?? $this->entity));
    }
    
    /**
     * Render the complete table page
     */
    public function render() {
        $this->render_layout();
    }
    
    /**
     * Render page layout
     */
    protected function render_layout() {
        ?>
        <div class="sawt-page" data-entity="<?php echo esc_attr($this->entity); ?>">
            <?php $this->render_page_header(); ?>
            <?php $this->render_tabs(); ?>
            
            <div class="sawt-page-body <?php echo $this->has_sidebar() ? 'has-sidebar' : ''; ?>">
                <div class="sawt-table-container">
                    <?php $this->render_table(); ?>
                </div>
                
                <?php if ($this->has_sidebar()): ?>
                    <?php $this->render_sidebar(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render page header
     */
    protected function render_page_header() {
        $title = $this->config['plural'] ?? 'Položky';
        $icon = $this->config['icon'] ?? '';
        $total = $this->data['total'] ?? 0;
        $create_label = 'Nový ' . ($this->config['singular'] ?? 'záznam');
        ?>
        <div class="sawt-page-header">
            <div class="sawt-page-header-left">
                <?php if ($icon): ?>
                    <span class="sawt-page-header-icon"><?php echo esc_html($icon); ?></span>
                <?php endif; ?>
                <h1 class="sawt-page-title"><?php echo esc_html($title); ?></h1>
                <span class="sawt-page-count"><?php echo number_format($total); ?></span>
            </div>
            <div class="sawt-page-header-right">
                <?php $this->render_search(); ?>
                <a href="<?php echo esc_url($this->base_url . '/create'); ?>" class="sawt-btn sawt-btn-primary">
                    + <?php echo esc_html($create_label); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render search box
     */
    protected function render_search() {
        $search = $this->data['search'] ?? '';
        $placeholder = $this->config['list']['search_placeholder'] ?? 'Hledat...';
        ?>
        <div class="sawt-search">
            <span class="sawt-search-icon">🔍</span>
            <input 
                type="text" 
                class="sawt-search-input" 
                placeholder="<?php echo esc_attr($placeholder); ?>"
                value="<?php echo esc_attr($search); ?>"
                data-action="search"
            >
        </div>
        <?php
    }
    
    /**
     * Render tabs
     */
    protected function render_tabs() {
        $tabs_config = $this->config['tabs'] ?? array();
        
        if (empty($tabs_config['enabled'])) {
            return;
        }
        
        $tabs = $tabs_config['tabs'] ?? array();
        $current_tab = $this->data['current_tab'] ?? ($tabs_config['default_tab'] ?? 'all');
        $tab_counts = $this->data['tab_counts'] ?? array();
        ?>
        <div class="sawt-tabs">
            <?php foreach ($tabs as $key => $tab): ?>
                <?php
                $is_active = ($key === $current_tab);
                $count = $tab_counts[$key] ?? 0;
                $url = add_query_arg('tab', $key, $this->base_url . '/');
                ?>
                <a href="<?php echo esc_url($url); ?>" 
                   class="sawt-tab <?php echo $is_active ? 'is-active' : ''; ?>"
                   data-tab="<?php echo esc_attr($key); ?>">
                    <?php if (!empty($tab['icon'])): ?>
                        <span class="sawt-tab-icon"><?php echo esc_html($tab['icon']); ?></span>
                    <?php endif; ?>
                    <span class="sawt-tab-label"><?php echo esc_html($tab['label']); ?></span>
                    <span class="sawt-tab-count"><?php echo number_format($count); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render table
     */
    protected function render_table() {
        $items = $this->data['items'] ?? array();
        $columns = $this->config['columns'] ?? array();
        
        if (empty($items)) {
            $this->render_empty_state();
            return;
        }
        ?>
        <table class="sawt-table">
            <thead class="sawt-table-head">
                <tr>
                    <?php foreach ($columns as $key => $col): ?>
                        <?php $this->render_table_header_cell($key, $col); ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="sawt-table-body">
                <?php foreach ($items as $item): ?>
                    <?php $this->render_table_row($item, $columns); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php $this->render_infinite_scroll_loader(); ?>
        <?php
    }
    
    /**
     * Render table header cell
     */
    protected function render_table_header_cell($key, $col) {
        $label = $col['label'] ?? '';
        $sortable = !empty($col['sortable']);
        $width = $col['width'] ?? '';
        $align = $col['align'] ?? 'left';
        
        $width_class = $width ? "sawt-col-{$width}" : '';
        $align_class = $align !== 'left' ? "sawt-text-{$align}" : '';
        
        $classes = array_filter(array('sawt-th', $width_class, $align_class));
        
        $current_orderby = $this->data['orderby'] ?? '';
        $current_order = $this->data['order'] ?? 'ASC';
        $is_sorted = ($current_orderby === $key);
        ?>
        <th class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php if ($sortable): ?>
                <a href="#" class="sawt-sortable <?php echo $is_sorted ? 'is-sorted' : ''; ?>" 
                   data-sort="<?php echo esc_attr($key); ?>"
                   data-order="<?php echo $is_sorted && $current_order === 'ASC' ? 'DESC' : 'ASC'; ?>">
                    <?php echo esc_html($label); ?>
                    <?php if ($is_sorted): ?>
                        <span class="sawt-sort-icon"><?php echo $current_order === 'ASC' ? '↑' : '↓'; ?></span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <?php echo esc_html($label); ?>
            <?php endif; ?>
        </th>
        <?php
    }
    
    /**
     * Render table row
     */
    protected function render_table_row($item, $columns) {
        $id = $item['id'] ?? 0;
        $detail_url = str_replace('{id}', $id, $this->base_url . '/{id}/');
        $is_active = $this->is_active_row($id);
        ?>
        <tr class="sawt-tr <?php echo $is_active ? 'is-active' : ''; ?>" 
            data-id="<?php echo esc_attr($id); ?>"
            data-url="<?php echo esc_url($detail_url); ?>">
            <?php foreach ($columns as $key => $col): ?>
                <?php $this->render_table_cell($item, $key, $col); ?>
            <?php endforeach; ?>
        </tr>
        <?php
    }
    
    /**
     * Render table cell
     */
    protected function render_table_cell($item, $key, $col) {
        $type = $col['type'] ?? 'text';
        $value = $item[$key] ?? '';
        $align = $col['align'] ?? 'left';
        $bold = !empty($col['bold']);
        
        $align_class = $align !== 'left' ? "sawt-text-{$align}" : '';
        $bold_class = $bold ? 'sawt-text-bold' : '';
        
        $classes = array_filter(array('sawt-td', $align_class, $bold_class));
        ?>
        <td class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php echo SAW_Row_Renderer::render_value($value, $type, $col, $item); ?>
        </td>
        <?php
    }
    
    /**
     * Render empty state
     */
    protected function render_empty_state() {
        $icon = $this->config['list']['empty_icon'] ?? $this->config['icon'] ?? '📋';
        $message = $this->config['list']['empty_message'] ?? 'Žádné záznamy nenalezeny';
        $create_label = 'Vytvořit ' . ($this->config['singular'] ?? 'záznam');
        ?>
        <div class="sawt-empty">
            <div class="sawt-empty-icon"><?php echo esc_html($icon); ?></div>
            <div class="sawt-empty-title"><?php echo esc_html($message); ?></div>
            <a href="<?php echo esc_url($this->base_url . '/create'); ?>" class="sawt-btn sawt-btn-primary">
                + <?php echo esc_html($create_label); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Render infinite scroll loader
     */
    protected function render_infinite_scroll_loader() {
        $config = $this->config['infinite_scroll'] ?? array();
        
        if (empty($config['enabled'])) {
            return;
        }
        ?>
        <div class="sawt-infinite-loader" style="display: none;">
            <div class="sawt-spinner"></div>
            <span>Načítám další...</span>
        </div>
        <?php
    }
    
    /**
     * Check if sidebar should be shown
     */
    protected function has_sidebar() {
        return !empty($this->sidebar['mode']);
    }
    
    /**
     * Render sidebar
     */
    protected function render_sidebar() {
        $mode = $this->sidebar['mode'] ?? '';
        
        if ($mode === 'detail') {
            $this->render_detail_sidebar();
        } elseif ($mode === 'form') {
            $this->render_form_sidebar();
        }
    }
    
    /**
     * Render detail sidebar
     */
    protected function render_detail_sidebar() {
        $item = $this->sidebar['detail_item'] ?? null;
        
        if (empty($item)) {
            return;
        }
        
        $detail_config = $this->config['detail'] ?? array();
        $title = $item['display_name'] ?? $item['name'] ?? 'Detail';
        ?>
        <div class="sawt-sidebar-wrapper is-open">
            <div class="sawt-sidebar-backdrop" data-action="close-sidebar"></div>
            <div class="sawt-sidebar">
                <!-- Header with navigation -->
                <div class="sawt-sidebar-header">
                    <div class="sawt-sidebar-header-left">
                        <button type="button" class="sawt-sidebar-nav-btn" data-action="prev" title="Předchozí">
                            ←
                        </button>
                        <button type="button" class="sawt-sidebar-nav-btn" data-action="next" title="Další">
                            →
                        </button>
                    </div>
                    <div class="sawt-sidebar-header-right">
                        <button type="button" class="sawt-sidebar-close" data-action="close-sidebar">
                            ✕
                        </button>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="sawt-sidebar-content">
                    <?php $this->render_detail_header($item, $detail_config); ?>
                    <?php $this->render_detail_sections($item, $detail_config); ?>
                </div>
                
                <!-- Floating actions -->
                <?php $this->render_floating_actions($item, $detail_config); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render detail header (blue gradient)
     */
    protected function render_detail_header($item, $config) {
        $title = $item['display_name'] ?? $item['name'] ?? 'Detail';
        $badges = $config['header_badges'] ?? array();
        ?>
        <div class="sawt-detail-header">
            <div class="sawt-detail-header-inner">
                <h3 class="sawt-detail-header-title"><?php echo esc_html($title); ?></h3>
                
                <?php if (!empty($badges)): ?>
                <div class="sawt-detail-header-meta">
                    <?php echo SAW_Badge_Renderer::render_badges($badges, $item); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="sawt-detail-header-stripe"></div>
        </div>
        <?php
    }
    
    /**
     * Render detail sections
     */
    protected function render_detail_sections($item, $config) {
        $sections = $config['sections'] ?? array();
        
        if (empty($sections)) {
            return;
        }
        ?>
        <div class="sawt-detail-wrapper">
            <div class="sawt-detail-stack">
                <?php foreach ($sections as $key => $section): ?>
                    <?php echo SAW_Section_Renderer::render($section, $item, array(), $this->entity); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render floating action buttons
     */
    protected function render_floating_actions($item, $config) {
        $actions = $config['actions'] ?? array();
        
        if (empty($actions)) {
            return;
        }
        
        $id = $item['id'] ?? 0;
        ?>
        <div class="sawt-floating-actions">
            <?php if (!empty($actions['edit'])): ?>
                <a href="<?php echo esc_url($this->base_url . '/' . $id . '/edit'); ?>" 
                   class="sawt-floating-btn sawt-floating-btn-edit"
                   title="<?php echo esc_attr($actions['edit']['label'] ?? 'Upravit'); ?>">
                    ✏️
                </a>
            <?php endif; ?>
            
            <?php if (!empty($actions['delete'])): ?>
                <button type="button" 
                        class="sawt-floating-btn sawt-floating-btn-delete"
                        data-action="delete"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-confirm="<?php echo esc_attr($actions['delete']['confirm'] ?? 'Opravdu smazat?'); ?>"
                        title="<?php echo esc_attr($actions['delete']['label'] ?? 'Smazat'); ?>">
                    🗑️
                </button>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render form sidebar
     */
    protected function render_form_sidebar() {
        $item = $this->sidebar['form_item'] ?? null;
        $is_edit = !empty($item);
        $title = $is_edit 
            ? 'Upravit ' . ($this->config['singular'] ?? 'záznam')
            : 'Nový ' . ($this->config['singular'] ?? 'záznam');
        ?>
        <div class="sawt-sidebar-wrapper is-open">
            <div class="sawt-sidebar-backdrop" data-action="close-sidebar"></div>
            <div class="sawt-sidebar sawt-sidebar-form">
                <div class="sawt-sidebar-header">
                    <div class="sawt-sidebar-header-left">
                        <h2 class="sawt-sidebar-title"><?php echo esc_html($title); ?></h2>
                    </div>
                    <div class="sawt-sidebar-header-right">
                        <button type="button" class="sawt-sidebar-close" data-action="close-sidebar">
                            ✕
                        </button>
                    </div>
                </div>
                
                <div class="sawt-sidebar-content">
                    <?php
                    // Include form template
                    $form_template = $this->config['path'] . 'form-template.php';
                    if (file_exists($form_template)) {
                        $GLOBALS['saw_sidebar_form'] = true;
                        $config = $this->config;
                        include $form_template;
                        unset($GLOBALS['saw_sidebar_form']);
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Check if row is active (selected)
     */
    protected function is_active_row($id) {
        if (!empty($this->sidebar['detail_item']['id'])) {
            return $this->sidebar['detail_item']['id'] == $id;
        }
        if (!empty($this->sidebar['form_item']['id'])) {
            return $this->sidebar['form_item']['id'] == $id;
        }
        return false;
    }
}
