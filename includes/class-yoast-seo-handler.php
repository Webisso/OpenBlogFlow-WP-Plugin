<?php
/**
 * Yoast SEO integration for post create/read.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_Yoast_SEO_Handler
 */
class OpenBlogFlow_Yoast_SEO_Handler {

	/**
	 * Check whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Meta', false );
	}

	/**
	 * Normalize metaKeywords from comma-separated string or array.
	 *
	 * @param mixed $value Request value.
	 * @return string[]
	 */
	public static function normalize_keywords( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		if ( is_string( $value ) ) {
			$parts = explode( ',', $value );
		} elseif ( is_array( $value ) ) {
			$parts = $value;
		} else {
			return array();
		}

		$keywords = array();

		foreach ( $parts as $part ) {
			$keyword = sanitize_text_field( trim( (string) $part ) );

			if ( '' !== $keyword ) {
				$keywords[] = $keyword;
			}
		}

		return array_values( array_unique( $keywords ) );
	}

	/**
	 * Apply SEO fields from API request to a post.
	 *
	 * @param int             $post_id Post ID.
	 * @param WP_REST_Request $request REST request.
	 */
	public static function apply_to_post( $post_id, WP_REST_Request $request ) {
		$meta_title = OpenBlogFlow_Request_Params::get_meta_title( $request );
		$meta_desc  = OpenBlogFlow_Request_Params::get_meta_description( $request );
		$keywords   = self::normalize_keywords( OpenBlogFlow_Request_Params::get_meta_keywords( $request ) );

		if ( self::is_active() ) {
			if ( ! empty( $meta_title ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $meta_title ) );
			}

			if ( ! empty( $meta_desc ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $meta_desc ) );
			}

			if ( ! empty( $keywords ) ) {
				self::save_yoast_keywords( $post_id, $keywords );
			}

			return;
		}

		if ( ! empty( $meta_title ) ) {
			update_post_meta( $post_id, '_openblogflow_meta_title', sanitize_text_field( $meta_title ) );
		}

		if ( ! empty( $meta_desc ) ) {
			update_post_meta( $post_id, '_openblogflow_meta_description', sanitize_textarea_field( $meta_desc ) );
		}

		if ( ! empty( $keywords ) ) {
			update_post_meta( $post_id, '_openblogflow_meta_keywords', implode( ', ', $keywords ) );
		}
	}

	/**
	 * Save keywords to Yoast SEO meta fields.
	 *
	 * @param int      $post_id  Post ID.
	 * @param string[] $keywords Keyword list.
	 */
	private static function save_yoast_keywords( $post_id, array $keywords ) {
		update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keywords[0] );

		if ( self::supports_multiple_focus_keywords() && count( $keywords ) > 1 ) {
			$focus_keywords = array();

			foreach ( $keywords as $keyword ) {
				$focus_keywords[] = array(
					'keyword'  => $keyword,
					'score'    => 0,
					'position' => 0,
				);
			}

			update_post_meta( $post_id, '_yoast_wpseo_focuskeywords', wp_json_encode( $focus_keywords ) );
		}
	}

	/**
	 * Whether Yoast SEO Premium multiple focus keywords are available.
	 *
	 * @return bool
	 */
	private static function supports_multiple_focus_keywords() {
		return defined( 'WPSEO_PREMIUM_VERSION' ) || class_exists( 'WPSEO_Premium', false );
	}

	/**
	 * Format SEO fields for API response.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public static function format_for_response( WP_Post $post ) {
		if ( self::is_active() ) {
			$keywords = self::get_stored_keywords( $post->ID );

			return array(
				'seoUrl'          => $post->post_name,
				'metaTitle'       => (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true ),
				'metaDescription' => (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ),
				'metaKeywords'    => implode( ', ', $keywords ),
			);
		}

		return array(
			'seoUrl'          => $post->post_name,
			'metaTitle'       => (string) get_post_meta( $post->ID, '_openblogflow_meta_title', true ),
			'metaDescription' => (string) get_post_meta( $post->ID, '_openblogflow_meta_description', true ),
			'metaKeywords'    => (string) get_post_meta( $post->ID, '_openblogflow_meta_keywords', true ),
		);
	}

	/**
	 * Read stored Yoast focus keywords for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function get_stored_keywords( $post_id ) {
		if ( self::supports_multiple_focus_keywords() ) {
			$raw = get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true );

			if ( ! empty( $raw ) ) {
				$decoded = json_decode( $raw, true );

				if ( is_array( $decoded ) ) {
					$keywords = array();

					foreach ( $decoded as $item ) {
						if ( is_array( $item ) && ! empty( $item['keyword'] ) ) {
							$keywords[] = sanitize_text_field( $item['keyword'] );
						}
					}

					if ( ! empty( $keywords ) ) {
						return $keywords;
					}
				}
			}
		}

		$focus_kw = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );

		if ( ! empty( $focus_kw ) ) {
			return self::normalize_keywords( $focus_kw );
		}

		return array();
	}
}
