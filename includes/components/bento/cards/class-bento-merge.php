<?php
/**
 * SAW Bento Merge Card
 * 
 * Karta pro kontrolu a sloučení duplicitních záznamů.
 * Používá se primárně v modulu companies.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Bento_Merge extends SAW_Bento_Card {
    
    /**
     * Default arguments
     * 
     * @var array
     */
    protected $defaults = [
        'icon' => 'git-merge',
        'title' => 'Kontrola duplicit',
        'warning_title' => 'Možné duplicity',
        'warning_text' => 'Zkontrolujte, zda neexistují podobné záznamy',
        'check_btn_label' => 'Zkontrolovat',
        'entity_id' => 0,
        'entity_type' => 'companies',
        'ajax_action' => 'saw_show_merge_modal_companies',
        'merge_ajax_action' => 'saw_merge_companies',
        'colspan' => 2,
        'variant' => 'warning',
        'tabs' => [
            'auto' => [
                'icon' => '🤖',
                'label' => 'Auto detekce',
            ],
            'manual' => [
                'icon' => '✋',
                'label' => 'Manuální výběr',
            ],
        ],
        'manual_companies' => [],
        'translations' => [],
    ];
    
    /**
     * Render the merge card
     */
    public function render() {
        $args = $this->args;
        $t = $args['translations'];
        
        $tr = function($key, $fallback) use ($t) {
            return $t[$key] ?? $fallback;
        };
        
        $classes = $this->build_classes([
            'bento-card',
            'bento-merge',
            $this->get_colspan_class($args['colspan']),
        ]);
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-entity-id="<?php echo intval($args['entity_id']); ?>" data-entity-type="<?php echo esc_attr($args['entity_type']); ?>">
            
            <!-- Warning Box -->
            <div class="bento-merge-warning">
                <div class="bento-merge-warning-icon">
                    <?php $this->render_icon('alert-triangle'); ?>
                </div>
                <div class="bento-merge-warning-content">
                    <strong class="bento-merge-warning-title">
                        <?php echo esc_html($args['warning_title']); ?>
                    </strong>
                    <p class="bento-merge-warning-text">
                        <?php echo esc_html($args['warning_text']); ?>
                    </p>
                </div>
                <button type="button" class="bento-merge-check-btn" id="sawMergeBtn">
                    <?php $this->render_icon('search', 'bento-merge-check-icon'); ?>
                    <?php echo esc_html($args['check_btn_label']); ?>
                </button>
            </div>
            
            <!-- Merge Container (hidden by default) -->
            <div class="bento-merge-container" id="sawMergeContainer">
                <div class="bento-merge-header">
                    <div class="bento-merge-header-title">
                        <?php $this->render_icon('git-merge', 'bento-merge-header-icon'); ?>
                        <span><?php echo esc_html($tr('merge_title', 'Sloučit duplicity')); ?></span>
                    </div>
                    <button type="button" class="bento-merge-close" onclick="closeMerge()">
                        <?php $this->render_icon('x'); ?>
                    </button>
                </div>
                
                <div class="bento-merge-body">
                    <!-- Tabs -->
                    <div class="bento-merge-tabs">
                        <button type="button" class="bento-merge-tab active" data-tab="auto" onclick="switchTab('auto')">
                            <span class="bento-merge-tab-icon"><?php echo $args['tabs']['auto']['icon']; ?></span>
                            <?php echo esc_html($tr('merge_auto_detection', $args['tabs']['auto']['label'])); ?>
                        </button>
                        <button type="button" class="bento-merge-tab" data-tab="manual" onclick="switchTab('manual')">
                            <span class="bento-merge-tab-icon"><?php echo $args['tabs']['manual']['icon']; ?></span>
                            <?php echo esc_html($tr('merge_manual_selection', $args['tabs']['manual']['label'])); ?>
                        </button>
                    </div>
                    
                    <!-- Auto Detection Content -->
                    <div class="bento-merge-content active" id="sawMergeAuto">
                        <div id="sawMergeAutoContent">
                            <div class="bento-merge-loading">
                                <?php $this->render_icon('loader', 'bento-merge-spinner'); ?>
                                <span><?php echo esc_html($tr('loading', 'Načítání...')); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manual Selection Content -->
                    <div class="bento-merge-content" id="sawMergeManual">
                        <div class="bento-merge-help-text">
                            <?php $this->render_icon('info', 'bento-merge-help-icon'); ?>
                            <?php echo esc_html($tr('merge_manual_help', 'Vyhledejte a vyberte záznamy, které chcete sloučit pod aktuální záznam')); ?>
                        </div>
                        
                        <div class="bento-merge-search">
                            <div class="bento-merge-search-input-wrapper">
                                <?php $this->render_icon('search', 'bento-merge-search-icon'); ?>
                                <input type="text" 
                                       id="sawManualSearch" 
                                       class="bento-merge-search-input"
                                       placeholder="<?php echo esc_attr($tr('merge_search_placeholder', 'Hledat...')); ?>" 
                                       onkeyup="filterManualList()">
                            </div>
                        </div>
                        
                        <div class="bento-merge-list" id="sawManualList">
                            <?php if (!empty($args['manual_companies'])): ?>
                                <?php foreach ($args['manual_companies'] as $company): ?>
                                <label class="bento-merge-item" data-name="<?php echo esc_attr(strtolower($company['name'])); ?>">
                                    <input type="checkbox" name="manual_ids[]" value="<?php echo intval($company['id']); ?>" onchange="updateMergeButton()">
                                    <div class="bento-merge-item-info">
                                        <strong><?php echo esc_html($company['name']); ?></strong>
                                        <div class="bento-merge-item-meta">
                                            <span class="bento-merge-item-visits">
                                                <?php $this->render_icon('clipboard-list', 'bento-merge-meta-icon'); ?>
                                                <?php echo intval($company['visit_count'] ?? 0); ?> <?php echo esc_html($tr('visits_count', 'návštěv')); ?>
                                            </span>
                                            <?php if (!empty($company['ico'])): ?>
                                            <span class="bento-merge-item-ico">
                                                <?php echo esc_html($tr('ico_label', 'IČO')); ?>: <?php echo esc_html($company['ico']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="bento-merge-actions">
                            <button type="button" class="bento-merge-submit-btn" id="sawMergeButton" onclick="confirmMerge()" disabled>
                                <?php $this->render_icon('git-merge', 'bento-merge-submit-icon'); ?>
                                <?php echo esc_html($tr('merge_selected_btn', 'Sloučit vybrané')); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
