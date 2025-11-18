<?php
/**
 * Training Languages List Template
 *
 * @package SAW_Visitors
 * @version 3.1.0 - FIXED: Added Title + URLs for Sidebar/Edit/Create
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Příprava konfigurace pro tabulku
$table_config = $config; // Načte základ z config.php

// 2. 🔥 CRITICAL: Doplnění URL adres a Nadpisu pro komponentu
$base_url = home_url('/admin/' . ($config['route'] ?? 'training-languages'));

$table_config['title']      = $config['plural']; // Nadpis (Jazyky školení)
$table_config['create_url'] = $base_url . '/create'; // Tlačítko "Přidat nový"
$table_config['detail_url'] = $base_url . '/{id}/'; // Kliknutí na řádek (Sidebar)
$table_config['edit_url']   = $base_url . '/{id}/edit'; // Tlačítko Edit (Tužka)

// 3. Konfigurace sloupců
$table_config['columns'] = [
    'flag_emoji' => [
        'label' => 'Vlajka',
        'type' => 'custom',
        'width' => '60px',
        'align' => 'center',
        'callback' => function($value) {
            return '<span style="font-size: 24px;">' . esc_html($value) . '</span>';
        }
    ],
    'language_name' => [
        'label' => 'Název jazyka',
        'type' => 'custom',
        'sortable' => true,
        'class' => 'saw-table-cell-bold',
        'callback' => function($value, $item) {
            $html = esc_html($value);
            // Check for Czech (protected)
            if (($item['language_code'] ?? '') === 'cs') {
                $html .= ' <span class="saw-badge saw-badge-info">Povinný</span>';
            }
            return $html;
        }
    ],
    'language_code' => [
        'label' => 'Kód',
        'type' => 'custom',
        'width' => '80px',
        'align' => 'center',
        'sortable' => true,
        'callback' => function($value) {
            return '<code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600; color:#475569;">' . esc_html(strtoupper($value)) . '</code>';
        }
    ],
    'branches_count' => [
        'label' => 'Aktivní pobočky',
        'type' => 'custom',
        'align' => 'center',
        'width' => '150px',
        'callback' => function($value) {
            $count = intval($value);
            if ($count > 0) {
                return '<span class="saw-badge saw-badge-success">' . $count . '</span>';
            } else {
                return '<span class="saw-text-muted" style="color:#cbd5e1;">—</span>';
            }
        }
    ],
    'created_at' => [
        'label' => 'Vytvořeno',
        'type' => 'date',
        'width' => '120px',
        'sortable' => true,
        'format' => 'd.m.Y'
    ]
];

// 4. Data a stránkování
$table_config['rows'] = $items;
$table_config['total_items'] = $total;
$table_config['current_page'] = $page;
$table_config['total_pages'] = $total_pages;
$table_config['search_value'] = $search;
$table_config['orderby'] = $orderby;
$table_config['order'] = $order;

// 5. Zapnutí funkcí
$table_config['enable_search'] = true;
$table_config['search_placeholder'] = 'Hledat jazyk...';
$table_config['enable_filters'] = false; // Zatím žádné filtry nejsou potřeba

// 6. Sidebar kontext (předává se z controlleru)
$table_config['sidebar_mode'] = $sidebar_mode;
$table_config['detail_item'] = $detail_item ?? null;
$table_config['form_item'] = $form_item ?? null;
$table_config['detail_tab'] = $detail_tab;
$table_config['module_config'] = $config; 

// 7. Akce v tabulce
$table_config['actions'] = ['view', 'edit', 'delete'];
$table_config['add_new'] = 'Nový jazyk';

// 8. Renderování
$table = new SAW_Component_Admin_Table($entity, $table_config);
$table->render();
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    jQuery(document).on('click', '.saw-action-delete', function(e) {
        const row = jQuery(this).closest('tr');
        // Hledáme buňku s kódem (3. sloupec)
        const codeCell = row.find('td:nth-child(3)').text().trim().toLowerCase();
        
        if (codeCell === 'cs') {
            e.preventDefault();
            e.stopImmediatePropagation();
            alert('Češtinu nelze smazat, je to výchozí jazyk systému.');
            return false;
        }
    });
});
</script>