<?php
/**
 * Calendar Module Configuration
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar
 * @version     1.3.0 - ADDED: Mobile AJAX handlers (saw_calendar_days_with_events, saw_calendar_day_events)
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
        // Desktop (FullCalendar)
        'saw_calendar_events' => 'ajax_get_events',
        'saw_calendar_event_details' => 'ajax_get_event_details',
        'saw_calendar_update_event' => 'ajax_update_event',
        
        // Mobile (Mini Calendar + Agenda)
        'saw_calendar_days_with_events' => 'ajax_get_days_with_events',
        'saw_calendar_day_events' => 'ajax_get_day_events',
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
        
        // Barvy podle statusu
        'status_colors' => [
            'draft'       => ['background' => '#94a3b8', 'border' => '#64748b', 'text' => '#ffffff'],
            'pending'     => ['background' => '#f59e0b', 'border' => '#d97706', 'text' => '#ffffff'],
            'confirmed'   => ['background' => '#3b82f6', 'border' => '#2563eb', 'text' => '#ffffff'],
            'in_progress' => ['background' => '#f97316', 'border' => '#ea580c', 'text' => '#ffffff'],
            'completed'   => ['background' => '#6b7280', 'border' => '#4b5563', 'text' => '#ffffff'],
            'cancelled'   => ['background' => '#ef4444', 'border' => '#dc2626', 'text' => '#ffffff'],
        ],
        
        // Barvy podle typu
        'type_colors' => [
            'planned' => '#3b82f6',
            'walk_in' => '#10b981',
        ],
    ],
    
    // =========================================
    // FILTRY
    // =========================================
    'filters' => [
        'status' => [
            'label' => 'Status',
            'type' => 'select',
            'options' => [
                '' => 'Všechny',
                'pending' => 'Čekající',
                'confirmed' => 'Potvrzené',
                'in_progress' => 'Probíhající',
                'completed' => 'Dokončené',
                'cancelled' => 'Zrušené',
            ],
        ],
        'type' => [
            'label' => 'Typ',
            'type' => 'select',
            'options' => [
                '' => 'Všechny',
                'planned' => 'Plánované',
                'walk_in' => 'Neplánované',
            ],
        ],
    ],
];