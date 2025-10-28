/**
 * SAW Customers Table - Specific implementation
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

class SAWCustomersTable extends SAWDataTable {
    constructor() {
        super('customers', {
            ajaxAction: 'saw_search_customers',
            perPage: 20,
            currentSearch: '',
            currentOrderBy: 'name',
            currentOrder: 'ASC'
        });
    }
    
    /**
     * Build rows HTML for customers
     */
    buildRowsHTML(customers) {
        let rowsHTML = '';
        
        customers.forEach(customer => {
            const logoHTML = customer.logo_url 
                ? `<img src="${this.escapeHtml(customer.logo_url)}" alt="${this.escapeHtml(customer.name)}" style="max-width: 60px; height: auto; border-radius: 4px;">`
                : '<span class="dashicons dashicons-building" style="font-size: 40px; color: #9ca3af;"></span>';
            
            const colorHTML = customer.color 
                ? `<div style="display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; background: ${this.escapeHtml(customer.color)}; border: 1px solid rgba(0,0,0,0.1);"></span>
                    <code style="font-size: 12px;">${this.escapeHtml(customer.color)}</code>
                   </div>`
                : '—';
            
            const editUrl = sawCustomersConfig ? sawCustomersConfig.editUrl + '?id=' + customer.id : '#';
            
            rowsHTML += `
                <tr>
                    <td style="width: 80px;" data-label="Logo">
                        <div class="saw-table-logo">
                            ${logoHTML}
                        </div>
                    </td>
                    <td data-label="Název">
                        <strong>${this.escapeHtml(customer.name)}</strong>
                    </td>
                    <td style="width: 120px;" data-label="IČO">
                        ${this.escapeHtml(customer.ico || '—')}
                    </td>
                    <td data-label="Adresa">
                        ${this.escapeHtml(customer.address || '—')}
                    </td>
                    <td style="width: 100px;" data-label="Barva">
                        ${colorHTML}
                    </td>
                    <td style="width: 140px;" class="saw-text-center" data-label="Akce">
                        <div class="saw-table-actions">
                            <a href="${editUrl}" 
                                class="saw-btn saw-btn-sm saw-btn-secondary" 
                                title="Upravit">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <button type="button" 
                                class="saw-btn saw-btn-sm saw-btn-danger saw-delete-customer" 
                                data-id="${customer.id}" 
                                data-name="${this.escapeHtml(customer.name)}" 
                                title="Smazat">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        return rowsHTML;
    }
    
    /**
     * Build complete table HTML
     */
    buildTableHTML(data) {
        const rowsHTML = this.buildRowsHTML(data.items);
        
        return `
            <div class="saw-table-responsive">
                <table class="saw-table saw-table-sortable">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Logo</th>
                            <th class="saw-sortable" data-column="name" data-label="Název">
                                <a href="#">Název ${this.getSortIcon('name')}</a>
                            </th>
                            <th class="saw-sortable" data-column="ico" style="width: 120px;" data-label="IČO">
                                <a href="#">IČO ${this.getSortIcon('ico')}</a>
                            </th>
                            <th>Adresa</th>
                            <th style="width: 100px;">Barva</th>
                            <th style="width: 140px;" class="saw-text-center">Akce</th>
                        </tr>
                    </thead>
                    <tbody id="saw-customers-tbody">
                        ${rowsHTML}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
}

// Initialize when DOM is ready
jQuery(document).ready(function($) {
    // Check if we're on customers page
    if ($('#saw-customers-container').length) {
        const customersTable = new SAWCustomersTable();
        
        // Delete customer handler
        $(document).on('click', '.saw-delete-customer', function() {
            const customerId = $(this).data('id');
            const customerName = $(this).data('name');
            
            if (confirm(`Opravdu chcete smazat zákazníka "${customerName}"?\n\nTato akce je nevratná!`)) {
                // TODO: Implement delete via AJAX
                console.log('Delete customer:', customerId);
                alert('Funkce mazání bude implementována.');
            }
        });
    }
});