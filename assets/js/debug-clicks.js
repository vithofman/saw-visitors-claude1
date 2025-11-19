/**
 * Debug Click Tracker
 * Logs ALL clicks to help diagnose event handler issues
 */

(function ($) {
    'use strict';

    console.log('🐛 DEBUG CLICK TRACKER LOADED');

    // Global click interceptor - HIGHEST PRIORITY
    $(document).on('click', function (e) {
        const $target = $(e.target);
        const $closest = $target.closest('a, tr, button');

        if ($closest.length) {
            const tag = $closest.prop('tagName');
            const classes = $closest.attr('class') || 'no-classes';
            const href = $closest.attr('href') || 'no-href';
            const dataId = $closest.data('id') || 'no-id';

            console.warn('🐛 GLOBAL CLICK:', {
                tag: tag,
                classes: classes,
                href: href,
                dataId: dataId,
                hasDetailUrl: $closest.data('detail-url') ? 'YES' : 'NO',
                defaultPrevented: e.isDefaultPrevented(),
                propagationStopped: e.isPropagationStopped()
            });
        }
    });

    // Specific table row listener
    $(document).on('click', '.saw-admin-table tbody tr', function (e) {
        console.error('🐛 TABLE ROW CLICKED (specific listener)');
        console.error('🐛 Row data-id:', $(this).data('id'));
        console.error('🐛 Row data-detail-url:', $(this).data('detail-url'));
    });

})(jQuery);
