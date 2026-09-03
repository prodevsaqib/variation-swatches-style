/* global jQuery */
jQuery(function ($) {
	'use strict';

	var settings = window.ohvsSettings || { showSelectedLabel: true, enableOosIndicator: true };

	function syncSwatchesFromSelect($select) {
		var $group = $select.next('.ohvs-swatches');

		if (!$group.length) {
			return;
		}

		var val = $select.val();

		$group.find('.ohvs-swatch').each(function () {
			var $swatch = $(this);
			$swatch.toggleClass('selected', val !== '' && String($swatch.data('value')) === String(val));
		});
	}

	// Show the currently selected value next to the attribute label, e.g. "Flavor : Blueberry Muffin".
	function updateSelectedLabel($select) {
		if (!settings.showSelectedLabel) {
			return;
		}

		var id = $select.attr('id');

		if (!id) {
			return;
		}

		var $label = $('label[for="' + id + '"]');

		if (!$label.length) {
			return;
		}

		var $value = $label.find('.ohvs-selected-value');

		if (!$value.length) {
			$label.append('<span class="ohvs-label-sep"> : </span><span class="ohvs-selected-value"></span>');
			$value = $label.find('.ohvs-selected-value');
		}

		var val = $select.val();
		var text = val ? $select.find('option:selected').text() : '';

		$value.text(text);
		$label.toggleClass('ohvs-has-value', text !== '');
	}

	function syncDisabledState($select) {
		var $group = $select.next('.ohvs-swatches');

		if (!$group.length) {
			return;
		}

		$select.find('option').each(function () {
			var $option = $(this);
			var val = $option.attr('value');

			if (val === '') {
				return;
			}

			$group.find('.ohvs-swatch').each(function () {
				var $swatch = $(this);
				if (String($swatch.data('value')) === String(val)) {
					$swatch.toggleClass('disabled', $option.is(':disabled'));
				}
			});
		});
	}

	// Read every attribute currently chosen in the form, keyed by select name (e.g. "attribute_color").
	function getChosenAttributes($form) {
		var chosen = {};

		$form.find('select[name^="attribute_"]').each(function () {
			var $select = $(this);
			chosen[$select.attr('name')] = ($select.val() || '').toString();
		});

		return chosen;
	}

	// Whether a variation is compatible with the given chosen attributes (empty variation
	// attribute values are wildcards, same as WooCommerce's own matching logic).
	function variationMatchesChosen(variation, chosen, ignoreAttrName) {
		for (var attrName in chosen) {
			if (!chosen.hasOwnProperty(attrName) || attrName === ignoreAttrName) {
				continue;
			}

			var chosenVal = chosen[attrName];

			if (!chosenVal) {
				continue;
			}

			var variationVal = variation.attributes[attrName];

			if (variationVal && variationVal.toString().toLowerCase() !== chosenVal.toLowerCase()) {
				return false;
			}
		}

		return true;
	}

	// Cross out swatches whose value, combined with whatever else is already selected,
	// only ever matches out-of-stock variations.
	function updateStockCrosses($form) {
		if (!settings.enableOosIndicator) {
			return;
		}

		var variations = $form.data('product_variations');

		if (!variations || !variations.length) {
			return;
		}

		var chosen = getChosenAttributes($form);

		$form.find('.ohvs-swatches').each(function () {
			var $group = $(this);
			var $select = $group.prev('select.ohvs-native-select');

			if (!$select.length) {
				return;
			}

			var attrName = $select.attr('name');

			$group.find('.ohvs-swatch').each(function () {
				var $swatch = $(this);
				var value = String($swatch.data('value'));
				var candidateChosen = $.extend({}, chosen);

				candidateChosen[attrName] = value;

				var anyMatch = false;
				var anyInStock = false;

				for (var i = 0; i < variations.length; i++) {
					var variation = variations[i];
					var variationVal = variation.attributes[attrName];

					if (variationVal && variationVal.toString().toLowerCase() !== value.toLowerCase()) {
						continue;
					}

					if (!variationMatchesChosen(variation, candidateChosen, attrName)) {
						continue;
					}

					anyMatch = true;

					if (variation.is_in_stock) {
						anyInStock = true;
						break;
					}
				}

				$swatch.toggleClass('oos', anyMatch && !anyInStock);
			});
		});
	}

	// Click a swatch: drive the hidden <select> so WooCommerce's own variation logic runs.
	$(document).on('click', '.ohvs-swatch', function (e) {
		e.preventDefault();

		var $swatch = $(this);

		if ($swatch.hasClass('disabled') || $swatch.hasClass('oos')) {
			return;
		}

		var $group = $swatch.closest('.ohvs-swatches');
		var $select = $group.prev('select.ohvs-native-select');

		if (!$select.length) {
			return;
		}

		var value = $swatch.data('value');

		// Clicking the already-selected swatch clears the selection.
		if ($swatch.hasClass('selected')) {
			$select.val('').trigger('change');
			return;
		}

		$select.val(value).trigger('change');
	});

	// Keep swatch UI in sync whenever the underlying select changes,
	// whether from our own click handler or WooCommerce's "Clear" link.
	$(document).on('change', 'select.ohvs-native-select', function () {
		syncSwatchesFromSelect($(this));
		updateSelectedLabel($(this));
		updateStockCrosses($(this).closest('.variations_form'));
	});

	// WooCommerce fires this after checking stock/price for the current combination;
	// use it to gray out swatches whose matching <option> became disabled.
	$(document).on('woocommerce_update_variation_values', function (e) {
		$(e.target).find('select.ohvs-native-select').each(function () {
			syncDisabledState($(this));
		});
	});

	// Initial state (e.g. default attribute selections, or values restored from the URL).
	$('.variations_form').each(function () {
		var $form = $(this);

		$form.find('select.ohvs-native-select').each(function () {
			syncSwatchesFromSelect($(this));
			updateSelectedLabel($(this));
		});

		updateStockCrosses($form);
	});
});
