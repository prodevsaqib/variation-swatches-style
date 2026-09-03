<?php
/**
 * Core class: renders custom swatch/dropdown markup for variation attribute selectors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OHVS_Swatches {

	/**
	 * Per-request cache of color maps, keyed by product id.
	 *
	 * @var array
	 */
	protected $color_map_cache = array();

	/**
	 * Per-request cache of out-of-stock option values, keyed by "product id|attribute".
	 *
	 * @var array
	 */
	protected $stock_map_cache = array();

	/**
	 * Per-request cache of the "does this product need dropdowns" check, keyed by product id.
	 *
	 * @var array
	 */
	protected $force_select_cache = array();

	/**
	 * Singleton instance.
	 *
	 * @var OHVS_Swatches
	 */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render_swatches' ), 10, 2 );
	}

	/**
	 * Enqueue front-end assets on single product pages only.
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$settings = OHVS_Settings::get_all();

		wp_enqueue_style( 'ohvs-swatches', OHVS_URL . 'assets/css/swatches.css', array(), OHVS_VERSION );

		$accent = sanitize_hex_color( $settings['accent_color'] );
		wp_add_inline_style( 'ohvs-swatches', ':root{--ohvs-accent: ' . esc_attr( $accent ? $accent : '#111111' ) . ';}' );

		wp_enqueue_script( 'ohvs-swatches', OHVS_URL . 'assets/js/swatches.js', array( 'jquery' ), OHVS_VERSION, true );

		wp_localize_script(
			'ohvs-swatches',
			'ohvsSettings',
			array(
				'showSelectedLabel' => 'yes' === $settings['show_selected_label'],
				'enableOosIndicator' => 'yes' === $settings['enable_out_of_stock_indicator'],
			)
		);
	}

	/**
	 * Replace WooCommerce's variation attribute <select> HTML with swatches
	 * (color circles / button swatches) or a styled dropdown.
	 *
	 * @param string $html Original <select> markup built by wc_dropdown_variation_attribute_options().
	 * @param array  $args Arguments passed to wc_dropdown_variation_attribute_options().
	 * @return string
	 */
	public function render_swatches( $html, $args ) {
		if ( empty( $args['product'] ) || ! ( $args['product'] instanceof WC_Product ) || empty( $args['attribute'] ) ) {
			return $html;
		}

		$product   = $args['product'];
		$attribute = $args['attribute'];
		$options   = ! empty( $args['options'] ) ? $args['options'] : array();

		if ( empty( $options ) ) {
			$attributes = $product->get_variation_attributes();
			$options    = isset( $attributes[ $attribute ] ) ? $attributes[ $attribute ] : array();
		}

		$options = array_values( array_filter( array_map( 'trim', (array) $options ), 'strlen' ) );

		if ( count( $options ) < 2 ) {
			return $html;
		}

		if ( $this->is_excluded_attribute( $attribute ) ) {
			return $html;
		}

		$is_taxonomy = taxonomy_exists( $attribute );

		$selected_key = 'attribute_' . sanitize_title( $attribute );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = isset( $_REQUEST[ $selected_key ] ) ? wc_clean( wp_unslash( $_REQUEST[ $selected_key ] ) ) : $product->get_variation_default_attribute( $attribute );

		$is_color = $this->is_color_attribute( $attribute );
		$settings = OHVS_Settings::get_all();

		// If any attribute on this product has more options than the configured limit,
		// every attribute on that product is rendered as a dropdown, for a consistent UI
		// (unless color attributes are explicitly excluded from that rule in Settings).
		if ( $is_color && 'no' === $settings['apply_threshold_to_color'] ) {
			$type = 'color';
		} elseif ( $this->product_needs_dropdowns( $product ) ) {
			$type = 'select';
		} elseif ( $is_color ) {
			$type = 'color';
		} else {
			$type = 'button';
		}

		// Product-wide dropdown mode: keep the native <select>, just style it.
		if ( 'select' === $type ) {
			return '<span class="ohvs-select-wrap">' . str_replace( '<select ', '<select class="ohvs-native-select ohvs-styled-select" ', $html ) . '</span>';
		}

		$color_map        = $is_color ? $this->get_color_map( $product, $attribute, $is_taxonomy ) : array();
		$admin_color_map  = $is_color ? $this->get_admin_color_map( $product ) : array();
		$out_of_stock_map = 'yes' === $settings['enable_out_of_stock_indicator'] ? $this->get_out_of_stock_options( $product, $attribute ) : array();

		$shape = 'color' === $type ? $settings['color_shape'] : $settings['button_shape'];

		// Keep the real <select> in the DOM (WooCommerce's variation JS relies on it) but visually hidden.
		$hidden_select = str_replace( '<select ', '<select class="ohvs-native-select ohvs-hidden-select" ', $html );

		$container_classes = array(
			'ohvs-swatches',
			'ohvs-swatches--' . $type,
			'ohvs-shape-' . $shape,
			'ohvs-size-' . $settings['swatch_size'],
			'ohvs-oos-style-' . $settings['out_of_stock_style'],
		);

		$swatches = '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '">';

		foreach ( $options as $option ) {
			$option_value = $option;
			$option_label = $option;

			if ( $is_taxonomy ) {
				$term = get_term_by( 'slug', $option, $attribute );
				if ( $term ) {
					$option_label = $term->name;
				}
			}

			$option_label = apply_filters( 'woocommerce_variation_option_name', $option_label, $is_taxonomy ? ( isset( $term ) ? $term : null ) : null, $attribute, $product );

			$is_selected     = ( '' !== $selected && ( $selected === $option_value || sanitize_title( $selected ) === sanitize_title( $option_value ) ) );
			$is_out_of_stock = $this->matches_option( $out_of_stock_map, $option_label, $option_value );

			$classes = 'ohvs-swatch ohvs-swatch--' . $type;
			$classes .= $is_selected ? ' selected' : '';
			$classes .= $is_out_of_stock ? ' oos' : '';

			if ( 'color' === $type ) {
				// Prefer a color explicitly picked by the shop manager, then a matching color
				// code from importer meta, then fall back to using the option's own name as a
				// CSS color (works for plain names like "Red", "Green").
				$hex = isset( $admin_color_map[ sanitize_title( $option_label ) ] ) ? $admin_color_map[ sanitize_title( $option_label ) ] : '';

				if ( '' === $hex ) {
					$hex = $this->find_color_for_option( $color_map, $option_label, $option_value );
				}

				if ( '' === $hex ) {
					$hex = $option_label;
				}

				$style = ' style="background-color: ' . esc_attr( $this->normalize_color( $hex ) ) . ';"';
				$title = 'yes' === $settings['show_tooltip'] ? ' title="' . esc_attr( $option_label ) . '"' : '';

				$swatches .= '<span class="' . esc_attr( $classes ) . '" data-value="' . esc_attr( $option_value ) . '"' . $title . $style . '></span>';
			} else {
				$swatches .= '<span class="' . esc_attr( $classes ) . '" data-value="' . esc_attr( $option_value ) . '">' . esc_html( $option_label ) . '</span>';
			}
		}

		$swatches .= '</div>';

		return $hidden_select . $swatches;
	}

	/**
	 * Whether any variation attribute on this product has more options than the configured
	 * dropdown threshold. When true, every attribute on the product is rendered as a styled
	 * dropdown, so the product's attribute selectors look consistent rather than mixing
	 * swatches and dropdowns.
	 *
	 * @param WC_Product $product
	 * @return bool
	 */
	protected function product_needs_dropdowns( $product ) {
		$product_id = $product->get_id();

		if ( isset( $this->force_select_cache[ $product_id ] ) ) {
			return $this->force_select_cache[ $product_id ];
		}

		$threshold        = (int) OHVS_Settings::get( 'dropdown_threshold' );
		$needs_dropdowns = false;

		foreach ( $product->get_variation_attributes() as $attribute_options ) {
			$attribute_options = array_values( array_filter( array_map( 'trim', (array) $attribute_options ), 'strlen' ) );

			if ( count( $attribute_options ) > $threshold ) {
				$needs_dropdowns = true;
				break;
			}
		}

		$this->force_select_cache[ $product_id ] = $needs_dropdowns;

		return $needs_dropdowns;
	}

	/**
	 * Whether this attribute is listed in the "Excluded attributes" setting
	 * (matched by its display label, case-insensitive) and should keep the
	 * plain WooCommerce dropdown untouched.
	 *
	 * @param string $attribute Attribute key as passed to wc_dropdown_variation_attribute_options().
	 * @return bool
	 */
	protected function is_excluded_attribute( $attribute ) {
		$excluded = OHVS_Settings::get_excluded_attributes();

		if ( empty( $excluded ) ) {
			return false;
		}

		$label = taxonomy_exists( $attribute ) ? wc_attribute_label( $attribute ) : $attribute;

		return in_array( strtolower( trim( $label ) ), $excluded, true );
	}

	/**
	 * Whether the given attribute (taxonomy name or custom attribute name) represents a color attribute.
	 *
	 * @param string $attribute Attribute key as passed to wc_dropdown_variation_attribute_options().
	 * @return bool
	 */
	protected function is_color_attribute( $attribute ) {
		$name = strtolower( trim( str_replace( 'pa_', '', $attribute ) ) );

		return apply_filters( 'ohvs_is_color_attribute', in_array( $name, array( 'color', 'colour' ), true ), $attribute );
	}

	/**
	 * Build a map of color name => color value (hex/css value) for a product.
	 *
	 * For custom (non-taxonomy) attributes this reads the color meta saved per
	 * variation by the Onlinehub importer (_zilon_color_name / _zilon_color_value).
	 * For taxonomy attributes it reads the "ohvs_color" term meta set on the Color
	 * attribute's term add/edit screen (Products > Attributes > Color > Configure terms).
	 *
	 * @param WC_Product $product
	 * @param string     $attribute
	 * @param bool       $is_taxonomy
	 * @return array
	 */
	protected function get_color_map( $product, $attribute, $is_taxonomy ) {
		$cache_key = $product->get_id() . '|' . $attribute;

		if ( isset( $this->color_map_cache[ $cache_key ] ) ) {
			return $this->color_map_cache[ $cache_key ];
		}

		$map = array();

		if ( $is_taxonomy ) {
			$terms = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );

			foreach ( $terms as $term ) {
				$color = get_term_meta( $term->term_id, 'ohvs_color', true );

				if ( $color ) {
					$map[ $term->name ] = $color;
				}
			}
		} else {
			foreach ( $product->get_children() as $variation_id ) {
				$color_name  = get_post_meta( $variation_id, '_zilon_color_name', true );
				$color_value = get_post_meta( $variation_id, '_zilon_color_value', true );

				if ( $color_name && $color_value && ! isset( $map[ $color_name ] ) ) {
					$map[ $color_name ] = $color_value;
				}
			}
		}

		$this->color_map_cache[ $cache_key ] = $map;

		return $map;
	}

	/**
	 * Get the color map picked manually by the shop manager (Product Data > Color Swatches tab),
	 * keyed by sanitize_title( option label ) => color value.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	protected function get_admin_color_map( $product ) {
		$map = get_post_meta( $product->get_id(), '_ohvs_color_map', true );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Look up a color value in the color map by option label or raw value (case-insensitive).
	 *
	 * @param array  $color_map
	 * @param string $label
	 * @param string $value
	 * @return string
	 */
	protected function find_color_for_option( $color_map, $label, $value ) {
		foreach ( $color_map as $name => $color ) {
			if ( 0 === strcasecmp( $name, $label ) || 0 === strcasecmp( $name, $value ) ) {
				return $color;
			}
		}

		return '';
	}

	/**
	 * Work out which option values for this attribute have no in-stock variation at all
	 * (regardless of the other selected attributes), so they can be shown crossed-out.
	 *
	 * @param WC_Product $product
	 * @param string     $attribute
	 * @return array List of option values (as stored in variation meta) that are out of stock.
	 */
	protected function get_out_of_stock_options( $product, $attribute ) {
		$cache_key = $product->get_id() . '|' . $attribute;

		if ( isset( $this->stock_map_cache[ $cache_key ] ) ) {
			return $this->stock_map_cache[ $cache_key ];
		}

		$meta_key    = 'attribute_' . sanitize_title( $attribute );
		$seen        = array();
		$has_instock = array();

		foreach ( $product->get_children() as $variation_id ) {
			$value = get_post_meta( $variation_id, $meta_key, true );

			if ( '' === $value ) {
				continue; // "Any <attribute>" variation - not tied to a single option.
			}

			$variation = wc_get_product( $variation_id );

			if ( ! $variation ) {
				continue;
			}

			$seen[ $value ] = true;

			if ( $variation->is_in_stock() ) {
				$has_instock[ $value ] = true;
			}
		}

		$out_of_stock = array_values( array_diff( array_keys( $seen ), array_keys( $has_instock ) ) );

		$this->stock_map_cache[ $cache_key ] = $out_of_stock;

		return $out_of_stock;
	}

	/**
	 * Whether an option (matched by its label or raw value, case-insensitive) is present in a list of values.
	 *
	 * @param array  $values
	 * @param string $label
	 * @param string $value
	 * @return bool
	 */
	protected function matches_option( $values, $label, $value ) {
		foreach ( $values as $stored_value ) {
			if ( 0 === strcasecmp( $stored_value, $label ) || 0 === strcasecmp( $stored_value, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a stored color value into something usable as a CSS background-color.
	 * Adds a leading "#" to bare hex values; passes through named colors / rgb()/hsl() as-is.
	 *
	 * @param string $value
	 * @return string
	 */
	protected function normalize_color( $value ) {
		$value = trim( $value );

		if ( preg_match( '/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return '#' . $value;
		}

		return $value;
	}
}
