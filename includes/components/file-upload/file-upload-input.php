<?php
/**
 * File Upload Input Template
 * 
 * Renders the modern file upload component HTML.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     2.0.0
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render file upload input
 * 
 * @since 2.0.0
 * @param array $args Component arguments
 * @return void
 */
function saw_file_upload_input($args = array()) {
    $defaults = array(
        'name' => 'file_upload',
        'id' => '',
        'multiple' => false,
        'accept' => '',
        'max_size' => 0, // 0 = no limit, in bytes
        'max_files' => 0, // 0 = no limit, number of files
        'context' => 'documents',
        'class' => '',
        'required' => false,
        'existing_files' => array(), // Array of existing file metadata
        'category_config' => array(), // Category/document type configuration
        // category_config structure:
        // 'enabled' => true/false
        // 'source' => 'database' | 'config'
        // 'options' => array() // For config source, array of ['id' => X, 'name' => 'Y']
        // 'table' => 'table_name' // For database source
        // 'id_field' => 'id'
        // 'name_field' => 'name'
        // 'required' => true/false
        // 'label' => 'Typ dokumentu'
        // 'multiple' => true/false // For multiselect
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Generate unique ID if not provided
    if (empty($args['id'])) {
        $args['id'] = 'saw-file-upload-' . uniqid();
    }
    
    // Enqueue assets
    if (class_exists('SAW_Component_File_Upload')) {
        SAW_Component_File_Upload::enqueue_assets();
    }
    
    // Prepare options for JavaScript
    $js_options = array(
        'multiple' => $args['multiple'],
        'accept' => $args['accept'],
        'maxSize' => $args['max_size'],
        'maxFiles' => $args['max_files'],
        'uploadUrl' => admin_url('admin-ajax.php'),
        'context' => $args['context'],
        'name' => $args['name'],
        'id' => $args['id'],
        'existingFiles' => $args['existing_files'],
        'categoryConfig' => $args['category_config'],
    );
    
    $container_class = 'saw-file-upload-modern-container';
    if (!empty($args['class'])) {
        $container_class .= ' ' . esc_attr($args['class']);
    }
    
    ?>
    <div 
        class="<?php echo esc_attr($container_class); ?>"
        data-options="<?php echo esc_attr(json_encode($js_options)); ?>"
        data-name="<?php echo esc_attr($args['name']); ?>"
        data-context="<?php echo esc_attr($args['context']); ?>"
    ></div>
    
    <?php if ($args['required']): ?>
        <input 
            type="hidden" 
            name="<?php echo esc_attr($args['name']); ?>_required" 
            value="1"
        >
    <?php endif; ?>
    <?php
}
