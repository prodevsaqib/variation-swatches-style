<?php
/**
 * Central, cached accessor for the plugin's stored settings. Loaded on both
 * the front end and admin, since the swatch renderer needs these values too.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OHVS_Settings {

	const OPTION_KEY = 'ohvs_settings';

	/**
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'dropdown_threshold'             => 3,
			'apply_threshold_to_color'       => 'yes',
			'color_shape'                    => 'circle', // circle|square
			'button_shape'                   => 'rounded', // rounded|square
			'swatch_size'                    => 'medium', // small|medium|large
			'accent_color'                   => '#111111',
			'show_selected_label'            => 'yes',
			'enable_out_of_stock_indicator'  => 'yes',
			'out_of_stock_style'             => 'diagonal', // diagonal|faded|hide
			'show_tooltip'                   => 'yes',
			'excluded_attributes'            => '',
		);
	}

	/**
	 * All settings, merged with defaults.
	 *
	 * @return array
	 */
	public static function get_all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION_KEY, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * A single setting value.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::get_all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Clear the in-memory cache (used right after saving).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * The "excluded_attributes" setting, parsed into a lowercase, trimmed array.
	 *
	 * @return array
	 */
	public static function get_excluded_attributes() {
		$raw = (string) self::get( 'excluded_attributes' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $name ) {
						return strtolower( trim( $name ) );
					},
					explode( ',', $raw )
				)
			)
		);
	}
}
