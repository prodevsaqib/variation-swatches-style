/* global jQuery */
jQuery(function ($) {
	'use strict';

	var $root = $(':root');

	function applyPreview() {
		var colorShape = $('input[name$="[color_shape]"]:checked').val();
		var buttonShape = $('input[name$="[button_shape]"]:checked').val();
		var swatchSize = $('input[name$="[swatch_size]"]:checked').val();
		var accent = $('#ohvs_accent_color').val() || '#111111';

		$('.ohvs-preview-swatch--color').toggleClass('shape-square', 'square' === colorShape);
		$('.ohvs-preview-swatch--button').toggleClass('shape-square', 'square' === buttonShape);

		$('.ohvs-preview-swatch')
			.removeClass('size-small size-medium size-large')
			.addClass('size-' + (swatchSize || 'medium'));

		$root.get(0).style.setProperty('--ohvs-preview-accent', accent);
	}

	$('#ohvs_accent_color').wpColorPicker({
		change: function () {
			// wpColorPicker updates the input value asynchronously; defer a tick.
			setTimeout(applyPreview, 10);
		},
		clear: applyPreview,
	});

	$(document).on('change', '.ohvs-preview-input', applyPreview);

	applyPreview();

	// --- Toast notifications ---------------------------------------------

	var $toastContainer = $('<div class="ohvs-toast-container" aria-live="polite"></div>').appendTo('body');

	function showToast(type, message) {
		var $toast = $('<div class="ohvs-toast ohvs-toast--' + type + '"></div>');
		var icon = 'success' === type ? '✓' : '✕';

		$toast.append('<span class="ohvs-toast-icon">' + icon + '</span>');
		$toast.append('<span class="ohvs-toast-message"></span>').find('.ohvs-toast-message').text(message);
		$toast.append('<button type="button" class="ohvs-toast-close" aria-label="Dismiss">✕</button>');

		$toastContainer.append($toast);

		// Force reflow so the enter transition plays.
		// eslint-disable-next-line no-unused-expressions
		$toast.get(0).offsetHeight;
		$toast.addClass('is-visible');

		var dismiss = function () {
			$toast.removeClass('is-visible');
			setTimeout(function () {
				$toast.remove();
			}, 200);
		};

		$toast.find('.ohvs-toast-close').on('click', dismiss);
		setTimeout(dismiss, 4000);
	}

	// --- AJAX save ---------------------------------------------------------

	var settingsData = window.ohvsAdmin || null;

	if (settingsData) {
		$(document).on('submit', '.ohvs-settings-form', function (e) {
			e.preventDefault();

			var $form = $(this);
			var $submit = $form.find('.ohvs-save-bar .button-primary');
			var originalText = $submit.val();

			$submit.prop('disabled', true).val(settingsData.savingText);

			$.post(settingsData.ajaxUrl, $form.serialize() + '&action=ohvs_save_settings&nonce=' + encodeURIComponent(settingsData.nonce))
				.done(function (response) {
					if (response && response.success) {
						showToast('success', (response.data && response.data.message) || settingsData.savedText);
					} else {
						showToast('error', (response && response.data && response.data.message) || settingsData.errorText);
					}
				})
				.fail(function () {
					showToast('error', settingsData.errorText);
				})
				.always(function () {
					$submit.prop('disabled', false).val(originalText);
				});
		});
	}
});
