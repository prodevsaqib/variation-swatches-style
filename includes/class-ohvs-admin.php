<?php
/**
 * Admin UI: lets a shop manager pick a color code for each value of a
 * "Color" attribute, used to render the front-end color swatches.
 *
 * - Global (taxonomy) "Color" attributes get a color field on the term's own
 *   Add/Edit screen (Products > Attributes > Color > Configure terms), since
 *   the term is shared across every product that uses it.
 * - Custom (per-product) "Color" attributes get their color fields inline in
 *   that attribute's row on the product's Attributes tab, since their values
 *   only exist on that one product.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OHVS_Admin {

	const META_KEY      = '_ohvs_color_map';
	const TERM_META_KEY = 'ohvs_color';

	/**
	 * Singleton instance.
	 *
	 * @var OHVS_Admin
	 */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		add_action( 'woocommerce_after_product_attribute_settings', array( $this, 'render_color_fields' ), 10, 2 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'register_term_color_fields' ), 20 );
	}

	/**
	 * Enqueue the WP color picker wherever a color field of ours might appear:
	 * product edit screens, and the term add/edit screens for color attribute taxonomies.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$is_product_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'product' === $screen->post_type;
		$is_term_screen    = in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) && $this->is_color_taxonomy( $screen->taxonomy );

		if ( ! $is_product_screen && ! $is_term_screen ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'ohvs-admin-color-picker', OHVS_URL . 'assets/css/admin-color-picker.css', array( 'wp-color-picker' ), OHVS_VERSION );
		wp_enqueue_script( 'ohvs-admin-color-picker', OHVS_URL . 'assets/js/admin-color-picker.js', array( 'jquery', 'wp-color-picker' ), OHVS_VERSION, true );
	}

	/**
	 * Register Add/Edit term color fields for every global attribute taxonomy named "Color".
	 */
	public function register_term_color_fields() {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			$label = ! empty( $tax->attribute_label ) ? $tax->attribute_label : $tax->attribute_name;

			if ( ! in_array( strtolower( trim( $label ) ), array( 'color', 'colour' ), true ) ) {
				continue;
			}

			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );

			add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_term_add_field' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_term_edit_field' ) );
			add_action( "created_{$taxonomy}", array( $this, 'save_term_color' ) );
			add_action( "edited_{$taxonomy}", array( $this, 'save_term_color' ) );
		}
	}

	/**
	 * Whether a taxonomy is a global attribute taxonomy labeled "Color"/"Colour".
	 *
	 * @param string $taxonomy
	 * @return bool
	 */
	protected function is_color_taxonomy( $taxonomy ) {
		if ( ! $taxonomy || 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return false;
		}

		return in_array( strtolower( trim( wc_attribute_label( $taxonomy ) ) ), array( 'color', 'colour' ), true );
	}

	/**
	 * "Add new Color" screen field.
	 */
	public function render_term_add_field() {
		?>
		<div class="form-field">
			<label for="ohvs_term_color"><?php esc_html_e( 'Color', 'onlinehub-variation-swatches' ); ?></label>
			<input type="text" class="ohvs-color-picker" id="ohvs_term_color" name="ohvs_term_color" value="" data-default-color="" />
			<p><?php esc_html_e( 'Pick the color shown for this value in the front-end swatches.', 'onlinehub-variation-swatches' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Edit-term screen field.
	 *
	 * @param WP_Term $term
	 */
	public function render_term_edit_field( $term ) {
		$value = get_term_meta( $term->term_id, self::TERM_META_KEY, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="ohvs_term_color"><?php esc_html_e( 'Color', 'onlinehub-variation-swatches' ); ?></label></th>
			<td>
				<input type="text" class="ohvs-color-picker" id="ohvs_term_color" name="ohvs_term_color" value="<?php echo esc_attr( $value ); ?>" data-default-color="" />
				<p class="description"><?php esc_html_e( 'Pick the color shown for this value in the front-end swatches.', 'onlinehub-variation-swatches' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save a term's color (WordPress has already verified the add/edit-tag nonce by the
	 * time created_{taxonomy}/edited_{taxonomy} fire).
	 *
	 * @param int $term_id
	 */
	public function save_term_color( $term_id ) {
		if ( ! isset( $_POST['ohvs_term_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$value = sanitize_text_field( wp_unslash( $_POST['ohvs_term_color'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' !== $value ) {
			update_term_meta( $term_id, self::TERM_META_KEY, $value );
		} else {
			delete_term_meta( $term_id, self::TERM_META_KEY );
		}
	}

	/**
	 * Render a color-picker field for each value of a CUSTOM (non-taxonomy) "Color" attribute,
	 * inline inside that attribute's own row on the product's Attributes tab. Global (taxonomy)
	 * "Color" attributes are handled per-term instead (see register_term_color_fields()) since
	 * their values are shared across products.
	 *
	 * @param WC_Product_Attribute $attribute
	 * @param int                  $i Attribute row index (unused, required by the hook signature).
	 */
	public function render_color_fields( $attribute, $i ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedFunctionParameter
		if ( $attribute->is_taxonomy() ) {
			return;
		}

		$name = strtolower( trim( $attribute->get_name() ) );

		if ( ! in_array( $name, array( 'color', 'colour' ), true ) ) {
			return;
		}

		$options = $attribute->get_options();

		if ( empty( $options ) ) {
			return;
		}

		global $post;

		$color_map = $this->get_saved_map( $post ? $post->ID : 0 );
		?>
		<tr class="ohvs-color-picker-row">
			<td colspan="3">
				<label><?php esc_html_e( 'Color codes (used for the front-end color swatches):', 'onlinehub-variation-swatches' ); ?></label>
				<div class="ohvs-color-picker-grid">
					<?php foreach ( $options as $option ) : ?>
						<?php
						$key   = sanitize_title( $option );
						$value = isset( $color_map[ $key ] ) ? $color_map[ $key ] : '';
						?>
						<span class="ohvs-color-picker-field">
							<span class="ohvs-color-picker-field-label"><?php echo esc_html( $option ); ?></span>
							<input type="text" class="ohvs-color-picker" name="ohvs_color_map[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" data-default-color="" />
						</span>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the posted color map for a custom (per-product) "Color" attribute.
	 *
	 * @param int $post_id
	 */
	public function save( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via WooCommerce's own product-save nonce below.
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( empty( $_POST['ohvs_color_map'] ) || ! is_array( $_POST['ohvs_color_map'] ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		$map = array();

		foreach ( wp_unslash( $_POST['ohvs_color_map'] ) as $key => $value ) {
			$key   = sanitize_title( $key );
			$value = sanitize_text_field( $value );

			if ( '' !== $key && '' !== $value ) {
				$map[ $key ] = $value;
			}
		}

		if ( $map ) {
			update_post_meta( $post_id, self::META_KEY, $map );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Get the saved admin color map for a product's custom "Color" attribute.
	 *
	 * @param int $product_id
	 * @return array
	 */
	public function get_saved_map( $product_id ) {
		$map = get_post_meta( $product_id, self::META_KEY, true );

		return is_array( $map ) ? $map : array();
	}
}
