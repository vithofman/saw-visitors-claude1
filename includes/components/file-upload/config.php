<?php
/**
 * File Upload Component Configuration
 * 
 * Defines allowed MIME types, file size limits, and upload directory paths
 * for the file upload component.
 * 
 * @package     SAW_Visitors
 * @subpackage  Components/FileUpload
 * @version     1.0.0
 * @since       1.0.0
 * @author      SAW Visitors Team
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    /**
     * Allowed MIME types
     * 
     * List of permitted MIME types for uploads.
     * Currently allows JPEG, PNG, and GIF images.
     */
    'allowed_mimes' => array(
        'image/jpeg',
        'image/png',
        'image/gif',
    ),
    
    /**
     * Maximum file size
     * 
     * Maximum allowed file size in bytes.
     * Default: 2097152 bytes (2MB)
     */
    'max_file_size' => 2097152,
    
    /**
     * Upload directories
     * 
     * Directory paths relative to WordPress uploads directory.
     * Each key represents a context for file uploads.
     */
    'upload_dirs' => array(
        'customers' => '/saw-customers/',
        'branches' => '/saw-branches/',
        'materials' => '/saw-visitor-docs/materials/',
        'visitor-uploads' => '/saw-visitor-docs/visitor-uploads/',
        'risk-docs' => '/saw-visitor-docs/risk-docs/',
        'documents' => '/saw-training/documents/',
        'oopp' => '/saw-oopp/',
    ),
);