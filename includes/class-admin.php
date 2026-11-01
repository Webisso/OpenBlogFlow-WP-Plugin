<?php
/**
 * Admin settings page and API documentation UI.
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenBlogFlow_Admin
 */
class OpenBlogFlow_Admin {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'openblogflow-settings';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_generate_token' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu item.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'OpenBlogFlow', 'openblogflow' ),
			__( 'OpenBlogFlow', 'openblogflow' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-rest-api',
			30
		);
	}

	/**
	 * Register plugin settings.
	 */
	public static function register_settings() {
		register_setting(
			'openblogflow_settings_group',
			'openblogflow_bearer_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_token' ),
				'default'           => '',
			)
		);

		register_setting(
			'openblogflow_settings_group',
			OpenBlogFlow_Plugin_Settings::OPTION_DEFAULT_STATUS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'OpenBlogFlow_Plugin_Settings', 'sanitize_default_status' ),
				'default'           => '',
			)
		);

		register_setting(
			'openblogflow_settings_group',
			OpenBlogFlow_Plugin_Settings::OPTION_DEFAULT_CATEGORY,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( 'OpenBlogFlow_Plugin_Settings', 'sanitize_default_category' ),
				'default'           => 0,
			)
		);
	}

	/**
	 * Sanitize bearer token input.
	 *
	 * @param string $value Submitted token.
	 * @return string
	 */
	public static function sanitize_token( $value ) {
		return sanitize_text_field( trim( $value ) );
	}

	/**
	 * Enqueue admin styles on settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'openblogflow-admin',
			OPENBLOGFLOW_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			OPENBLOGFLOW_VERSION
		);
	}

	/**
	 * REST API base URL (index.php?rest_route= format).
	 *
	 * @return string
	 */
	public static function get_api_base_url() {
		return home_url( '/index.php?rest_route=/' . OPENBLOGFLOW_API_NAMESPACE );
	}

	/**
	 * Build a full REST API endpoint URL.
	 *
	 * @param string $route      Route path after namespace (e.g. '/posts', '/posts/42').
	 * @param array  $query_args Optional query parameters.
	 * @return string
	 */
	public static function get_api_endpoint_url( $route, $query_args = array() ) {
		$url = home_url( '/index.php?rest_route=/' . OPENBLOGFLOW_API_NAMESPACE . $route );

		if ( ! empty( $query_args ) ) {
			$url = add_query_arg( $query_args, $url );
		}

		return $url;
	}

	/**
	 * Render the settings page with tabs.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$base_url   = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$token      = get_option( 'openblogflow_bearer_token', '' );

		?>
		<div class="wrap openblogflow-wrap">
			<h1><?php esc_html_e( 'OpenBlogFlow', 'openblogflow' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Manage your REST API token and explore endpoint documentation.', 'openblogflow' ); ?>
			</p>

			<nav class="nav-tab-wrapper obf-nav-tabs">
				<a href="<?php echo esc_url( $base_url . '&tab=settings' ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'openblogflow' ); ?>
				</a>
				<a href="<?php echo esc_url( $base_url . '&tab=documentation' ); ?>"
				   class="nav-tab <?php echo 'documentation' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'API Documentation', 'openblogflow' ); ?>
				</a>
			</nav>

			<?php if ( 'documentation' === $active_tab ) : ?>
				<?php self::render_documentation_tab(); ?>
			<?php else : ?>
				<?php self::render_settings_tab( $token ); ?>
			<?php endif; ?>

			<?php self::render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Render settings tab content.
	 *
	 * @param string $token Current bearer token.
	 */
	private static function render_settings_tab( $token ) {
		$generated          = isset( $_GET['obf_generated'] ) ? sanitize_text_field( wp_unslash( $_GET['obf_generated'] ) ) : '';
		$default_status     = get_option( OpenBlogFlow_Plugin_Settings::OPTION_DEFAULT_STATUS, '' );
		$default_category   = (int) get_option( OpenBlogFlow_Plugin_Settings::OPTION_DEFAULT_CATEGORY, 0 );
		$status_choices     = OpenBlogFlow_Plugin_Settings::get_post_status_choices();
		?>
		<div class="obf-tab-content">
			<form method="post" action="options.php" class="obf-settings-form">
				<?php settings_fields( 'openblogflow_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'API authentication', 'openblogflow' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="openblogflow_bearer_token">
								<?php esc_html_e( 'API Bearer Token', 'openblogflow' ); ?>
							</label>
						</th>
						<td>
							<input
								type="password"
								id="openblogflow_bearer_token"
								name="openblogflow_bearer_token"
								value="<?php echo esc_attr( $token ); ?>"
								class="regular-text obf-token-input"
								autocomplete="new-password"
								placeholder="<?php esc_attr_e( 'Enter a secure token', 'openblogflow' ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Send this token in the Authorization header as: Bearer YOUR_TOKEN', 'openblogflow' ); ?>
							</p>
							<?php if ( ! empty( $token ) ) : ?>
								<p class="obf-token-status obf-token-set">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'A Bearer token is configured.', 'openblogflow' ); ?>
								</p>
							<?php else : ?>
								<p class="obf-token-status obf-token-missing">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'No token configured. API requests will be rejected until you save one.', 'openblogflow' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'API post defaults', 'openblogflow' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Used when a POST /posts request omits status or categories. Request body values always take priority.', 'openblogflow' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="openblogflow_default_post_status">
								<?php esc_html_e( 'Default post status', 'openblogflow' ); ?>
							</label>
						</th>
						<td>
							<select
								id="openblogflow_default_post_status"
								name="<?php echo esc_attr( OpenBlogFlow_Plugin_Settings::OPTION_DEFAULT_STATUS ); ?>"
							>
								<?php foreach ( $status_choices as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_status, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'If not set here and omitted from the API, new posts are saved as draft.', 'openblogflow' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="openblogflow_default_category_id">
								<?php esc_html_e( 'Default category', 'openblogflow' ); ?>
							</label>
						</th>
						<td>
							<?php
							wp_dropdown_categories(
								array(
									'show_option_none'  => __( '— WordPress default category —', 'openblogflow' ),
									'option_none_value' => '0',
									'selected'          => $default_category,
									'name'              => OpenBlogFlow_Plugin_Settings::OPTION_DEFAULT_CATEGORY,
									'id'                => 'openblogflow_default_category_id',
									'hide_empty'        => 0,
									'orderby'           => 'name',
									'taxonomy'          => 'category',
									'hierarchical'      => true,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'If not set here and omitted from the API, WordPress assigns its site default category (usually Uncategorized).', 'openblogflow' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Settings', 'openblogflow' ), 'primary', 'submit', false ); ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&obf_action=generate_token' ), 'obf_generate_token' ) ); ?>"
					   class="button button-secondary">
						<?php esc_html_e( 'Generate Secure Token', 'openblogflow' ); ?>
					</a>
				</p>
			</form>

			<?php if ( ! empty( $generated ) ) : ?>
				<div class="notice notice-info obf-generated-notice">
					<p>
						<strong><?php esc_html_e( 'Generated token (copy and save before leaving this page):', 'openblogflow' ); ?></strong>
					</p>
					<code class="obf-generated-token"><?php echo esc_html( $generated ); ?></code>
				</div>
			<?php endif; ?>

			<div class="obf-info-box">
				<h3><?php esc_html_e( 'Quick Start', 'openblogflow' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Generate or enter a secure Bearer token and save settings.', 'openblogflow' ); ?></li>
					<li><?php esc_html_e( 'Optionally set default post status and category for API posts that omit those fields.', 'openblogflow' ); ?></li>
					<li><?php esc_html_e( 'Send HTTP requests to your REST API URLs (index.php?rest_route=/openblogflow/v1/…), not to admin-ajax.php or plugin PHP files.', 'openblogflow' ); ?></li>
					<li><?php esc_html_e( 'Include Authorization: Bearer YOUR_TOKEN on every API request.', 'openblogflow' ); ?></li>
					<li><?php esc_html_e( 'Open the API Documentation tab for full endpoint URLs, request bodies, and response examples.', 'openblogflow' ); ?></li>
				</ol>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle secure token generation action.
	 */
	public static function handle_generate_token() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! isset( $_GET['obf_action'] ) || 'generate_token' !== $_GET['obf_action'] ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'obf_generate_token' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'openblogflow' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'openblogflow' ) );
		}

		$new_token = OpenBlogFlow_Auth::generate_token();
		update_option( 'openblogflow_bearer_token', $new_token );

		wp_safe_redirect(
			admin_url(
				'admin.php?page=' . self::PAGE_SLUG . '&obf_generated=' . rawurlencode( $new_token )
			)
		);
		exit;
	}

	/**
	 * Render API documentation tab.
	 */
	private static function render_documentation_tab() {
		$site_url     = home_url();
		$api_base     = self::get_api_base_url();
		$settings_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="obf-tab-content obf-docs">
			<section class="obf-doc-section">
				<h2><?php esc_html_e( 'Overview', 'openblogflow' ); ?></h2>
				<p>
					<?php esc_html_e( 'OpenBlogFlow registers custom routes on the native WordPress REST API. Send HTTP requests using the index.php?rest_route= URL format shown below from any external app, script, or service.', 'openblogflow' ); ?>
				</p>

				<div class="obf-info-box">
					<h3><?php esc_html_e( 'Important', 'openblogflow' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Use the REST API base URL below (index.php?rest_route=/openblogflow/v1/…).', 'openblogflow' ); ?></li>
						<li><?php esc_html_e( 'Append extra query parameters with & (e.g. …/posts&per_page=10&page=1). Do not use a second ? inside rest_route.', 'openblogflow' ); ?></li>
						<li><?php esc_html_e( 'Do not call admin-ajax.php — OpenBlogFlow does not use AJAX handlers.', 'openblogflow' ); ?></li>
						<li><?php esc_html_e( 'The Settings tab in wp-admin is only for configuring your Bearer token; it is not an API endpoint.', 'openblogflow' ); ?></li>
					</ul>
				</div>

				<table class="widefat obf-doc-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Site URL', 'openblogflow' ); ?></th>
							<td><code><?php echo esc_html( untrailingslashit( $site_url ) ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'REST API Base URL', 'openblogflow' ); ?></th>
							<td><code><?php echo esc_html( $api_base ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Plugin Settings (wp-admin)', 'openblogflow' ); ?></th>
							<td><code><?php echo esc_html( $settings_url ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Protocol', 'openblogflow' ); ?></th>
							<td><?php esc_html_e( 'HTTP/HTTPS JSON REST (WordPress REST API)', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Request Content-Type', 'openblogflow' ); ?></th>
							<td><code>application/json</code> <?php esc_html_e( '(required for POST requests with a JSON body)', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Response Format', 'openblogflow' ); ?></th>
							<td><code>application/json</code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Authentication', 'openblogflow' ); ?></th>
							<td><code>Authorization: Bearer YOUR_TOKEN</code></td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="obf-doc-section">
				<h2><?php esc_html_e( 'Endpoint URLs', 'openblogflow' ); ?></h2>
				<p><?php esc_html_e( 'All endpoints are registered under the WordPress REST API namespace openblogflow/v1. Replace YOUR_TOKEN with the Bearer token saved on the Settings tab.', 'openblogflow' ); ?></p>
				<table class="widefat obf-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Method', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Full URL', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Description', 'openblogflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>GET</code></td>
							<td><code><?php echo esc_html( self::get_api_endpoint_url( '/posts' ) ); ?></code></td>
							<td><?php esc_html_e( 'List blog posts', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<td><code>GET</code></td>
							<td><code><?php echo esc_html( self::get_api_endpoint_url( '/posts/{id}' ) ); ?></code></td>
							<td><?php esc_html_e( 'Get a single post by numeric ID', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<td><code>POST</code></td>
							<td><code><?php echo esc_html( self::get_api_endpoint_url( '/posts' ) ); ?></code></td>
							<td><?php esc_html_e( 'Create a new blog post', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<td><code>GET</code></td>
							<td><code><?php echo esc_html( self::get_api_endpoint_url( '/categories' ) ); ?></code></td>
							<td><?php esc_html_e( 'List categories', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<td><code>GET</code></td>
							<td><code><?php echo esc_html( self::get_api_endpoint_url( '/tags' ) ); ?></code></td>
							<td><?php esc_html_e( 'List tags', 'openblogflow' ); ?></td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="obf-doc-section">
				<h2><?php esc_html_e( 'Authentication', 'openblogflow' ); ?></h2>
				<p><?php esc_html_e( 'Every REST API request must include your Bearer token in the Authorization HTTP header. The token is configured on the Settings tab of this plugin — it is not your WordPress login password.', 'openblogflow' ); ?></p>
				<pre class="obf-code-block"><code>Authorization: Bearer YOUR_TOKEN</code></pre>

				<h3><?php esc_html_e( 'Server note (Apache)', 'openblogflow' ); ?></h3>
				<p><?php esc_html_e( 'If the Authorization header is stripped by your server, add this to your site .htaccess file:', 'openblogflow' ); ?></p>
				<pre class="obf-code-block"><code>RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]</code></pre>

				<h3><?php esc_html_e( 'Error Responses', 'openblogflow' ); ?></h3>
				<table class="widefat obf-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'HTTP Code', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Code', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Description', 'openblogflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>401</td>
							<td><code>openblogflow_missing_token</code></td>
							<td><?php esc_html_e( 'Authorization header is missing.', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<td>403</td>
							<td><code>openblogflow_invalid_token</code></td>
							<td><?php esc_html_e( 'Token does not match the configured value.', 'openblogflow' ); ?></td>
						</tr>
						<tr>
							<td>503</td>
							<td><code>openblogflow_token_not_configured</code></td>
							<td><?php esc_html_e( 'No token has been saved in plugin settings.', 'openblogflow' ); ?></td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="obf-doc-section">
				<h2>GET /posts</h2>
				<p><?php esc_html_e( 'List blog posts with pagination and optional filters.', 'openblogflow' ); ?></p>

				<h3><?php esc_html_e( 'Query Parameters', 'openblogflow' ); ?></h3>
				<table class="widefat obf-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Parameter', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Type', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Default', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Description', 'openblogflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>per_page</code></td><td>integer</td><td>10</td><td><?php esc_html_e( 'Results per page (max 100).', 'openblogflow' ); ?></td></tr>
						<tr><td><code>page</code></td><td>integer</td><td>1</td><td><?php esc_html_e( 'Page number.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>status</code></td><td>string</td><td>any</td><td><?php esc_html_e( 'publish, draft, pending, private, or any.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>search</code></td><td>string</td><td>—</td><td><?php esc_html_e( 'Search posts by keyword.', 'openblogflow' ); ?></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Example Request (cURL)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>curl -X GET "<?php echo esc_html( self::get_api_endpoint_url( '/posts', array( 'per_page' => 10, 'page' => 1 ) ) ); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"</code></pre>

				<h3><?php esc_html_e( 'Example Request (JavaScript fetch)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>fetch("<?php echo esc_html( self::get_api_endpoint_url( '/posts', array( 'per_page' => 10, 'page' => 1 ) ) ); ?>", {
  method: "GET",
  headers: {
    "Authorization": "Bearer YOUR_TOKEN",
    "Accept": "application/json"
  }
})
  .then((response) => response.json())
  .then((data) => console.log(data));</code></pre>

				<h3><?php esc_html_e( 'Example Response (200)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>{
  "posts": [
    {
      "id": 42,
      "title": "My First Post",
      "content": "&lt;p&gt;Hello world&lt;/p&gt;",
      "excerpt": "Hello world",
      "status": "publish",
      "slug": "my-first-post",
      "date": "2026-06-18T10:00:00+00:00",
      "modified": "2026-06-18T10:00:00+00:00",
      "permalink": "<?php echo esc_html( $site_url ); ?>/my-first-post/",
      "featured_image": {
        "id": 15,
        "url": "<?php echo esc_html( $site_url ); ?>/wp-content/uploads/2026/06/hero.jpg",
        "alt": "Hero image"
      },
      "categories": [
        { "id": 3, "name": "Technology", "slug": "technology" }
      ],
      "tags": [
        { "id": 7, "name": "wordpress", "slug": "wordpress" }
      ]
    }
  ],
  "total": 1,
  "total_pages": 1,
  "page": 1,
  "per_page": 10
}</code></pre>
			</section>

			<section class="obf-doc-section">
				<h2>GET /posts/{id}</h2>
				<p><?php esc_html_e( 'Retrieve a single blog post by ID.', 'openblogflow' ); ?></p>

				<h3><?php esc_html_e( 'Example Request (cURL)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>curl -X GET "<?php echo esc_html( self::get_api_endpoint_url( '/posts/42' ) ); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"</code></pre>

				<h3><?php esc_html_e( 'Example Response (200)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>{
  "id": 42,
  "title": "My First Post",
  "content": "&lt;p&gt;Hello world&lt;/p&gt;",
  "excerpt": "Hello world",
  "status": "publish",
  "slug": "my-first-post",
  "date": "2026-06-18T10:00:00+00:00",
  "modified": "2026-06-18T10:00:00+00:00",
  "permalink": "<?php echo esc_html( $site_url ); ?>/my-first-post/",
  "featured_image": null,
  "categories": [],
  "tags": []
}</code></pre>

				<h3><?php esc_html_e( 'Error Response (404)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>{
  "code": "openblogflow_post_not_found",
  "message": "Post not found.",
  "data": { "status": 404 }
}</code></pre>
			</section>

			<section class="obf-doc-section">
				<h2>POST /posts</h2>
				<p><?php esc_html_e( 'Create a new blog post. External images in content and the featured image are automatically downloaded to the WordPress media library.', 'openblogflow' ); ?></p>

				<h3><?php esc_html_e( 'Request Body', 'openblogflow' ); ?></h3>
				<table class="widefat obf-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Type', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Required', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Description', 'openblogflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>title</code></td><td>string</td><td><?php esc_html_e( 'Yes', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Post title.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>content</code></td><td>string</td><td><?php esc_html_e( 'Yes', 'openblogflow' ); ?></td><td><?php esc_html_e( 'HTML content. Inline &lt;img&gt; URLs are imported to media library. &lt;script&gt; and &lt;style&gt; blocks (e.g. JSON-LD) are removed before save.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>excerpt</code></td><td>string</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Short summary.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>status</code></td><td>string</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'publish, draft, pending, or private. If omitted: plugin default from Settings, otherwise draft.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>categories</code></td><td>array</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Category IDs or names. New names are created automatically. If omitted: plugin default category from Settings, otherwise WordPress default.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>tags</code></td><td>array</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Tag IDs or names.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>featured_image</code></td><td>string</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Image URL or base64 data URI. Saved as post thumbnail. Alias: main_image.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>slug</code> / <code>seoUrl</code></td><td>string</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Custom post slug (URL path). seoUrl is checked first when both are sent.', 'openblogflow' ); ?></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'SEO Fields (optional)', 'openblogflow' ); ?></h3>
				<p>
					<?php
					if ( OpenBlogFlow_Yoast_SEO_Handler::is_active() ) {
						esc_html_e( 'Saved to Yoast SEO when active. metaKeywords / meta_keywords are also assigned as WordPress post tags.', 'openblogflow' );
					} else {
						esc_html_e( 'Stored as post meta when Yoast SEO is not active. Keywords are still assigned as WordPress post tags.', 'openblogflow' );
					}
					?>
				</p>
				<table class="widefat obf-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Type', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Required', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Description', 'openblogflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>metaTitle</code> / <code>meta_title</code></td><td>string</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'SEO title.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>metaDescription</code> / <code>meta_description</code></td><td>string</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Meta description.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>metaKeywords</code> / <code>meta_keywords</code></td><td>string or array</td><td><?php esc_html_e( 'No', 'openblogflow' ); ?></td><td><?php esc_html_e( 'Comma-separated string or array. Also used as WordPress tags.', 'openblogflow' ); ?></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Example Request (cURL)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>curl -X POST "<?php echo esc_html( self::get_api_endpoint_url( '/posts' ) ); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "API Created Post",
    "content": "&lt;p&gt;Check this image:&lt;/p&gt;&lt;img src=\"https://example.com/photo.jpg\" alt=\"Photo\" /&gt;",
    "excerpt": "A post created via API",
    "status": "publish",
    "categories": ["Technology", 2],
    "tags": ["api", "wordpress"],
    "featured_image": "https://example.com/hero.jpg",
    "seoUrl": "api-created-post",
    "metaTitle": "API Created Post | My Site",
    "metaDescription": "A post created via the OpenBlogFlow API.",
    "metaKeywords": "api, wordpress, automation"
  }'</code></pre>

				<h3><?php esc_html_e( 'Example Request (PHP wp_remote_post)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>$response = wp_remote_post(
  "<?php echo esc_html( self::get_api_endpoint_url( '/posts' ) ); ?>",
  array(
    "headers" => array(
      "Authorization" => "Bearer YOUR_TOKEN",
      "Content-Type"  => "application/json",
    ),
    "body" => wp_json_encode(
      array(
        "title"          => "API Created Post",
        "content"        => "&lt;p&gt;Hello&lt;/p&gt;",
        "status"         => "publish",
        "categories"     => array( "Technology" ),
        "tags"           => array( "api" ),
        "featured_image" => "https://example.com/hero.jpg",
        "seoUrl"         => "api-created-post",
        "metaTitle"      => "API Created Post | My Site",
        "metaDescription"=> "A post created via the OpenBlogFlow API.",
        "metaKeywords"   => array( "api", "wordpress", "automation" ),
      )
    ),
  )
);

$body = json_decode( wp_remote_retrieve_body( $response ), true );</code></pre>

				<h3><?php esc_html_e( 'Example Response (201)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>{
  "id": 43,
  "title": "API Created Post",
  "content": "&lt;p&gt;Check this image:&lt;/p&gt;&lt;img src=\"<?php echo esc_html( $site_url ); ?>/wp-content/uploads/2026/06/photo.jpg\" alt=\"Photo\" /&gt;",
  "excerpt": "A post created via API",
  "status": "publish",
  "slug": "api-created-post",
  "date": "2026-06-18T12:00:00+00:00",
  "modified": "2026-06-18T12:00:00+00:00",
  "permalink": "<?php echo esc_html( $site_url ); ?>/api-created-post/",
  "featured_image": {
    "id": 16,
    "url": "<?php echo esc_html( $site_url ); ?>/wp-content/uploads/2026/06/hero.jpg",
    "alt": ""
  },
  "categories": [
    { "id": 2, "name": "Technology", "slug": "technology" }
  ],
  "tags": [
    { "id": 8, "name": "api", "slug": "api" },
    { "id": 7, "name": "wordpress", "slug": "wordpress" }
  ],
  "seo": {
    "seoUrl": "api-created-post",
    "metaTitle": "API Created Post | My Site",
    "metaDescription": "A post created via the OpenBlogFlow API.",
    "metaKeywords": "api, wordpress, automation"
  }
}</code></pre>

				<div class="obf-info-box">
					<h3><?php esc_html_e( 'Image Handling', 'openblogflow' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'featured_image: Remote URLs and base64 data URIs are uploaded to the media library and set as the post thumbnail.', 'openblogflow' ); ?></li>
						<li><?php esc_html_e( 'content: Every external &lt;img src="..."&gt; is downloaded, stored in the media library, and the src is replaced with the local URL.', 'openblogflow' ); ?></li>
						<li><?php esc_html_e( 'Images already hosted on this WordPress site are left unchanged.', 'openblogflow' ); ?></li>
					</ul>
				</div>
			</section>

			<section class="obf-doc-section">
				<h2>GET /categories</h2>
				<p><?php esc_html_e( 'List all post categories.', 'openblogflow' ); ?></p>

				<h3><?php esc_html_e( 'Query Parameters', 'openblogflow' ); ?></h3>
				<table class="widefat obf-doc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Parameter', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Type', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Default', 'openblogflow' ); ?></th>
							<th><?php esc_html_e( 'Description', 'openblogflow' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>per_page</code></td><td>integer</td><td>100</td><td><?php esc_html_e( 'Results per page (max 100).', 'openblogflow' ); ?></td></tr>
						<tr><td><code>page</code></td><td>integer</td><td>1</td><td><?php esc_html_e( 'Page number.', 'openblogflow' ); ?></td></tr>
						<tr><td><code>search</code></td><td>string</td><td>—</td><td><?php esc_html_e( 'Filter categories by name.', 'openblogflow' ); ?></td></tr>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Example Request (cURL)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>curl -X GET "<?php echo esc_html( self::get_api_endpoint_url( '/categories' ) ); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"</code></pre>

				<h3><?php esc_html_e( 'Example Response (200)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>{
  "terms": [
    {
      "id": 1,
      "name": "Uncategorized",
      "slug": "uncategorized",
      "description": "",
      "count": 5,
      "parent": 0
    },
    {
      "id": 3,
      "name": "Technology",
      "slug": "technology",
      "description": "Tech articles",
      "count": 12,
      "parent": 0
    }
  ],
  "total": 2,
  "page": 1,
  "per_page": 100,
  "total_pages": 1
}</code></pre>
			</section>

			<section class="obf-doc-section">
				<h2>GET /tags</h2>
				<p><?php esc_html_e( 'List all post tags. Same query parameters and response structure as /categories.', 'openblogflow' ); ?></p>

				<h3><?php esc_html_e( 'Example Request (cURL)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>curl -X GET "<?php echo esc_html( self::get_api_endpoint_url( '/tags' ) ); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"</code></pre>

				<h3><?php esc_html_e( 'Example Response (200)', 'openblogflow' ); ?></h3>
				<pre class="obf-code-block"><code>{
  "terms": [
    {
      "id": 7,
      "name": "wordpress",
      "slug": "wordpress",
      "description": "",
      "count": 8,
      "parent": 0
    }
  ],
  "total": 1,
  "page": 1,
  "per_page": 100,
  "total_pages": 1
}</code></pre>
			</section>
		</div>
		<?php
	}

	/**
	 * Render page footer with branding, copyright, and version.
	 */
	private static function render_footer() {
		$logo_url = OPENBLOGFLOW_PLUGIN_URL . 'admin/images/webisso-logo.svg';
		?>
		<footer class="obf-page-footer">
			<div class="obf-footer-inner">
				<a href="https://webisso.com"
				   class="obf-footer-brand"
				   target="_blank"
				   rel="noopener noreferrer">
					<span class="obf-footer-developed"><?php esc_html_e( 'Developed By', 'openblogflow' ); ?></span>
					<img src="<?php echo esc_url( $logo_url ); ?>"
					     alt="Webisso"
					     class="obf-footer-logo"
					     width="110"
					     height="39" />
				</a>
				<p class="obf-footer-copyright">
					&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
					<a href="https://webisso.com" target="_blank" rel="noopener noreferrer">Webisso</a>.
					<?php esc_html_e( 'All rights reserved.', 'openblogflow' ); ?>
				</p>
				<p class="obf-footer-version">
					<?php
					printf(
						/* translators: %s: plugin version number */
						esc_html__( 'Version %s', 'openblogflow' ),
						esc_html( OPENBLOGFLOW_VERSION )
					);
					?>
				</p>
			</div>
		</footer>
		<?php
	}
}
