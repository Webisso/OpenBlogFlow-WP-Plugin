<?php
/**
 * Media library upload and image URL replacement helpers.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_Media_Handler
 */
class OpenBlogFlow_Media_Handler {

	/**
	 * Upload an image from URL or base64 data URI and attach to a post.
	 *
	 * @param string   $source   Remote URL or base64 data URI.
	 * @param int      $post_id  Parent post ID.
	 * @param string   $filename Optional filename override.
	 * @return int|WP_Error Attachment ID or error.
	 */
	public static function upload_image( $source, $post_id = 0, $filename = '' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( self::is_base64_image( $source ) ) {
			return self::upload_base64_image( $source, $post_id, $filename );
		}

		if ( ! filter_var( $source, FILTER_VALIDATE_URL ) ) {
			return new WP_Error(
				'openblogflow_invalid_image_source',
				__( 'Invalid image source. Provide a valid URL or base64 data URI.', 'openblogflow' )
			);
		}

		$tmp = download_url( $source );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$parsed_name = ! empty( $filename ) ? $filename : basename( wp_parse_url( $source, PHP_URL_PATH ) );
		if ( empty( $parsed_name ) || false === strpos( $parsed_name, '.' ) ) {
			$parsed_name = 'openblogflow-' . wp_generate_uuid4() . '.jpg';
		}

		$file_array = array(
			'name'     => sanitize_file_name( $parsed_name ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Process all <img> tags in HTML content and replace external sources with media library URLs.
	 *
	 * @param string $content HTML content.
	 * @param int    $post_id Parent post ID.
	 * @return string|WP_Error Updated content or error.
	 */
	public static function process_content_images( $content, $post_id ) {
		if ( empty( $content ) || false === stripos( $content, '<img' ) ) {
			return $content;
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error(
				'openblogflow_dom_missing',
				__( 'DOMDocument extension is required to process inline images.', 'openblogflow' )
			);
		}

		$internal_errors = libxml_use_internal_errors( true );

		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$wrapped = '<?xml encoding="UTF-8"><div id="obf-root">' . $content . '</div>';

		$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		$images = $dom->getElementsByTagName( 'img' );

		if ( 0 === $images->length ) {
			return $content;
		}

		/** @var DOMElement $img */
		foreach ( $images as $img ) {
			$src = $img->getAttribute( 'src' );

			if ( empty( $src ) || self::is_local_media_url( $src ) ) {
				continue;
			}

			$attachment_id = self::upload_image( $src, $post_id );

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			$new_url = wp_get_attachment_url( $attachment_id );

			if ( $new_url ) {
				$img->setAttribute( 'src', esc_url_raw( $new_url ) );

				$srcset = wp_get_attachment_image_srcset( $attachment_id, 'full' );
				if ( $srcset ) {
					$img->setAttribute( 'srcset', $srcset );
				}
			}
		}

		$root = $dom->getElementById( 'obf-root' );

		if ( ! $root ) {
			return $content;
		}

		$updated = '';
		foreach ( $root->childNodes as $child ) {
			$updated .= $dom->saveHTML( $child );
		}

		return $updated;
	}

	/**
	 * Check whether a URL already points to this site's uploads directory.
	 *
	 * @param string $url Image URL.
	 * @return bool
	 */
	private static function is_local_media_url( $url ) {
		$upload_dir = wp_upload_dir();
		$site_url   = home_url();

		return 0 === strpos( $url, $upload_dir['baseurl'] ) || 0 === strpos( $url, $site_url );
	}

	/**
	 * Detect base64-encoded image data URI.
	 *
	 * @param string $source Image source string.
	 * @return bool
	 */
	private static function is_base64_image( $source ) {
		return 0 === strpos( $source, 'data:image/' );
	}

	/**
	 * Upload a base64 data URI image to the media library.
	 *
	 * @param string $data_uri Base64 data URI.
	 * @param int    $post_id  Parent post ID.
	 * @param string $filename Optional filename.
	 * @return int|WP_Error
	 */
	private static function upload_base64_image( $data_uri, $post_id, $filename = '' ) {
		if ( ! preg_match( '/^data:image\/(\w+);base64,(.+)$/', $data_uri, $matches ) ) {
			return new WP_Error(
				'openblogflow_invalid_base64',
				__( 'Invalid base64 image data.', 'openblogflow' )
			);
		}

		$extension = strtolower( $matches[1] );
		$allowed   = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' );

		if ( ! in_array( $extension, $allowed, true ) ) {
			return new WP_Error(
				'openblogflow_unsupported_image_type',
				__( 'Unsupported image type in base64 data.', 'openblogflow' )
			);
		}

		$decoded = base64_decode( $matches[2], true );

		if ( false === $decoded ) {
			return new WP_Error(
				'openblogflow_base64_decode_failed',
				__( 'Failed to decode base64 image data.', 'openblogflow' )
			);
		}

		$name = ! empty( $filename ) ? $filename : 'openblogflow-' . wp_generate_uuid4() . '.' . $extension;

		$tmp = wp_tempnam( $name );

		if ( ! $tmp ) {
			return new WP_Error(
				'openblogflow_temp_file_failed',
				__( 'Could not create temporary file for image upload.', 'openblogflow' )
			);
		}

		file_put_contents( $tmp, $decoded );

		$file_array = array(
			'name'     => sanitize_file_name( $name ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return $attachment_id;
		}

		return $attachment_id;
	}
}
