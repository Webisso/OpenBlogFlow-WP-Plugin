<?php
/**
 * Resolve API request fields with camelCase and snake_case aliases.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_Request_Params
 */
class OpenBlogFlow_Request_Params {

	/**
	 * Return the first non-empty parameter value from a list of keys.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string[]        $keys    Parameter names in priority order.
	 * @return mixed|null
	 */
	public static function get( WP_REST_Request $request, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! $request->has_param( $key ) ) {
				continue;
			}

			$value = $request->get_param( $key );

			if ( null === $value ) {
				continue;
			}

			if ( is_string( $value ) && '' === trim( $value ) ) {
				continue;
			}

			if ( is_array( $value ) && empty( $value ) ) {
				continue;
			}

			return $value;
		}

		return null;
	}

	/**
	 * Post slug from seoUrl or slug.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return string|null
	 */
	public static function get_post_slug( WP_REST_Request $request ) {
		return self::get( $request, array( 'seoUrl', 'slug' ) );
	}

	/**
	 * Featured image URL or base64 from featured_image or main_image.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return string|null
	 */
	public static function get_featured_image( WP_REST_Request $request ) {
		return self::get( $request, array( 'featured_image', 'main_image' ) );
	}

	/**
	 * SEO meta title.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return string|null
	 */
	public static function get_meta_title( WP_REST_Request $request ) {
		return self::get( $request, array( 'metaTitle', 'meta_title' ) );
	}

	/**
	 * SEO meta description.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return string|null
	 */
	public static function get_meta_description( WP_REST_Request $request ) {
		return self::get( $request, array( 'metaDescription', 'meta_description' ) );
	}

	/**
	 * SEO keywords string or array.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return mixed|null
	 */
	public static function get_meta_keywords( WP_REST_Request $request ) {
		return self::get( $request, array( 'metaKeywords', 'meta_keywords' ) );
	}
}
