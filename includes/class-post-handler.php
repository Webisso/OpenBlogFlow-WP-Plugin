<?php
/**
 * Post creation and serialization helpers.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_Post_Handler
 */
class OpenBlogFlow_Post_Handler {

	/**
	 * Create a new blog post from API payload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array|WP_Error
	 */
	public static function create_post( WP_REST_Request $request ) {
		$title   = sanitize_text_field( $request->get_param( 'title' ) );
		$content = $request->get_param( 'content' );
		$excerpt = $request->get_param( 'excerpt' );

		if ( empty( $title ) ) {
			return new WP_Error(
				'openblogflow_missing_title',
				__( 'Post title is required.', 'openblogflow' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $content ) ) {
			return new WP_Error(
				'openblogflow_missing_content',
				__( 'Post content is required.', 'openblogflow' ),
				array( 'status' => 400 )
			);
		}

		$content = self::strip_scripts_from_content( $content );

		$status = OpenBlogFlow_Plugin_Settings::resolve_post_status( $request );

		$post_data = array(
			'post_title'   => $title,
			'post_content' => wp_kses_post( $content ),
			'post_status'  => $status,
			'post_type'    => 'post',
			'post_author'  => get_current_user_id() ?: 1,
		);

		if ( ! empty( $excerpt ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $excerpt );
		}

		$seo_url = OpenBlogFlow_Request_Params::get_post_slug( $request );
		if ( ! empty( $seo_url ) ) {
			$post_data['post_name'] = sanitize_title( $seo_url );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$processed_content = OpenBlogFlow_Media_Handler::process_content_images( $content, $post_id );

		if ( is_wp_error( $processed_content ) ) {
			wp_delete_post( $post_id, true );
			return $processed_content;
		}

		if ( $processed_content !== $content ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => wp_kses_post( $processed_content ),
				)
			);
		}

		self::assign_categories( $post_id, OpenBlogFlow_Plugin_Settings::resolve_categories( $request ) );
		self::assign_tags( $post_id, self::merge_tag_inputs( $request ) );

		OpenBlogFlow_Yoast_SEO_Handler::apply_to_post( $post_id, $request );

		$featured_image = OpenBlogFlow_Request_Params::get_featured_image( $request );

		if ( ! empty( $featured_image ) ) {
			$thumbnail_result = self::set_featured_image( $post_id, $featured_image );

			if ( is_wp_error( $thumbnail_result ) ) {
				return new WP_Error(
					'openblogflow_featured_image_failed',
					$thumbnail_result->get_error_message(),
					array(
						'status'  => 422,
						'post_id' => $post_id,
					)
				);
			}
		}

		$post = get_post( $post_id );

		return self::format_post( $post );
	}

	/**
	 * Get a collection of posts.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public static function get_posts( WP_REST_Request $request ) {
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 10 ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$status   = $request->get_param( 'status' ) ?: 'any';

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'any' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'any';
		}

		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$query_args['s'] = sanitize_text_field( $search );
		}

		$query = new WP_Query( $query_args );
		$posts = array();

		foreach ( $query->posts as $post ) {
			$posts[] = self::format_post( $post );
		}

		return array(
			'posts'       => $posts,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	/**
	 * Get a single post by ID.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array|WP_Error
	 */
	public static function get_post( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return new WP_Error(
				'openblogflow_post_not_found',
				__( 'Post not found.', 'openblogflow' ),
				array( 'status' => 404 )
			);
		}

		return self::format_post( $post );
	}

	/**
	 * Format a WP_Post object for API response.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public static function format_post( WP_Post $post ) {
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
		$tags       = wp_get_post_tags( $post->ID );

		$featured_image = null;
		$thumbnail_id   = get_post_thumbnail_id( $post->ID );

		if ( $thumbnail_id ) {
			$featured_image = array(
				'id'  => $thumbnail_id,
				'url' => wp_get_attachment_url( $thumbnail_id ),
				'alt' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
			);
		}

		$formatted = array(
			'id'              => $post->ID,
			'title'           => get_the_title( $post ),
			'content'         => apply_filters( 'the_content', $post->post_content ),
			'excerpt'         => get_the_excerpt( $post ),
			'status'          => $post->post_status,
			'slug'            => $post->post_name,
			'date'            => get_the_date( 'c', $post ),
			'modified'        => get_the_modified_date( 'c', $post ),
			'permalink'       => get_permalink( $post ),
			'featured_image'  => $featured_image,
			'categories'      => array_map(
				function ( $cat ) {
					return array(
						'id'   => $cat->term_id,
						'name' => $cat->name,
						'slug' => $cat->slug,
					);
				},
				$categories
			),
			'tags'            => array_map(
				function ( $tag ) {
					return array(
						'id'   => $tag->term_id,
						'name' => $tag->name,
						'slug' => $tag->slug,
					);
				},
				$tags
			),
		);

		$formatted['seo'] = OpenBlogFlow_Yoast_SEO_Handler::format_for_response( $post );

		return $formatted;
	}

	/**
	 * Merge tags and metaKeywords into a single tag input list.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	private static function merge_tag_inputs( WP_REST_Request $request ) {
		$tags       = $request->get_param( 'tags' );
		$keywords   = OpenBlogFlow_Yoast_SEO_Handler::normalize_keywords(
			OpenBlogFlow_Request_Params::get_meta_keywords( $request )
		);
		$merged     = array();

		if ( ! empty( $tags ) && is_array( $tags ) ) {
			$merged = array_merge( $merged, $tags );
		}

		if ( ! empty( $keywords ) ) {
			$merged = array_merge( $merged, $keywords );
		}

		return $merged;
	}

	/**
	 * Assign categories to a post by ID or name.
	 *
	 * @param int        $post_id    Post ID.
	 * @param array|null $categories Category IDs or names.
	 */
	private static function assign_categories( $post_id, $categories ) {
		if ( empty( $categories ) || ! is_array( $categories ) ) {
			return;
		}

		$term_ids = array();

		foreach ( $categories as $category ) {
			if ( is_numeric( $category ) ) {
				$term_ids[] = (int) $category;
				continue;
			}

			$term = get_term_by( 'name', sanitize_text_field( $category ), 'category' );

			if ( $term ) {
				$term_ids[] = $term->term_id;
			} else {
				$new_term = wp_insert_term( sanitize_text_field( $category ), 'category' );
				if ( ! is_wp_error( $new_term ) ) {
					$term_ids[] = $new_term['term_id'];
				}
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_post_categories( $post_id, $term_ids );
		}
	}

	/**
	 * Assign tags to a post by ID or name.
	 *
	 * @param int        $post_id Post ID.
	 * @param array|null $tags    Tag IDs or names.
	 */
	private static function assign_tags( $post_id, $tags ) {
		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return;
		}

		$tag_names = array();

		foreach ( $tags as $tag ) {
			if ( is_numeric( $tag ) ) {
				$term = get_term( (int) $tag, 'post_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$tag_names[] = $term->name;
				}
			} else {
				$tag_names[] = sanitize_text_field( $tag );
			}
		}

		if ( ! empty( $tag_names ) ) {
			wp_set_post_tags( $post_id, $tag_names );
		}
	}

	/**
	 * Set featured image from URL or base64.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $source  Image URL or base64 data URI.
	 * @return true|WP_Error
	 */
	private static function set_featured_image( $post_id, $source ) {
		$attachment_id = OpenBlogFlow_Media_Handler::upload_image( $source, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$result = set_post_thumbnail( $post_id, $attachment_id );

		if ( ! $result ) {
			return new WP_Error(
				'openblogflow_thumbnail_failed',
				__( 'Failed to set featured image.', 'openblogflow' )
			);
		}

		return true;
	}

	/**
	 * Remove script/style tags and their contents before KSES sanitization.
	 *
	 * wp_kses_post() strips disallowed tags but can leave inner text (e.g. JSON-LD)
	 * visible in post content; remove these elements entirely first.
	 *
	 * @param string $content Post HTML content.
	 * @return string
	 */
	private static function strip_scripts_from_content( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		$content = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $content );
		$content = preg_replace( '#<script\b[^>]*>#is', '', $content );
		$content = preg_replace( '#</script>#is', '', $content );

		$content = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $content );
		$content = preg_replace( '#<style\b[^>]*>#is', '', $content );
		$content = preg_replace( '#</style>#is', '', $content );

		return $content;
	}
}
