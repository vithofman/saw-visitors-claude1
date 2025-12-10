<?php
/**
 * Calendar Module Configuration
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar
 * @version     1.2.0 - ADDED: custom_ajax_actions for AJAX registration
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    // =========================================
    // ZÁKLADNÍ NASTAVENÍ
    // =========================================
    'entity' => 'calendar',
    'singular' => 'Kalendář',
    'plural' => 'Kalendář návštěv',
    'route' => 'calendar',
    'icon' => '📅',
    
    // =========================================
    // OPRÁVNĚNÍ (používá visits permissions)
    // =========================================
    'permissions' => [
        'list' => ['super_admin', 'admin', 'super_manager', 'manager'],
        'view' => ['super_admin', 'admin', 'super_manager', 'manager'],
        'create' => ['super_admin', 'admin', 'super_manager', 'manager'],
        'edit' => ['super_admin', 'admin', 'super_manager'],
        'delete' => ['super_admin', 'admin'],
    ],
    
    // =========================================
    // CUSTOM AJAX ACTIONS
    // =========================================
    'custom_ajax_actions' => [
        'saw_calendar_events' => 'ajax_get_events',
        'saw_calendar_event_details' => 'ajax_get_event_details',
        'saw_calendar_update_event' => 'ajax_update_event',
    ],
    
    // =========================================
    // NASTAVENÍ KALENDÁŘE
    // =========================================
    'calendar' => [
        'default_view' => 'dayGridMonth',
        'first_day' => 1, // Pondělí
        'slot_min_time' => '06:00:00',
        'slot_max_time' => '22:00:00',
        'slot_duration' => '00:30:00',
        
        // Barvy podle stavu
        'status_colors' => [
            'draft' => [
                'background' => '#94a3b8',
                'border' => '#64748b',
                'text' => '#ffffff',
            ],
            'pending' => [
                'background' => '#f59e0b',
                'border' => '#d97706',
                'text' => '#ffffff',
            ],
            'confirmed' => [
                'background' => '#3b82f6',
                'border' => '#2563eb',
                'text' => '#ffffff',
            ],
            'in_progress' => [
                'background' => '#f97316',
                'border' => '#ea580c',
                'text' => '#ffffff',
            ],
            'completed' => [
                'background' => '#6b7280',
                'border' => '#4b5563',
                'text' => '#ffffff',
            ],
            'cancelled' => [
                'background' => '#ef4444',
                'border' => '#dc2626',
                'text' => '#ffffff',
            ],
        ],
        
        // Barvy podle typu návštěvy
        'type_colors' => [
            'planned' => [
                'background' => '#3b82f6',
                'border' => '#2563eb',
            ],
            'walk_in' => [
                'background' => '#f59e0b',
                'border' => '#d97706',
            ],
        ],
    ],
    
    // =========================================
    // PŘEKLADY
    // =========================================
    'translations' => [
        // Stavy
        'status_draft' => 'Koncept',
        'status_pending' => 'Čekající',
        'status_confirmed' => 'Potvrzená',
        'status_in_progress' => 'Probíhá',
        'status_completed' => 'Dokončená',
        'status_cancelled' => 'Zrušená',
        
        // Typy
        'type_planned' => 'Plánovaná',
        'type_walk_in' => 'Neplánovaná',
        
        // UI
        'loading' => 'Načítání...',
        'error_loading' => 'Chyba při načítání událostí',
        'event_moved' => 'Návštěva byla přesunuta',
        'no_events' => 'Žádné události',
        
        // Filtry
        'filter_all_statuses' => 'Všechny stavy',
        'filter_all_types' => 'Všechny typy',
        
        // Akce
        'new_visit' => 'Nová návštěva',
        'view_detail' => 'Detail',
        'edit' => 'Upravit',
    ],
    
    // =========================================
    // FILTRY (bez pobočky - ta je z context)
    // =========================================
    'filters' => [
        'status' => [
            'type' => 'select',
            'label' => 'Stav',
            'options' => [
                '' => 'Všechny stavy',
                'draft' => 'Koncept',
                'pending' => 'Čekající',
                'confirmed' => 'Potvrzená',
                'in_progress' => 'Probíhá',
                'completed' => 'Dokončená',
                'cancelled' => 'Zrušená',
            ],
        ],
        'visit_type' => [
            'type' => 'select',
            'label' => 'Typ návštěvy',
            'options' => [
                '' => 'Všechny typy',
                'planned' => 'Plánovaná',
                'walk_in' => 'Neplánovaná',
            ],
        ],
    ],
    
    // =========================================
    // CACHE
    // =========================================
    'cache' => [
        'enabled' => false, // Calendar data should be real-time
    ],
];
