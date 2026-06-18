<?php
/**
 * Plugin Name:       OpenBlogFlow
 * Plugin URI:        https://github.com/openblogflow/openblogflow-wp-plugin
 * Description:       REST API for creating and retrieving blog posts, categories, and tags with Bearer token authentication.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            OpenBlogFlow
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       openblogflow
 *
 * @package OpenBlogFlow
 */

defined( 'ABSPATH' ) || exit;

define( 'OPENBLOGFLOW_VERSION', '1.0.0' );
define( 'OPENBLOGFLOW_PLUGIN_FILE', __FILE__ );
define( 'OPENBLOGFLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPENBLOGFLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OPENBLOGFLOW_API_NAMESPACE', 'openblogflow/v1' );

require_once OPENBLOGFLOW_PLUGIN_DIR . 'includes/class-auth.php';
require_once OPENBLOGFLOW_PLUGIN_DIR . 'includes/class-media-handler.php';
require_once OPENBLOGFLOW_PLUGIN_DIR . 'includes/class-yoast-seo-handler.php';
require_once OPENBLOGFLOW_PLUGIN_DIR . 'includes/class-post-handler.php';
require_once OPENBLOGFLOW_PLUGIN_DIR . 'includes/class-api.php';
require_once OPENBLOGFLOW_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Initialize the plugin.
 */
function openblogflow_init() {
	OpenBlogFlow_Auth::init();
	OpenBlogFlow_API::init();
	OpenBlogFlow_Admin::init();
}
add_action( 'plugins_loaded', 'openblogflow_init' );

/**
 * Set default options on activation.
 */
function openblogflow_activate() {
	if ( false === get_option( 'openblogflow_bearer_token' ) ) {
		add_option( 'openblogflow_bearer_token', '' );
	}
}
register_activation_hook( __FILE__, 'openblogflow_activate' );
