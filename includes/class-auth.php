<?php
/**
 * Bearer token authentication for REST API requests.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_Auth
 */
class OpenBlogFlow_Auth {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'authenticate_request' ), 100 );
	}

	/**
	 * Validate Bearer token on OpenBlogFlow API routes.
	 *
	 * @param WP_Error|null|true $result Authentication result from other handlers.
	 * @return WP_Error|null|true
	 */
	public static function authenticate_request( $result ) {
		if ( ! self::is_openblogflow_request() ) {
			return $result;
		}

		if ( is_wp_error( $result ) && 'rest_not_logged_in' !== $result->get_error_code() ) {
			return $result;
		}

		$stored_token = get_option( 'openblogflow_bearer_token', '' );

		if ( empty( $stored_token ) ) {
			return new WP_Error(
				'openblogflow_token_not_configured',
				__( 'API Bearer token is not configured. Please set it in OpenBlogFlow settings.', 'openblogflow' ),
				array( 'status' => 503 )
			);
		}

		$provided_token = self::get_bearer_token_from_request();

		if ( empty( $provided_token ) ) {
			return new WP_Error(
				'openblogflow_missing_token',
				__( 'Authorization header with Bearer token is required.', 'openblogflow' ),
				array( 'status' => 401 )
			);
		}

		if ( ! hash_equals( $stored_token, $provided_token ) ) {
			return new WP_Error(
				'openblogflow_invalid_token',
				__( 'Invalid Bearer token.', 'openblogflow' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check if the current request targets OpenBlogFlow API.
	 *
	 * @return bool
	 */
	private static function is_openblogflow_request() {
		$rest_route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
			? $GLOBALS['wp']->query_vars['rest_route']
			: '';

		if ( empty( $rest_route ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
			if ( $path && false !== strpos( $path, '/wp-json/' . OPENBLOGFLOW_API_NAMESPACE ) ) {
				return true;
			}
		}

		return 0 === strpos( $rest_route, '/' . OPENBLOGFLOW_API_NAMESPACE );
	}

	/**
	 * Extract Bearer token from Authorization header.
	 *
	 * @return string
	 */
	public static function get_bearer_token_from_request() {
		$header = self::get_authorization_header();

		if ( empty( $header ) ) {
			return '';
		}

		if ( preg_match( '/Bearer\s+(\S+)/i', $header, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Read Authorization header across server environments.
	 *
	 * @return string
	 */
	private static function get_authorization_header() {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			foreach ( $headers as $key => $value ) {
				if ( 'authorization' === strtolower( $key ) ) {
					return sanitize_text_field( $value );
				}
			}
		}

		return '';
	}

	/**
	 * Generate a cryptographically secure random token.
	 *
	 * @param int $length Token length in bytes (output is hex, so 2x characters).
	 * @return string
	 */
	public static function generate_token( $length = 32 ) {
		if ( function_exists( 'random_bytes' ) ) {
			return bin2hex( random_bytes( $length ) );
		}

		return wp_generate_password( $length * 2, false, false );
	}
}
