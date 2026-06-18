<?php
/**
 * Uninstall cleanup.
 *
 * @package OpenBlogFlow
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'openblogflow_bearer_token' );
