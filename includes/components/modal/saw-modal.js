/**
 * SAW Modal Component JavaScript
 * 
 * Univerzální modal systém - ŽÁDNÉ hardcoded entity!
 * 
 * @package SAW_Visitors
 * @version 4.9.0
 */

(function($) {
    'use strict';
    
    const SAWModal = {
        
        currentModalData: {},
        
        open: function(modalId, data = {}) {
            const fullId = this.getFullId(modalId);
            const $modal = $('#' + fullId);
            
            if ($modal.length === 0) {
                console.error('SAWModal: Modal not found:', fullId);
                return;
            }
            
            this.currentModalData[modalId] = data;
            
            console.log('🔵 SAWModal: Opening modal', fullId, data);
            
            this.updateHeaderActions($modal, data);
            
            const ajaxEnabled = $modal.data('ajax-enabled') === 1;
            
            if (ajaxEnabled) {
                this.loadAjaxContent($modal, data);
            }
            
            $modal.addClass('saw-modal-open');
            $('body').addClass('saw-modal-active');
            
            $modal.find('[data-modal-close]').first().focus();
            
            $(document).trigger('saw:modal:opened', {
                modalId: modalId,
                fullId: fullId,
                data: data
            });
        },
        
        updateHeaderActions: function($modal, data) {
            const itemId = data.id || null;
            
            if (!itemId) {
                return;
            }
            
            $modal.find('[data-action-type="edit"]').each(function() {
                const $btn = $(this);
                let url = $btn.data('action-url');
                
                if (url && url.includes('{id}')) {
                    url = url.replace('{id}', itemId);
                    $btn.attr('href', url);
                }
            });
            
            $modal.find('[data-action-type="delete"]').each(function() {
                $(this).data('item-id', itemId);
            });
        },
        
        close: function(modalId) {
            const fullId = this.getFullId(modalId);
            const $modal = $('#' + fullId);
            
            if ($modal.length === 0) {
                console.error('SAWModal: Modal not found:', fullId);
                return;
            }
            
            console.log('🔵 SAWModal: Closing modal', fullId);
            
            $modal.removeClass('saw-modal-open');
            
            delete this.currentModalData[modalId];
            
            if ($('.saw-modal.saw-modal-open').length === 0) {
                $('body').removeClass('saw-modal-active');
            }
            
            $(document).trigger('saw:modal:closed', {
                modalId: modalId,
                fullId: fullId
            });
        },
        
        loadAjaxContent: function($modal, data) {
            const ajaxAction = $modal.data('ajax-action');
            
            if (!ajaxAction) {
                console.error('SAWModal: Missing AJAX action');
                return;
            }
            
            const $content = $modal.find('.saw-modal-body');
            
            $content.html(
                '<div class="saw-modal-loading">' +
                '<div class="saw-spinner"></div>' +
                '<p>Načítám...</p>' +
                '</div>'
            );
            
            const ajaxData = {
                action: ajaxAction,
                nonce: data.nonce || sawModalGlobal.nonce,
                ...data
            };
            
            $.ajax({
                url: sawModalGlobal.ajaxurl,
                method: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        this.handleAjaxSuccess($modal, $content, response.data);
                    } else {
                        this.handleAjaxError($modal, $content, response.data?.message || 'Chyba při načítání');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('SAWModal AJAX error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    this.handleAjaxError($modal, $content, 'Chyba komunikace se serverem (HTTP ' + xhr.status + ')');
                }
            });
        },
        
        handleAjaxSuccess: function($modal, $content, data) {
            if (typeof data === 'string') {
                $content.html(data);
                return;
            }
            
            if (data.html) {
                $content.html(data.html);
                return;
            }
            
            if (data.content) {
                $content.html(data.content);
                return;
            }
            
            let item = null;
            for (let key in data) {
                if (key !== 'success' && key !== 'message' && typeof data[key] === 'object') {
                    item = data[key];
                    break;
                }
            }
            
            if (!item) {
                item = data.item || data;
            }
            
            const html = this.renderUniversalDetail(item);
            $content.html(html);
        },
        
        handleAjaxError: function($modal, $content, message) {
            $content.html(
                '<div class="saw-alert saw-alert-danger" style="padding: 16px 20px; border-radius: 8px; background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626;">' +
                '<strong>Chyba:</strong> ' + this.escapeHtml(message) +
                '</div>'
            );
        },
        
        renderUniversalDetail: function(item) {
            if (!item || typeof item !== 'object') {
                return '<p class="saw-text-muted">Žádná data k zobrazení</p>';
            }
            
            let html = '<div class="saw-detail-grid">';
            
            const headerInfo = this.getHeaderInfo(item);
            if (headerInfo.visual || headerInfo.title) {
                html += '<div class="saw-detail-header">';
                
                if (headerInfo.visual) {
                    html += headerInfo.visual;
                }
                
                if (headerInfo.title) {
                    html += '<div class="saw-detail-header-info">';
                    html += '<h2>' + this.escapeHtml(headerInfo.title) + '</h2>';
                    if (headerInfo.subtitle) {
                        html += '<p class="saw-text-muted">' + this.escapeHtml(headerInfo.subtitle) + '</p>';
                    }
                    html += '</div>';
                }
                
                html += '</div>';
            }
            
            html += '<div class="saw-detail-sections">';
            
            const sections = this.groupFieldsIntoSections(item);
            
            sections.forEach(section => {
                if (section.fields.length === 0) return;
                
                html += '<div class="saw-detail-section">';
                html += '<h3>' + section.title + '</h3>';
                
                if (section.type === 'list') {
                    html += '<ul class="saw-features-list">';
                    section.fields.forEach(value => {
                        html += '<li><span class="dashicons dashicons-yes-alt"></span> ' + this.escapeHtml(value) + '</li>';
                    });
                    html += '</ul>';
                } else if (section.type === 'text') {
                    html += '<p>' + this.escapeHtml(section.fields[0]) + '</p>';
                } else {
                    html += '<dl>';
                    section.fields.forEach(field => {
                        html += this.renderDetailRow(field.label, field.value);
                    });
                    html += '</dl>';
                }
                
                html += '</div>';
            });
            
            const meta = this.getMetaInfo(item);
            if (meta) {
                html += '<div class="saw-detail-section saw-detail-meta">';
                html += '<p class="saw-text-small saw-text-muted">' + meta + '</p>';
                html += '</div>';
            }
            
            html += '</div></div>';
            
            return html;
        },
        
        getHeaderInfo: function(item) {
            const info = {
                visual: null,
                title: null,
                subtitle: null
            };
            
            if (item.logo_url) {
                info.visual = '<img src="' + item.logo_url + '" alt="Logo" class="saw-detail-logo">';
            } else if (item.color && item.display_name) {
                info.visual = '<div style="width: 80px; height: 80px; background-color: ' + item.color + '; border-radius: 16px; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.1), 0 0 0 1px #e5e7eb;"></div>';
            }
            
            info.title = item.display_name || item.name || item.title || this.getFirstTextField(item);
            
            if (item.ico) {
                info.subtitle = 'IČO: ' + item.ico;
            } else if (item.name && item.display_name && item.name !== item.display_name) {
                info.subtitle = 'Interní název: ' + item.name;
            } else if (item.email) {
                info.subtitle = item.email;
            }
            
            return info;
        },
        
        groupFieldsIntoSections: function(item) {
            const sections = [];
            const skipFields = ['id', 'created_at', 'updated_at', 'logo_url', 'color', 'name', 'display_name', 'title', 'ico', 'email'];
            const arrayFields = ['features', 'features_array'];
            const textFields = ['description', 'notes', 'bio', 'about'];
            
            const basicFields = [];
            let featuresArray = null;
            let textBlock = null;
            const contactFields = [];
            const addressFields = [];
            
            Object.keys(item).forEach(key => {
                if (skipFields.includes(key) || (key.includes('_formatted') && key !== 'price_formatted')) return;
                if (item[key] === null || item[key] === undefined || item[key] === '') return;
                
                if (arrayFields.includes(key) && Array.isArray(item[key])) {
                    featuresArray = item[key];
                    return;
                }
                
                if (textFields.includes(key)) {
                    textBlock = { key, value: item[key] };
                    return;
                }
                
                if (key.includes('contact') || (key.includes('email') && !key.includes('_')) || (key.includes('phone') && !key.includes('_'))) {
                    contactFields.push({
                        label: this.humanizeKey(key),
                        value: this.formatValue(key, item[key], item)
                    });
                    return;
                }
                
                if (key.includes('address') || key.includes('street') || key.includes('city') || key.includes('zip')) {
                    addressFields.push({
                        label: this.humanizeKey(key),
                        value: this.formatValue(key, item[key], item)
                    });
                    return;
                }
                
                basicFields.push({
                    label: this.humanizeKey(key),
                    value: this.formatValue(key, item[key], item)
                });
            });
            
            if (basicFields.length > 0) {
                sections.push({
                    title: 'Základní údaje',
                    type: 'keyvalue',
                    fields: basicFields
                });
            }
            
            if (featuresArray && featuresArray.length > 0) {
                sections.push({
                    title: 'Funkce',
                    type: 'list',
                    fields: featuresArray
                });
            }
            
            if (textBlock) {
                sections.push({
                    title: this.humanizeKey(textBlock.key),
                    type: 'text',
                    fields: [textBlock.value]
                });
            }
            
            if (contactFields.length > 0) {
                sections.push({
                    title: 'Kontaktní údaje',
                    type: 'keyvalue',
                    fields: contactFields
                });
            }
            
            if (addressFields.length > 0) {
                sections.push({
                    title: 'Adresa',
                    type: 'keyvalue',
                    fields: addressFields
                });
            }
            
            return sections;
        },
        
        formatValue: function(key, value, item) {
            if (typeof value === 'boolean' || key.includes('is_')) {
                return value ? '<span class="saw-badge saw-badge-success">Ano</span>' : '<span class="saw-badge saw-badge-secondary">Ne</span>';
            }
            
            if (key === 'color' || key.includes('_color')) {
                return '<span style="display: inline-block; width: 32px; height: 32px; background-color: ' + value + '; border-radius: 8px; border: 3px solid #fff; box-shadow: 0 0 0 1px #e5e7eb; vertical-align: middle;"></span> ' + value.toUpperCase();
            }
            
            if (key.includes('email') && typeof value === 'string' && value.includes('@')) {
                return '<a href="mailto:' + value + '">' + this.escapeHtml(value) + '</a>';
            }
            
            if (key.includes('phone') || key.includes('telefon')) {
                return '<a href="tel:' + value + '">' + this.escapeHtml(value) + '</a>';
            }
            
            if (key.includes('price') && !key.includes('_formatted')) {
                return parseFloat(value).toLocaleString('cs-CZ') + ' Kč';
            }
            
            if (key === 'status' && item.status_label) {
                return item.status_label;
            }
            
            if (key === 'subscription_type' && item.subscription_type_label) {
                return item.subscription_type_label;
            }
            
            return this.escapeHtml(String(value));
        },
        
        humanizeKey: function(key) {
            key = key.replace('_formatted', '');
            
            const translations = {
                'name': 'Název',
                'display_name': 'Zobrazovaný název',
                'price': 'Cena',
                'color': 'Barva',
                'status': 'Status',
                'is_active': 'Aktivní',
                'sort_order': 'Pořadí',
                'ico': 'IČO',
                'dic': 'DIČ',
                'contact_person': 'Kontaktní osoba',
                'contact_email': 'Email',
                'contact_phone': 'Telefon',
                'subscription_type': 'Typ předplatného',
                'primary_color': 'Hlavní barva',
            };
            
            if (translations[key]) {
                return translations[key];
            }
            
            return key.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        },
        
        getFirstTextField: function(item) {
            for (let key in item) {
                if (key !== 'id' && typeof item[key] === 'string' && item[key].length > 0) {
                    return item[key];
                }
            }
            return 'Detail';
        },
        
        getMetaInfo: function(item) {
            const parts = [];
            
            if (item.created_at_formatted) {
                parts.push('Vytvořeno: ' + item.created_at_formatted);
            } else if (item.created_at) {
                parts.push('Vytvořeno: ' + item.created_at);
            }
            
            if (item.updated_at_formatted) {
                parts.push('Upraveno: ' + item.updated_at_formatted);
            } else if (item.updated_at) {
                parts.push('Upraveno: ' + item.updated_at);
            }
            
            return parts.length > 0 ? parts.join(' | ') : null;
        },
        
        renderDetailRow: function(label, value) {
            if (!value || value === '-' || value === '') {
                return '';
            }
            return '<dt>' + this.escapeHtml(label) + '</dt><dd>' + value + '</dd>';
        },
        
        getFullId: function(modalId) {
            if (modalId.startsWith('saw-modal-')) {
                return modalId;
            }
            return 'saw-modal-' + modalId;
        },
        
        escapeHtml: function(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    // Close button click
    $(document).on('click', '[data-modal-close]', function() {
        const $modal = $(this).closest('.saw-modal');
        const modalId = $modal.attr('id').replace('saw-modal-', '');
        SAWModal.close(modalId);
    });
    
    // Backdrop click
    $(document).on('click', '.saw-modal-overlay', function(e) {
        const $modal = $(this).closest('.saw-modal');
        const closeOnBackdrop = $modal.data('close-backdrop') !== 0;
        
        if (closeOnBackdrop) {
            const modalId = $modal.attr('id').replace('saw-modal-', '');
            SAWModal.close(modalId);
        }
    });
    
    // ESC key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('body').hasClass('saw-modal-active')) {
            const $openModal = $('.saw-modal.saw-modal-open').last();
            
            if ($openModal.length > 0) {
                const closeOnEscape = $openModal.data('close-escape') !== 0;
                
                if (closeOnEscape) {
                    const modalId = $openModal.attr('id').replace('saw-modal-', '');
                    SAWModal.close(modalId);
                }
            }
        }
    });
    
    // Header action buttons
    $(document).on('click', '.saw-modal-action-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $(this);
        const $modal = $btn.closest('.saw-modal');
        const modalId = $modal.attr('id').replace('saw-modal-', '');
        
        const actionType = $btn.data('action-type');
        const actionUrl = $btn.data('action-url');
        const actionConfirm = $btn.data('action-confirm');
        const actionConfirmMsg = $btn.data('action-confirm-message') || 'Opravdu chcete provést tuto akci?';
        const actionAjax = $btn.data('action-ajax');
        const actionCallback = $btn.data('action-callback');
        const itemId = $btn.data('item-id') || SAWModal.currentModalData[modalId]?.id;
        
        if (actionConfirm && !confirm(actionConfirmMsg)) {
            return;
        }
        
        if (actionType === 'edit' && actionUrl) {
            let url = actionUrl;
            if (url.includes('{id}') && itemId) {
                url = url.replace('{id}', itemId);
            }
            window.location.href = url;
            
        } else if (actionType === 'delete') {
            if (actionAjax) {
                handleDeleteAction($btn, $modal, modalId, actionAjax, itemId);
            } else if (actionCallback && typeof window[actionCallback] === 'function') {
                window[actionCallback](modalId, itemId, $modal);
            }
            
        } else if (actionCallback && typeof window[actionCallback] === 'function') {
            window[actionCallback](modalId, itemId, $modal);
        }
        
        $(document).trigger('saw:modal:action', {
            modalId: modalId,
            actionType: actionType,
            itemId: itemId,
            button: this
        });
    });
    
    function handleDeleteAction($btn, $modal, modalId, ajaxAction, itemId) {
        const originalHtml = $btn.html();
        
        $btn.prop('disabled', true).html(
            '<span class="dashicons dashicons-update saw-spin"></span>'
        );
        
        $.ajax({
            url: sawModalGlobal.ajaxurl,
            method: 'POST',
            data: {
                action: ajaxAction,
                nonce: sawModalGlobal.nonce,
                id: itemId
            },
            success: function(response) {
                if (response.success) {
                    if (typeof sawShowToast === 'function') {
                        sawShowToast('Úspěšně smazáno', 'success');
                    }
                    
                    SAWModal.close(modalId);
                    
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert('Chyba: ' + (response.data?.message || 'Neznámá chyba'));
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Chyba při mazání');
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }
    
    $(document).on('click', '[data-modal-action]', function() {
        const action = $(this).data('modal-action');
        const $modal = $(this).closest('.saw-modal');
        const modalId = $modal.attr('id').replace('saw-modal-', '');
        
        if (action === 'close') {
            SAWModal.close(modalId);
        } else if (action === 'confirm') {
            $(document).trigger('saw:modal:confirmed', {
                modalId: modalId,
                button: this
            });
        } else {
            $(document).trigger('saw:modal:action', {
                modalId: modalId,
                action: action,
                button: this
            });
        }
    });
    
    $(document).ready(function() {
        console.log('🚀 SAWModal initialized');
    });
    
    window.SAWModal = SAWModal;
    
})(jQuery);