# SAW Select-Create Component

**Version:** 1.0.0  
**Since:** 13.0.0  
**Package:** SAW_Visitors  
**Subpackage:** Components/SelectCreate

---

## Overview

The Select-Create component provides a universal solution for inline creation of related records directly from form dropdowns. Users can select existing options or create new records without losing their form progress.

### Key Features

✅ **Inline Creation** - Create related records without leaving the form  
✅ **Nested Sidebars** - Opens new form in layered sidebar interface  
✅ **Auto-Update** - Automatically adds new option to dropdown  
✅ **Context Prefill** - Pre-fills nested form with parent context  
✅ **Z-Index Management** - Handles multiple nested levels correctly  
✅ **Responsive Design** - Mobile-friendly layout  
✅ **AJAX-Powered** - Smooth UX without page reloads

---

## Architecture

### Component Structure

```
includes/components/select-create/
├── class-saw-component-select-create.php    # Main PHP class
├── select-create-input.php                  # Template file
├── select-create.css                        # Styles
├── select-create.js                         # JavaScript logic
└── README.md                                # This file
```

### Data Flow

```
User clicks "+ New" button
    ↓
JavaScript opens nested sidebar via AJAX
    ↓
Server loads target module form (Base Controller)
    ↓
User fills nested form and submits
    ↓
Server creates record (AJAX mode detected)
    ↓
JavaScript adds new option to dropdown
    ↓
Nested sidebar closes automatically
```

---

## Basic Usage

### Minimal Example

```php
<?php
// Load component class
if (!class_exists('SAW_Component_Select_Create')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/select-create/class-saw-component-select-create.php';
}

// Create and render component
$select = new SAW_Component_Select_Create('company_id', array(
    'label' => 'Firma',
    'options' => $companies,              // array(id => name)
    'selected' => $item['company_id'],    // current value
    'required' => true,
    'inline_create' => array(
        'enabled' => true,
        'target_module' => 'companies',
        'button_text' => '+ Nová firma',
    ),
));

$select->render();
?>
```

### With Context Prefill

```php
<?php
$select = new SAW_Component_Select_Create('company_id', array(
    'label' => 'Firma',
    'options' => $companies,
    'selected' => $item['company_id'] ?? null,
    'required' => true,
    'inline_create' => array(
        'enabled' => true,
        'target_module' => 'companies',
        'button_text' => '+ Nová firma',
        'prefill' => array(
            'branch_id' => 'context.branch_id',      // Dynamic from SAW_Context
            'customer_id' => 'context.customer_id',  // Dynamic from SAW_Context
        ),
    ),
));

$select->render();
?>
```

---

## Configuration Options

### Main Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `label` | string | `''` | Field label text |
| `options` | array | `[]` | Dropdown options (id => name) |
| `selected` | mixed | `''` | Currently selected value |
| `required` | bool | `false` | Whether field is required |
| `placeholder` | string | `'-- Vyberte --'` | Placeholder text |
| `custom_class` | string | `''` | Additional CSS classes |
| `inline_create` | array | `[]` | Inline create configuration |

### Inline Create Configuration

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `enabled` | bool | `false` | Enable inline create |
| `target_module` | string | `''` | Module slug (e.g. 'companies') |
| `button_text` | string | `'+ Nový'` | Button label |
| `prefill` | array | `[]` | Fields to prefill in nested form |

### Prefill Values

Prefill supports two types of values:

**Static Values:**
```php
'prefill' => array(
    'status' => 'active',
    'type_id' => 5,
)
```

**Dynamic Context References:**
```php
'prefill' => array(
    'branch_id' => 'context.branch_id',
    'customer_id' => 'context.customer_id',
)
```

Context references are automatically resolved from `SAW_Context::get_branch_id()` etc.

---

## Implementation Guide

### Step 1: Update Target Module Controller

Add `get_display_name()` method to define how new records appear in dropdown:

```php
// includes/modules/companies/controller.php

/**
 * Get display name for created item
 * 
 * @param array $item Created item data
 * @return string Display name for dropdown
 */
protected function get_display_name($item) {
    return $item['name'] ?? '';
}
```

### Step 2: Update Target Module Form Template

Add AJAX submit handler for nested mode:

```php
// includes/modules/companies/form-template.php

<?php
// Detect nested inline create mode
$is_nested = isset($GLOBALS['saw_nested_inline_create']) && $GLOBALS['saw_nested_inline_create'];
?>

<?php if ($is_nested): ?>
    <!-- Hidden field to trigger AJAX mode -->
    <input type="hidden" name="_ajax_inline_create" value="1">
    
    <!-- AJAX submit handler -->
    <script>
    jQuery(function($) {
        $('.saw-company-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = $form.serialize();
            
            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Get target field from nested sidebar
                        const targetField = $('.saw-sidebar[data-is-nested="1"]')
                            .last()
                            .attr('data-target-field');
                        
                        // Update dropdown and close sidebar
                        window.SAWSelectCreate.handleInlineSuccess(
                            response.data, 
                            targetField
                        );
                    } else {
                        alert(response.data.message || 'Chyba při ukládání');
                    }
                },
                error: function() {
                    alert('Chyba při komunikaci se serverem');
                }
            });
        });
    });
    </script>
<?php endif; ?>
```

### Step 3: Use Component in Parent Form

Replace standard select with Select-Create component:

```php
// includes/modules/visits/form-template.php

<?php
// Before: Standard select
/*
<select name="company_id" required>
    <option value="">-- Vyberte firmu --</option>
    <?php foreach ($companies as $id => $name): ?>
        <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
    <?php endforeach; ?>
</select>
*/

// After: Select-Create component
$select = new SAW_Component_Select_Create('company_id', array(
    'label' => 'Firma',
    'options' => $companies,
    'selected' => $item['company_id'] ?? null,
    'required' => true,
    'inline_create' => array(
        'enabled' => true,
        'target_module' => 'companies',
        'button_text' => '+ Nová firma',
        'prefill' => array(
            'branch_id' => 'context.branch_id',
        ),
    ),
));
$select->render();
?>
```

---

## Advanced Examples

### Multiple Nested Levels

The component supports multiple nested levels (e.g., Visit → Company → Branch):

```php
// Visit form with company select
$company_select = new SAW_Component_Select_Create('company_id', array(
    'label' => 'Firma',
    'options' => $companies,
    'inline_create' => array(
        'enabled' => true,
        'target_module' => 'companies',
    ),
));
$company_select->render();

// Company form also has branch select with inline create
$branch_select = new SAW_Component_Select_Create('branch_id', array(
    'label' => 'Pobočka',
    'options' => $branches,
    'inline_create' => array(
        'enabled' => true,
        'target_module' => 'branches',
    ),
));
$branch_select->render();
```

Z-index is automatically calculated: Visit (1000) → Company (1100) → Branch (1200)

### Conditional Inline Create

Enable inline create only for certain conditions:

```php
<?php
$can_create_companies = current_user_can('create_companies');

$select = new SAW_Component_Select_Create('company_id', array(
    'label' => 'Firma',
    'options' => $companies,
    'inline_create' => array(
        'enabled' => $can_create_companies,
        'target_module' => 'companies',
    ),
));
$select->render();
?>
```

---

## JavaScript API

### Global Namespace

```javascript
window.SAWSelectCreate
```

### Methods

#### `handleInlineSuccess(data, targetField)`

Called after successful inline creation to update dropdown.

**Parameters:**
- `data` (Object) - Server response data
  - `data.id` (number) - ID of created record
  - `data.name` (string) - Display name
- `targetField` (string) - Name of target select field

**Example:**
```javascript
window.SAWSelectCreate.handleInlineSuccess(
    { id: 123, name: 'New Company Ltd.' },
    'company_id'
);
```

#### `closeNested($nested)`

Closes a nested sidebar with animation.

**Parameters:**
- `$nested` (jQuery) - Nested sidebar element

**Example:**
```javascript
const $nested = $('.saw-sidebar[data-is-nested="1"]').last();
window.SAWSelectCreate.closeNested($nested);
```

---

## CSS Classes

### Component Classes

| Class | Description |
|-------|-------------|
| `.saw-select-create-component` | Main component wrapper |
| `.saw-select-create-wrapper` | Flexbox container for select + button |
| `.saw-select-create-select` | The select element |
| `.saw-inline-create-btn` | Inline create button |
| `.saw-field-updated` | Applied during highlight animation |

### Nested Sidebar Classes

| Attribute | Description |
|-----------|-------------|
| `data-is-nested="1"` | Marks sidebar as nested |
| `data-target-field="field_name"` | Stores target field identifier |

---

## Troubleshooting

### New Option Not Appearing

**Problem:** After creating record, dropdown doesn't update.

**Solution:** Ensure `get_display_name()` is implemented in target controller:

```php
protected function get_display_name($item) {
    return $item['name'] ?? '';
}
```

### Z-Index Issues

**Problem:** Nested sidebar appears behind parent.

**Solution:** Check that `calculateZIndex()` in JS is working correctly. Debug:

```javascript
console.log('Sidebar count:', $('.saw-sidebar').length);
console.log('Calculated z-index:', baseZIndex + (count * 100));
```

### Form Redirects Instead of AJAX

**Problem:** Nested form submits normally and redirects.

**Solution:** Ensure hidden field and AJAX handler are added to form template:

```php
<?php if ($is_nested): ?>
    <input type="hidden" name="_ajax_inline_create" value="1">
    <script>/* AJAX handler */</script>
<?php endif; ?>
```

### Prefill Not Working

**Problem:** Context values not being prefilled.

**Solution:** Check that SAW_Context has the requested values:

```php
var_dump(SAW_Context::get_branch_id());
var_dump(SAW_Context::get_customer_id());
```

---

## Browser Support

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

**IE11:** Not supported (uses modern ES6+ JavaScript)

---

## Performance Considerations

### Caching

- Component assets are cached via WordPress enqueue system
- AJAX responses are not cached (always fresh data)

### Loading

- CSS/JS are enqueued globally via Asset Manager
- No performance impact for unused components (minified assets)

---

## Security

### Nonce Verification

All AJAX requests use WordPress nonce verification:

```javascript
data: {
    action: 'saw_load_nested_sidebar',
    nonce: sawGlobal.nonce
}
```

### Permission Checks

Backend validates user permissions before loading nested forms:

```php
if (!$this->can('create')) {
    wp_send_json_error(array('message' => 'Nemáte oprávnění'));
}
```

### Data Sanitization

All prefill values are sanitized:

```php
foreach ($prefill as $key => $value) {
    $form_item[$key] = sanitize_text_field($value);
}
```

---

## Changelog

### Version 1.0.0 (2025-01-15)
- Initial release
- Core inline create functionality
- Nested sidebar support
- Context prefill system
- Z-index management
- AJAX integration
- Responsive design

---

## Credits

**Author:** SAW Visitors Team  
**License:** Proprietary  
**Support:** Internal documentation only

---

## Related Components

- **SAW_Component_Selectbox** - Standard select dropdown
- **SAW_Component_Admin_Table** - Table with sidebar forms
- **SAW_Component_Search** - Search functionality

---

## Future Enhancements

Planned features for future versions:

- 🔄 Multi-select support with inline create
- 🔍 Search integration in nested forms
- 📋 Copy fields from selected record to new
- 🎨 Customizable button styling
- 🌐 i18n/l10n support for button text
- ⚡ Performance optimization for large option lists
