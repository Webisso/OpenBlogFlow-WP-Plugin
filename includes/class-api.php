<?php
/**
 * REST API route registration and handlers.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_API
 */
class OpenBlogFlow_API {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 */
	public static function register_routes() {
		register_rest_route(
			OPENBLOGFLOW_API_NAMESPACE,
			'/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_get_posts' ),
					'permission_callback' => '__return_true',
					'args'                => self::get_posts_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_create_post' ),
					'permission_callback' => '__return_true',
					'args'                => self::get_create_post_args(),
				),
			)
		);

		register_rest_route(
			OPENBLOGFLOW_API_NAMESPACE,
			'/posts/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_get_post' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && $value > 0;
						},
					),
				),
			)
		);

		register_rest_route(
			OPENBLOGFLOW_API_NAMESPACE,
			'/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_get_categories' ),
				'permission_callback' => '__return_true',
				'args'                => self::get_taxonomy_list_args(),
			)
		);

		register_rest_route(
			OPENBLOGFLOW_API_NAMESPACE,
			'/tags',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_get_tags' ),
				'permission_callback' => '__return_true',
				'args'                => self::get_taxonomy_list_args(),
			)
		);
	}

	/**
	 * GET /posts handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get_posts( WP_REST_Request $request ) {
		$result = OpenBlogFlow_Post_Handler::get_posts( $request );
		return rest_ensure_response( $result );
	}

	/**
	 * GET /posts/{id} handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get_post( WP_REST_Request $request ) {
		$result = OpenBlogFlow_Post_Handler::get_post( $request );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * POST /posts handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create_post( WP_REST_Request $request ) {
		$result = OpenBlogFlow_Post_Handler::create_post( $request );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * GET /categories handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_get_categories( WP_REST_Request $request ) {
		return rest_ensure_response( self::get_taxonomy_terms( 'category', $request ) );
	}

	/**
	 * GET /tags handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_get_tags( WP_REST_Request $request ) {
		return rest_ensure_response( self::get_taxonomy_terms( 'post_tag', $request ) );
	}

	/**
	 * Fetch taxonomy terms with pagination.
	 *
	 * @param string          $taxonomy Taxonomy slug.
	 * @param WP_REST_Request $request  Request.
	 * @return array
	 */
	private static function get_taxonomy_terms( $taxonomy, WP_REST_Request $request ) {
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 100 ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$search   = $request->get_param( 'search' );

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( ! empty( $search ) ) {
			$args['search'] = sanitize_text_field( $search );
		}

		$terms       = get_terms( $args );
		$total_terms = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		$formatted = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$formatted[] = array(
					'id'          => $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'count'       => $term->count,
					'parent'      => $term->parent,
				);
			}
		}

		return array(
			'terms'       => $formatted,
			'total'       => (int) $total_terms,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total_terms / $per_page ),
		);
	}

	/**
	 * Query args for GET /posts.
	 *
	 * @return array
	 */
	private static function get_posts_args() {
		return array(
			'per_page' => array(
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
			'page'     => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'status'   => array(
				'default'           => 'any',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'search'   => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Body args for POST /posts.
	 *
	 * @return array
	 */
	private static function get_create_post_args() {
		return array(
			'title'          => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'        => array(
				'required' => true,
				'type'     => 'string',
			),
			'excerpt'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'status'         => array(
				'default'           => 'draft',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'categories'     => array(
				'type' => 'array',
			),
			'tags'           => array(
				'type' => 'array',
			),
			'featured_image' => array(
				'type' => 'string',
			),
			'seoUrl'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
			'metaTitle'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'metaDescription' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'metaKeywords'   => array(
				'validate_callback' => function ( $value ) {
					return null === $value || is_string( $value ) || is_array( $value );
				},
			),
		);
	}

	/**
	 * Query args for taxonomy list endpoints.
	 *
	 * @return array
	 */
	private static function get_taxonomy_list_args() {
		return array(
			'per_page' => array(
				'default'           => 100,
				'sanitize_callback' => 'absint',
			),
			'page'     => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
