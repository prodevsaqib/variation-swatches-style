<?php
/**
 * "Swatches Style" admin page under WooCommerce, where a shop manager controls
 * how variation swatches look and behave on the front end.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OHVS_Settings_Page {

	const PAGE_SLUG = 'ohvs-swatches-style';

	/**
	 * Singleton instance.
	 *
	 * @var OHVS_Settings_Page
	 */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ohvs_reset_settings', array( $this, 'handle_reset' ) );
		add_action( 'wp_ajax_ohvs_save_settings', array( $this, 'ajax_save' ) );
	}

	/**
	 * Add "Swatches Style" under the WooCommerce admin menu.
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Swatches Style', 'onlinehub-variation-swatches' ),
			__( 'Swatches Style', 'onlinehub-variation-swatches' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting + sanitizer with the Settings API.
	 */
	public function register_setting() {
		register_setting(
			'ohvs_settings_group',
			OHVS_Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => OHVS_Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitize posted settings.
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = OHVS_Settings::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		$output['dropdown_threshold'] = isset( $input['dropdown_threshold'] )
			? max( 2, min( 20, absint( $input['dropdown_threshold'] ) ) )
			: $defaults['dropdown_threshold'];

		$output['apply_threshold_to_color'] = ! empty( $input['apply_threshold_to_color'] ) ? 'yes' : 'no';

		$output['color_shape'] = in_array( $input['color_shape'] ?? '', array( 'circle', 'square' ), true )
			? $input['color_shape']
			: $defaults['color_shape'];

		$output['button_shape'] = in_array( $input['button_shape'] ?? '', array( 'rounded', 'square' ), true )
			? $input['button_shape']
			: $defaults['button_shape'];

		$output['swatch_size'] = in_array( $input['swatch_size'] ?? '', array( 'small', 'medium', 'large' ), true )
			? $input['swatch_size']
			: $defaults['swatch_size'];

		$accent = isset( $input['accent_color'] ) ? sanitize_hex_color( wp_unslash( $input['accent_color'] ) ) : '';
		$output['accent_color'] = $accent ? $accent : $defaults['accent_color'];

		$output['show_selected_label']           = ! empty( $input['show_selected_label'] ) ? 'yes' : 'no';
		$output['enable_out_of_stock_indicator'] = ! empty( $input['enable_out_of_stock_indicator'] ) ? 'yes' : 'no';

		$output['out_of_stock_style'] = in_array( $input['out_of_stock_style'] ?? '', array( 'diagonal', 'faded', 'hide' ), true )
			? $input['out_of_stock_style']
			: $defaults['out_of_stock_style'];

		$output['show_tooltip'] = ! empty( $input['show_tooltip'] ) ? 'yes' : 'no';

		$output['excluded_attributes'] = isset( $input['excluded_attributes'] )
			? sanitize_text_field( wp_unslash( $input['excluded_attributes'] ) )
			: '';

		OHVS_Settings::flush_cache();

		return $output;
	}

	/**
	 * AJAX handler for saving settings without a full page reload.
	 * Reuses the same sanitize() logic the Settings API (options.php) path uses,
	 * so both paths stay in sync.
	 */
	public function ajax_save() {
		check_ajax_referer( 'ohvs_ajax_save', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'onlinehub-variation-swatches' ) ), 403 );
		}

		$key   = OHVS_Settings::OPTION_KEY;
		$posted = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		$sanitized = $this->sanitize( $posted );

		update_option( $key, $sanitized );
		OHVS_Settings::flush_cache();

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'onlinehub-variation-swatches' ) ) );
	}

	/**
	 * Reset all settings back to their defaults.
	 */
	public function handle_reset() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'onlinehub-variation-swatches' ) );
		}

		check_admin_referer( 'ohvs_reset_settings', 'ohvs_reset_nonce' );

		delete_option( OHVS_Settings::OPTION_KEY );
		OHVS_Settings::flush_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'reset' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue the settings-page-only admin assets.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'ohvs-admin-settings', OHVS_URL . 'assets/css/admin-settings.css', array( 'wp-color-picker', 'dashicons' ), OHVS_VERSION );
		wp_enqueue_script( 'ohvs-admin-settings', OHVS_URL . 'assets/js/admin-settings.js', array( 'jquery', 'wp-color-picker' ), OHVS_VERSION, true );

		wp_localize_script(
			'ohvs-admin-settings',
			'ohvsAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ohvs_ajax_save' ),
				'optionKey'    => OHVS_Settings::OPTION_KEY,
				'savingText'   => __( 'Saving…', 'onlinehub-variation-swatches' ),
				'savedText'    => __( 'Settings saved', 'onlinehub-variation-swatches' ),
				'errorText'    => __( 'Could not save settings. Please try again.', 'onlinehub-variation-swatches' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = OHVS_Settings::get_all();
		$key      = OHVS_Settings::OPTION_KEY;

		if ( isset( $_GET['reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings reset to defaults.', 'onlinehub-variation-swatches' ) . '</p></div>';
		}
		?>
		<div class="wrap ohvs-settings-wrap">

			<div class="ohvs-settings-header">
				<div class="ohvs-settings-header-icon" aria-hidden="true">
					<span class="dashicons dashicons-art"></span>
				</div>
				<div class="ohvs-settings-header-text">
					<h1>
						<?php esc_html_e( 'Swatches Style', 'onlinehub-variation-swatches' ); ?>
						<span class="ohvs-version-pill">v<?php echo esc_html( OHVS_VERSION ); ?></span>
					</h1>
					<p><?php esc_html_e( 'Control how variation attributes render on the single product page — color swatches, buttons, and the dropdown fallback.', 'onlinehub-variation-swatches' ); ?></p>
				</div>
			</div>

			<?php settings_errors(); ?>

			<form method="post" action="options.php" class="ohvs-settings-form">
				<?php settings_fields( 'ohvs_settings_group' ); ?>

				<div class="ohvs-settings-grid">
					<div class="ohvs-settings-main">

						<div class="ohvs-card">
							<div class="ohvs-card-header">
								<span class="dashicons dashicons-editor-justify" aria-hidden="true"></span>
								<div>
									<h2><?php esc_html_e( 'Dropdown fallback', 'onlinehub-variation-swatches' ); ?></h2>
									<p><?php esc_html_e( 'Once an attribute has more options than this, it renders as a styled dropdown instead of swatches.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label for="ohvs_dropdown_threshold"><?php esc_html_e( 'Option limit', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'Attributes with more values than this become a dropdown.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<input type="number" min="2" max="20" id="ohvs_dropdown_threshold" name="<?php echo esc_attr( $key ); ?>[dropdown_threshold]" value="<?php echo esc_attr( $settings['dropdown_threshold'] ); ?>" class="ohvs-number-input" />
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label for="ohvs_apply_threshold_to_color"><?php esc_html_e( 'Apply to color attributes', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'When off, color attributes always render as swatches, no matter how many options they have.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<label class="ohvs-toggle">
										<input type="checkbox" id="ohvs_apply_threshold_to_color" name="<?php echo esc_attr( $key ); ?>[apply_threshold_to_color]" value="1" <?php checked( 'yes', $settings['apply_threshold_to_color'] ); ?> />
										<span class="ohvs-toggle-track"><span class="ohvs-toggle-thumb"></span></span>
									</label>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label for="ohvs_excluded_attributes"><?php esc_html_e( 'Excluded attributes', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'Comma-separated attribute names to leave as the plain WooCommerce dropdown, e.g. "Length, Material".', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<input type="text" id="ohvs_excluded_attributes" name="<?php echo esc_attr( $key ); ?>[excluded_attributes]" value="<?php echo esc_attr( $settings['excluded_attributes'] ); ?>" class="ohvs-text-input" placeholder="<?php esc_attr_e( 'e.g. Length, Material', 'onlinehub-variation-swatches' ); ?>" />
								</div>
							</div>
						</div>

						<div class="ohvs-card">
							<div class="ohvs-card-header">
								<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
								<div>
									<h2><?php esc_html_e( 'Appearance', 'onlinehub-variation-swatches' ); ?></h2>
									<p><?php esc_html_e( 'Shapes, size, and accent color used across every swatch on the site.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label><?php esc_html_e( 'Color swatch shape', 'onlinehub-variation-swatches' ); ?></label>
								</div>
								<div class="ohvs-field-control">
									<div class="ohvs-segmented" data-role="color-shape">
										<input type="radio" id="ohvs_color_shape_circle" name="<?php echo esc_attr( $key ); ?>[color_shape]" value="circle" class="ohvs-preview-input" <?php checked( 'circle', $settings['color_shape'] ); ?> />
										<label for="ohvs_color_shape_circle"><?php esc_html_e( 'Circle', 'onlinehub-variation-swatches' ); ?></label>

										<input type="radio" id="ohvs_color_shape_square" name="<?php echo esc_attr( $key ); ?>[color_shape]" value="square" class="ohvs-preview-input" <?php checked( 'square', $settings['color_shape'] ); ?> />
										<label for="ohvs_color_shape_square"><?php esc_html_e( 'Square', 'onlinehub-variation-swatches' ); ?></label>
									</div>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label><?php esc_html_e( 'Button shape', 'onlinehub-variation-swatches' ); ?></label>
								</div>
								<div class="ohvs-field-control">
									<div class="ohvs-segmented" data-role="button-shape">
										<input type="radio" id="ohvs_button_shape_rounded" name="<?php echo esc_attr( $key ); ?>[button_shape]" value="rounded" class="ohvs-preview-input" <?php checked( 'rounded', $settings['button_shape'] ); ?> />
										<label for="ohvs_button_shape_rounded"><?php esc_html_e( 'Rounded', 'onlinehub-variation-swatches' ); ?></label>

										<input type="radio" id="ohvs_button_shape_square" name="<?php echo esc_attr( $key ); ?>[button_shape]" value="square" class="ohvs-preview-input" <?php checked( 'square', $settings['button_shape'] ); ?> />
										<label for="ohvs_button_shape_square"><?php esc_html_e( 'Square', 'onlinehub-variation-swatches' ); ?></label>
									</div>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label><?php esc_html_e( 'Swatch size', 'onlinehub-variation-swatches' ); ?></label>
								</div>
								<div class="ohvs-field-control">
									<div class="ohvs-segmented" data-role="swatch-size">
										<input type="radio" id="ohvs_swatch_size_small" name="<?php echo esc_attr( $key ); ?>[swatch_size]" value="small" class="ohvs-preview-input" <?php checked( 'small', $settings['swatch_size'] ); ?> />
										<label for="ohvs_swatch_size_small"><?php esc_html_e( 'Small', 'onlinehub-variation-swatches' ); ?></label>

										<input type="radio" id="ohvs_swatch_size_medium" name="<?php echo esc_attr( $key ); ?>[swatch_size]" value="medium" class="ohvs-preview-input" <?php checked( 'medium', $settings['swatch_size'] ); ?> />
										<label for="ohvs_swatch_size_medium"><?php esc_html_e( 'Medium', 'onlinehub-variation-swatches' ); ?></label>

										<input type="radio" id="ohvs_swatch_size_large" name="<?php echo esc_attr( $key ); ?>[swatch_size]" value="large" class="ohvs-preview-input" <?php checked( 'large', $settings['swatch_size'] ); ?> />
										<label for="ohvs_swatch_size_large"><?php esc_html_e( 'Large', 'onlinehub-variation-swatches' ); ?></label>
									</div>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label for="ohvs_accent_color"><?php esc_html_e( 'Accent color', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'Used for the selected-swatch highlight.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<input type="text" id="ohvs_accent_color" name="<?php echo esc_attr( $key ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="ohvs-color-field ohvs-preview-input" data-default-color="#111111" />
								</div>
							</div>
						</div>

						<div class="ohvs-card">
							<div class="ohvs-card-header">
								<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
								<div>
									<h2><?php esc_html_e( 'Behavior', 'onlinehub-variation-swatches' ); ?></h2>
									<p><?php esc_html_e( 'Fine-tune what shows up alongside the swatches themselves.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label for="ohvs_show_selected_label"><?php esc_html_e( 'Selected value label', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'Show the selected value next to the attribute name, e.g. "Color : Gold".', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<label class="ohvs-toggle">
										<input type="checkbox" id="ohvs_show_selected_label" name="<?php echo esc_attr( $key ); ?>[show_selected_label]" value="1" <?php checked( 'yes', $settings['show_selected_label'] ); ?> />
										<span class="ohvs-toggle-track"><span class="ohvs-toggle-thumb"></span></span>
									</label>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label for="ohvs_show_tooltip"><?php esc_html_e( 'Color name tooltip', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'Show the color name in a native tooltip when hovering a color swatch.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<label class="ohvs-toggle">
										<input type="checkbox" id="ohvs_show_tooltip" name="<?php echo esc_attr( $key ); ?>[show_tooltip]" value="1" <?php checked( 'yes', $settings['show_tooltip'] ); ?> />
										<span class="ohvs-toggle-track"><span class="ohvs-toggle-thumb"></span></span>
									</label>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label for="ohvs_enable_out_of_stock_indicator"><?php esc_html_e( 'Out of stock indicator', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'Mark swatches whose combination is out of stock.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<label class="ohvs-toggle">
										<input type="checkbox" id="ohvs_enable_out_of_stock_indicator" name="<?php echo esc_attr( $key ); ?>[enable_out_of_stock_indicator]" value="1" <?php checked( 'yes', $settings['enable_out_of_stock_indicator'] ); ?> />
										<span class="ohvs-toggle-track"><span class="ohvs-toggle-thumb"></span></span>
									</label>
								</div>
							</div>

							<div class="ohvs-field-row">
								<div class="ohvs-field-label">
									<label><?php esc_html_e( 'Out of stock style', 'onlinehub-variation-swatches' ); ?></label>
									<p><?php esc_html_e( 'How an out-of-stock swatch is shown, when the indicator above is on.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
								<div class="ohvs-field-control">
									<div class="ohvs-segmented" data-role="oos-style">
										<input type="radio" id="ohvs_oos_style_diagonal" name="<?php echo esc_attr( $key ); ?>[out_of_stock_style]" value="diagonal" <?php checked( 'diagonal', $settings['out_of_stock_style'] ); ?> />
										<label for="ohvs_oos_style_diagonal"><?php esc_html_e( 'Diagonal line', 'onlinehub-variation-swatches' ); ?></label>

										<input type="radio" id="ohvs_oos_style_faded" name="<?php echo esc_attr( $key ); ?>[out_of_stock_style]" value="faded" <?php checked( 'faded', $settings['out_of_stock_style'] ); ?> />
										<label for="ohvs_oos_style_faded"><?php esc_html_e( 'Faded', 'onlinehub-variation-swatches' ); ?></label>

										<input type="radio" id="ohvs_oos_style_hide" name="<?php echo esc_attr( $key ); ?>[out_of_stock_style]" value="hide" <?php checked( 'hide', $settings['out_of_stock_style'] ); ?> />
										<label for="ohvs_oos_style_hide"><?php esc_html_e( 'Hide', 'onlinehub-variation-swatches' ); ?></label>
									</div>
								</div>
							</div>
						</div>

					</div>

					<div class="ohvs-settings-side">
						<div class="ohvs-card ohvs-preview-card">
							<div class="ohvs-card-header">
								<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
								<div>
									<h2><?php esc_html_e( 'Live preview', 'onlinehub-variation-swatches' ); ?></h2>
									<p><?php esc_html_e( 'Updates as you change shapes and color.', 'onlinehub-variation-swatches' ); ?></p>
								</div>
							</div>

							<div class="ohvs-preview-block">
								<span class="ohvs-preview-block-label"><?php esc_html_e( 'Color', 'onlinehub-variation-swatches' ); ?></span>
								<div class="ohvs-preview-row">
									<span class="ohvs-preview-swatch ohvs-preview-swatch--color selected"></span>
									<span class="ohvs-preview-swatch ohvs-preview-swatch--color" style="background:#2b6cb0"></span>
									<span class="ohvs-preview-swatch ohvs-preview-swatch--color" style="background:#c53030"></span>
								</div>
							</div>

							<div class="ohvs-preview-block">
								<span class="ohvs-preview-block-label"><?php esc_html_e( 'Buttons', 'onlinehub-variation-swatches' ); ?></span>
								<div class="ohvs-preview-row">
									<span class="ohvs-preview-swatch ohvs-preview-swatch--button selected">S</span>
									<span class="ohvs-preview-swatch ohvs-preview-swatch--button">M</span>
									<span class="ohvs-preview-swatch ohvs-preview-swatch--button">L</span>
								</div>
							</div>
						</div>

						<div class="ohvs-card ohvs-tips-card">
							<div class="ohvs-card-header">
								<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
								<div>
									<h2><?php esc_html_e( 'How it decides', 'onlinehub-variation-swatches' ); ?></h2>
								</div>
							</div>
							<ul>
								<li><?php esc_html_e( 'A "Color"/"Colour" attribute renders as color swatches.', 'onlinehub-variation-swatches' ); ?></li>
								<li><?php esc_html_e( 'Any other attribute renders as button swatches.', 'onlinehub-variation-swatches' ); ?></li>
								<li><?php esc_html_e( 'Once one attribute exceeds the option limit, every attribute on that product becomes a dropdown.', 'onlinehub-variation-swatches' ); ?></li>
							</ul>
						</div>

						<div class="ohvs-card ohvs-resources-card">
							<div class="ohvs-card-header">
								<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
								<div>
									<h2><?php esc_html_e( 'Plugin', 'onlinehub-variation-swatches' ); ?></h2>
								</div>
							</div>
							<p class="ohvs-resources-line"><?php esc_html_e( 'Author:', 'onlinehub-variation-swatches' ); ?> <a href="https://github.com/prodevsaqib" target="_blank" rel="noopener noreferrer">Muhammad Saqib</a></p>
							<p class="ohvs-resources-line"><?php esc_html_e( 'Version:', 'onlinehub-variation-swatches' ); ?> <?php echo esc_html( OHVS_VERSION ); ?></p>

							<?php
							$reset_url = wp_nonce_url(
								add_query_arg( 'action', 'ohvs_reset_settings', admin_url( 'admin-post.php' ) ),
								'ohvs_reset_settings',
								'ohvs_reset_nonce'
							);
							?>
							<div class="ohvs-reset-form">
								<a href="<?php echo esc_url( $reset_url ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( 'Reset all Swatches Style settings to their defaults?', 'onlinehub-variation-swatches' ) ); ?>');"><?php esc_html_e( 'Reset to Defaults', 'onlinehub-variation-swatches' ); ?></a>
							</div>
						</div>
					</div>
				</div>

				<div class="ohvs-save-bar">
					<?php submit_button( __( 'Save Changes', 'onlinehub-variation-swatches' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}
}
