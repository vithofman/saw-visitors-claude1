<?php
/**
 * Account Types List Template
 *
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap saw-account-types-page">
    <div class="saw-page-header">
        <h1 class="saw-page-title">Account Types</h1>
        <button type="button" class="button button-primary" id="add-account-type-btn">
            <span class="dashicons dashicons-plus-alt2"></span>
            Add New Account Type
        </button>
    </div>

    <?php $admin_table->render(); ?>
</div>

<!-- Account Type Detail Modal -->
<div id="account-type-detail-modal" class="saw-modal" style="display: none;">
    <div class="saw-modal-overlay"></div>
    <div class="saw-modal-container">
        <div class="saw-modal-header">
            <h2 class="saw-modal-title">Account Type Details</h2>
            <button type="button" class="saw-modal-close" aria-label="Close modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="saw-modal-body">
            <div class="saw-detail-grid">
                <div class="saw-detail-row">
                    <div class="saw-detail-label">ID:</div>
                    <div class="saw-detail-value" data-field="id"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Display Name:</div>
                    <div class="saw-detail-value" data-field="display_name"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Internal Name:</div>
                    <div class="saw-detail-value" data-field="name"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Color:</div>
                    <div class="saw-detail-value">
                        <span class="saw-color-preview" data-field="color"></span>
                        <span data-field="color"></span>
                    </div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Price:</div>
                    <div class="saw-detail-value" data-field="price"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Sort Order:</div>
                    <div class="saw-detail-value" data-field="sort_order"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Features:</div>
                    <div class="saw-detail-value saw-detail-features" data-field="features"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Status:</div>
                    <div class="saw-detail-value" data-field="is_active"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Created:</div>
                    <div class="saw-detail-value" data-field="created_at"></div>
                </div>
                <div class="saw-detail-row">
                    <div class="saw-detail-label">Updated:</div>
                    <div class="saw-detail-value" data-field="updated_at"></div>
                </div>
            </div>
        </div>
        <div class="saw-modal-footer">
            <button type="button" class="button button-primary saw-edit-account-type" data-id="">Edit</button>
            <button type="button" class="button saw-modal-close">Close</button>
        </div>
    </div>
</div>

<!-- Account Type Form Modal -->
<div id="account-type-form-modal" class="saw-modal" style="display: none;">
    <div class="saw-modal-overlay"></div>
    <div class="saw-modal-container saw-modal-lg">
        <div class="saw-modal-header">
            <h2 class="saw-modal-title" id="form-modal-title">Add Account Type</h2>
            <button type="button" class="saw-modal-close" aria-label="Close modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="saw-modal-body">
            <?php include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/account-types/form.php'; ?>
        </div>
    </div>
</div>
