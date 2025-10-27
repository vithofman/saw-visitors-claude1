/**
 * SAW Visitors - Admin JavaScript
 * Phase 5: Super Admin WP Menu
 *
 * @package    SAW_Visitors
 * @since      4.6.1
 */

(function($) {
	'use strict';

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function() {
		SAW_Admin.init();
	});

	/**
	 * Main Admin Object
	 */
	var SAW_Admin = {
		
		/**
		 * Initialize
		 */
		init: function() {
			this.initLanguageTabs();
			this.initConfirmations();
			this.initEmailDetailToggle();
			this.initColorPicker();
		},

		/**
		 * Language tabs for content management
		 */
		initLanguageTabs: function() {
			$('.saw-language-tab').on('click', function(e) {
				e.preventDefault();
				
				var targetLang = $(this).data('lang');
				
				// Update tabs
				$('.saw-language-tab').removeClass('active');
				$(this).addClass('active');
				
				// Show/hide content
				$('.saw-language-content').hide();
				$('.saw-language-content[data-lang="' + targetLang + '"]').show();
			});
			
			// Activate first tab by default
			$('.saw-language-tab').first().trigger('click');
		},

		/**
		 * Confirmation dialogs
		 */
		initConfirmations: function() {
			// Delete confirmations
			$('.button-link-delete').on('click', function(e) {
				if (!confirm('Opravdu smazat? Tato akce je nevratná.')) {
					e.preventDefault();
					return false;
				}
			});
			
			// Version reset confirmation
			$('input[name="saw_reset_version"]').on('click', function(e) {
				var reason = $('#reason').val().trim();
				
				if (!reason) {
					e.preventDefault();
					alert('Vyplňte prosím důvod změny verze.');
					$('#reason').focus();
					return false;
				}
				
				if (!confirm('Opravdu resetovat verzi školení? Všichni návštěvníci budou muset absolvovat školení znovu!')) {
					e.preventDefault();
					return false;
				}
			});
		},

		/**
		 * Email detail toggle
		 */
		initEmailDetailToggle: function() {
			$(document).on('click', '.saw-email-detail-toggle', function(e) {
				e.preventDefault();
				var targetId = $(this).data('target');
				$(targetId).slideToggle(200);
			});
		},

		/**
		 * Color picker enhancement
		 */
		initColorPicker: function() {
			$('input[type="color"]').on('change', function() {
				var color = $(this).val();
				$(this).closest('td').find('.color-preview').css('background-color', color);
			});
		}
	};

	/**
	 * AJAX helpers
	 */
	window.SAW_AJAX = {
		
		/**
		 * Generic AJAX request
		 */
		request: function(action, data, successCallback, errorCallback) {
			data.action = action;
			data.nonce = sawVisitorsAdmin.nonce;
			
			$.ajax({
				url: sawVisitorsAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						if (typeof successCallback === 'function') {
							successCallback(response.data);
						}
					} else {
						if (typeof errorCallback === 'function') {
							errorCallback(response.data);
						} else {
							alert('Chyba: ' + (response.data || 'Neznámá chyba'));
						}
					}
				},
				error: function(xhr, status, error) {
					if (typeof errorCallback === 'function') {
						errorCallback(error);
					} else {
						alert('AJAX chyba: ' + error);
					}
				}
			});
		},
		
		/**
		 * Switch customer (if needed via AJAX in future)
		 */
		switchCustomer: function(customerId, callback) {
			this.request('saw_switch_customer', {
				customer_id: customerId
			}, function(data) {
				if (typeof callback === 'function') {
					callback(data);
				} else {
					location.reload();
				}
			});
		}
	};

	/**
	 * Utilities
	 */
	window.SAW_Utils = {
		
		/**
		 * Format date
		 */
		formatDate: function(dateString) {
			var date = new Date(dateString);
			var day = String(date.getDate()).padStart(2, '0');
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var year = date.getFullYear();
			return day + '.' + month + '.' + year;
		},
		
		/**
		 * Format datetime
		 */
		formatDateTime: function(dateString) {
			var date = new Date(dateString);
			var day = String(date.getDate()).padStart(2, '0');
			var month = String(date.getMonth() + 1).padStart(2, '0');
			var year = date.getFullYear();
			var hours = String(date.getHours()).padStart(2, '0');
			var minutes = String(date.getMinutes()).padStart(2, '0');
			return day + '.' + month + '.' + year + ' ' + hours + ':' + minutes;
		},
		
		/**
		 * Show notification
		 */
		showNotification: function(message, type) {
			type = type || 'success';
			
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			
			$('.wrap h1').after($notice);
			
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

})(jQuery);
