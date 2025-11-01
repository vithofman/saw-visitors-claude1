<?php
/**
 * File Upload Component Configuration
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
    ],
    'max_file_size' => 2097152, // 2MB
    'upload_dirs' => [
        'customers' => '/saw-customers/',
        'materials' => '/saw-visitor-docs/materials/',
        'visitor-uploads' => '/saw-visitor-docs/visitor-uploads/',
        'risk-docs' => '/saw-visitor-docs/risk-docs/',
    ],
];