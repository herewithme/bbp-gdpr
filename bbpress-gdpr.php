<?php
/**
 * Plugin Name: bbPress GDPR (hacked for WPFR)
 * Description: GDPR support for bbPress
 * Author:      BuddyBoss
 * Author URI:  https://www.buddyboss.com
 * Version:     1.0.2
 * Text Domain: bbpress-gdpr
 * Domain Path: /languages
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_folder = dirname( __FILE__ );
require_once $plugin_folder . '/includes/controller.php';
require_once $plugin_folder . '/includes/functions.php';

/**
 * Get the main plugin object.
 *
 * @return \Boss\bbPress\Controller the singleton object
 */
function bbpress_gdpre_plugin_init() {
	if ( class_exists( 'bbpress' ) ) {
		return \Boss\bbPress\Controller::instance( __FILE__ );
	}
}

add_action( 'plugins_loaded', 'bbpress_gdpre_plugin_init' );
