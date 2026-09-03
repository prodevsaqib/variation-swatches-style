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

		// --- AJAX reset ------------------------------------------------------

		function setToggle($form, field, value) {
			$form.find('[name$="[' + field + ']"]').prop('checked', 'yes' === value);
		}

		function setRadio($form, field, value) {
			$form.find('input[name$="[' + field + ']"][value="' + value + '"]').prop('checked', true);
		}

		function populateForm($form, settings) {
			$form.find('#ohvs_dropdown_threshold').val(settings.dropdown_threshold);
			$form.find('#ohvs_excluded_attributes').val(settings.excluded_attributes);

			setToggle($form, 'apply_threshold_to_color', settings.apply_threshold_to_color);
			setToggle($form, 'show_selected_label', settings.show_selected_label);
			setToggle($form, 'show_tooltip', settings.show_tooltip);
			setToggle($form, 'enable_out_of_stock_indicator', settings.enable_out_of_stock_indicator);

			setRadio($form, 'color_shape', settings.color_shape);
			setRadio($form, 'button_shape', settings.button_shape);
			setRadio($form, 'swatch_size', settings.swatch_size);
			setRadio($form, 'out_of_stock_style', settings.out_of_stock_style);

			$('#ohvs_accent_color').wpColorPicker('color', settings.accent_color);

			applyPreview();
		}

		$(document).on('click', '#ohvs-reset-settings', function (e) {
			e.preventDefault();

			if (!window.confirm(settingsData.resetConfirmText)) {
				return;
			}

			var $btn = $(this);
			var originalText = $btn.text();

			$btn.addClass('disabled').text(settingsData.resettingText);

			$.post(settingsData.ajaxUrl, {
				action: 'ohvs_reset_settings',
				nonce: settingsData.nonce,
			})
				.done(function (response) {
					if (response && response.success) {
						populateForm($('.ohvs-settings-form'), response.data.settings);
						showToast('success', (response.data && response.data.message) || settingsData.resetText);
					} else {
						showToast('error', (response && response.data && response.data.message) || settingsData.resetErrorText);
					}
				})
				.fail(function () {
					showToast('error', settingsData.resetErrorText);
				})
				.always(function () {
					$btn.removeClass('disabled').text(originalText);
				});
		});
	}
});
