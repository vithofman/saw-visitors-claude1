<?php
/**
 * Training Content Viewer Component
 * 
 * Displays collapsible sections with richtext content and document attachments
 * Used for risks, additional info, and department-specific content
 * 
 * @package SAW_Visitors
 * @version 3.0.0
 * @since   3.0.0
 * 
 * @param array $args {
 *     Component arguments
 *     
 *     @type array $sections Array of content sections {
 *         @type string $title      Section title (required)
 *         @type string $content    HTML content (optional)
 *         @type array  $documents  Array of documents (optional) {
 *             @type string $name Document name
 *             @type string $url  Document URL
 *             @type string $icon Icon emoji (default: '📄')
 *         }
 *         @type bool   $collapsed  Is section initially collapsed (default: false)
 *     }
 *     @type bool $scrollable Make viewer scrollable (default: true)
 *     @type string $max_height Max height for scrollable (default: '60vh')
 * }
 * 
 * Usage:
 * get_template_part('components/training-content-viewer', null, [
 *     'sections' => [
 *         [
 *             'title' => 'Bezpečnostní rizika',
 *             'content' => '<p>Obsah sekce...</p>',
 *             'documents' => [
 *                 ['name' => 'Safety Guide.pdf', 'url' => '/path/to/file.pdf']
 *             ],
 *             'collapsed' => false
 *         ]
 *     ]
 * ]);
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get component arguments
$sections = $args['sections'] ?? [];
$scrollable = $args['scrollable'] ?? true;
$max_height = $args['max_height'] ?? '60vh';

// Validation
if (empty($sections)) {
    error_log('[SAW Training Content Viewer] Warning: No sections provided');
    echo '<div class="saw-empty-state">';
    echo '<div class="saw-empty-state-icon">📄</div>';
    echo '<div class="saw-empty-state-title">Žádný obsah</div>';
    echo '<div class="saw-empty-state-message">V tuto chvíli není k dispozici žádný obsah.</div>';
    echo '</div>';
    return;
}

// Build viewer classes
$viewer_classes = ['saw-content-viewer'];
if ($scrollable) {
    $viewer_classes[] = 'scrollable';
}

// Generate unique ID for JavaScript
$viewer_id = 'saw-viewer-' . uniqid();
?>

<div class="<?php echo esc_attr(implode(' ', $viewer_classes)); ?>" 
     id="<?php echo esc_attr($viewer_id); ?>"
     <?php if ($scrollable): ?>
     style="max-height: <?php echo esc_attr($max_height); ?>;"
     <?php endif; ?>>
    
    <?php foreach ($sections as $index => $section): ?>
        <?php
        // Validate section
        if (empty($section['title'])) {
            error_log('[SAW Training Content Viewer] Warning: Section title is required');
            continue;
        }
        
        $section_title = $section['title'];
        $section_content = $section['content'] ?? '';
        $section_documents = $section['documents'] ?? [];
        $section_collapsed = $section['collapsed'] ?? false;
        
        // Build section classes
        $section_classes = ['saw-content-section'];
        if ($section_collapsed) {
            $section_classes[] = 'collapsed';
        }
        
        $section_id = $viewer_id . '-section-' . $index;
        ?>
        
        <div class="<?php echo esc_attr(implode(' ', $section_classes)); ?>" 
             id="<?php echo esc_attr($section_id); ?>"
             data-section-index="<?php echo esc_attr($index); ?>">
            
            <!-- Section Header (Clickable) -->
            <button type="button" 
                    class="saw-content-section-header"
                    data-section-toggle="<?php echo esc_attr($section_id); ?>"
                    aria-expanded="<?php echo $section_collapsed ? 'false' : 'true'; ?>"
                    aria-controls="<?php echo esc_attr($section_id); ?>-body">
                
                <span class="saw-content-section-icon" aria-hidden="true">▼</span>
                
                <h3 class="saw-content-section-title">
                    <?php echo esc_html($section_title); ?>
                </h3>
            </button>
            
            <!-- Section Body (Collapsible) -->
            <div class="saw-content-section-body" 
                 id="<?php echo esc_attr($section_id); ?>-body"
                 role="region"
                 aria-labelledby="<?php echo esc_attr($section_id); ?>-header">
                
                <?php if (!empty($section_content)): ?>
                <!-- Rich Text Content -->
                <div class="saw-content-text">
                    <?php echo wp_kses_post($section_content); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($section_documents)): ?>
                <!-- Documents Section -->
                <div class="saw-content-documents">
                    <h4>📎 Přílohy</h4>
                    <ul class="saw-document-list">
                        <?php foreach ($section_documents as $doc): ?>
                            <?php
                            $doc_name = $doc['name'] ?? '';
                            $doc_url = $doc['url'] ?? '';
                            $doc_icon = $doc['icon'] ?? '📄';
                            
                            if (empty($doc_name) || empty($doc_url)) {
                                continue;
                            }
                            ?>
                            <li>
                                <a href="<?php echo esc_url($doc_url); ?>" 
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="saw-document-link">
                                    <span class="saw-document-icon"><?php echo esc_html($doc_icon); ?></span>
                                    <span class="saw-document-name"><?php echo esc_html($doc_name); ?></span>
                                    <span class="saw-document-arrow">→</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        
    <?php endforeach; ?>
    
</div>

<script>
(function() {
    'use strict';
    
    // Get all section toggle buttons
    const viewer = document.getElementById('<?php echo esc_js($viewer_id); ?>');
    if (!viewer) {
        console.error('[SAW Content Viewer] Viewer element not found');
        return;
    }
    
    const toggleButtons = viewer.querySelectorAll('[data-section-toggle]');
    
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const sectionId = this.getAttribute('data-section-toggle');
            const section = document.getElementById(sectionId);
            
            if (!section) {
                console.error('[SAW Content Viewer] Section not found:', sectionId);
                return;
            }
            
            // Toggle collapsed class
            const isCollapsed = section.classList.contains('collapsed');
            section.classList.toggle('collapsed');
            
            // Update aria-expanded
            this.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
            
            // Optional: Smooth scroll to section if expanded
            if (isCollapsed && section.getBoundingClientRect().top < 0) {
                section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });
    
    // Log initialization
    console.log('[SAW Content Viewer] Initialized with ' + toggleButtons.length + ' sections');
})();
</script>
