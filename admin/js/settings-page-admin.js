(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	// AI Spam Check functionality
	$(function() {
		
		// Handle AI toggle button change
		$(document).on('change', '.maspik-ai-toggle-wrap input[type="checkbox"]', function() {
			var isChecked = $(this).is(':checked');
			var configFields = $('#maspik-ai-config-fields');
			
			if (isChecked) {
				configFields.slideDown(300);
			} else {
				configFields.slideUp(300);
			}
		});

		// Initialize AI config fields visibility on page load
		$(document).ready(function() {
			var aiToggle = $('.maspik-ai-toggle-wrap input[type="checkbox"]');
			var configFields = $('#maspik-ai-config-fields');
			
			if (aiToggle.is(':checked')) {
				configFields.show();
			} else {
				configFields.hide();
			}
		});

		// Handle accordion functionality for AI section
		$(document).on('click', '#ai-spam-check-accordion', function() {
			var content = $('#maspik-ai-spam-check');
			var arrow = $(this).find('.dashicons-arrow-right');
			
			if (content.hasClass('active')) {
				content.removeClass('active').slideUp(300);
				arrow.removeClass('rotated');
			} else {
				content.addClass('active').slideDown(300);
				arrow.addClass('rotated');
			}
		});

		// Handle Generate New Secret button
		$(document).on('click', '#generate-new-secret', function() {
			var button = $(this);
			var input = $('#maspik_ai_client_secret');
			
			// Disable button and show loading
			button.prop('disabled', true).text('Generating...');
			
			// Make AJAX call to generate new secret
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'maspik_generate_ai_secret',
					nonce: maspik_ajax.nonce
				},
				success: function(response) {
					// Clear any previous feedback message.
					$('.maspik-secret-feedback').remove();

					if (response.success) {
						input.val(response.data.secret);
						button.after('<span class="maspik-secret-feedback maspik-secret-feedback--success">✓ New secret generated!</span>');
					} else {
						var errMsg = (response.data && typeof response.data === 'string') ? response.data : 'Error generating secret.';
						button.after('<span class="maspik-secret-feedback maspik-secret-feedback--error">✗ ' + errMsg + '</span>');
					}
					setTimeout(function() { $('.maspik-secret-feedback').fadeOut(400, function () { $(this).remove(); }); }, 3500);
				},
				error: function() {
					$('.maspik-secret-feedback').remove();
					button.after('<span class="maspik-secret-feedback maspik-secret-feedback--error">✗ Server error. Please try again.</span>');
					setTimeout(function() { $('.maspik-secret-feedback').fadeOut(400, function () { $(this).remove(); }); }, 3500);
				},
				complete: function() {
					button.prop('disabled', false).text('Generate New');
				}
			});
		});

	});

})( jQuery );
