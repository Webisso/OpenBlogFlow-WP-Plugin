<?php
/**
 * Plugin options and API create-post defaults.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_Plugin_Settings
 */
class OpenBlogFlow_Plugin_Settings {

	const OPTION_DEFAULT_STATUS   = 'openblogflow_default_post_status';
	const OPTION_DEFAULT_CATEGORY = 'openblogflow_default_category_id';

	/**
	 * Post statuses allowed for API-created posts.
	 *
	 * @return string[]
	 */
	public static function allowed_post_statuses() {
		return array( 'publish', 'draft', 'pending', 'private' );
	}

	/**
	 * Labels for post status select fields.
	 *
	 * @return array<string, string>
	 */
	public static function get_post_status_choices() {
		return array(
			''        => __( '— WordPress default (draft) —', 'openblogflow' ),
			'publish' => __( 'Published', 'openblogflow' ),
			'draft'   => __( 'Draft', 'openblogflow' ),
			'pending' => __( 'Pending review', 'openblogflow' ),
			'private' => __( 'Private', 'openblogflow' ),
		);
	}

	/**
	 * Sanitize default post status option.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_default_status( $value ) {
		$value = sanitize_key( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( in_array( $value, self::allowed_post_statuses(), true ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Sanitize default category option.
	 *
	 * @param mixed $value Submitted category ID.
	 * @return int
	 */
	public static function sanitize_default_category( $value ) {
		$category_id = absint( $value );

		if ( 0 === $category_id ) {
			return 0;
		}

		$term = get_term( $category_id, 'category' );

		if ( ! $term || is_wp_error( $term ) ) {
			return 0;
		}

		return $category_id;
	}

	/**
	 * Resolve post status for create: request value, then admin default, then draft.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return string
	 */
	public static function resolve_post_status( WP_REST_Request $request ) {
		$allowed = self::allowed_post_statuses();
		$status  = $request->get_param( 'status' );

		if ( null !== $status && '' !== $status ) {
			$status = sanitize_key( (string) $status );

			if ( in_array( $status, $allowed, true ) ) {
				return $status;
			}
		}

		$default = get_option( self::OPTION_DEFAULT_STATUS, '' );

		if ( is_string( $default ) && '' !== $default && in_array( $default, $allowed, true ) ) {
			return $default;
		}

		return 'draft';
	}

	/**
	 * Resolve categories for create: request list, then admin default category, else none.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array|null
	 */
	public static function resolve_categories( WP_REST_Request $request ) {
		$categories = $request->get_param( 'categories' );

		if ( ! empty( $categories ) && is_array( $categories ) ) {
			return $categories;
		}

		$default_category = (int) get_option( self::OPTION_DEFAULT_CATEGORY, 0 );

		if ( $default_category > 0 ) {
			$term = get_term( $default_category, 'category' );

			if ( $term && ! is_wp_error( $term ) ) {
				return array( $default_category );
			}
		}

		return null;
	}
}
