<?php

namespace Boss\bbPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( '\Boss\bbPress\Controller' ) ) :

	final class Controller {
		/**
		 * This options array is setup during class instantiation, holds
		 * default and saved options for the plugin.
		 *
		 * @var array
		 */
		protected $_network_activated   = false;
		/**
		 * Holds the namespace information of current plugin scope.
		 * 
		 * @var bool|string
		 */
		protected $namespace   = false;

		/**
		 * Holds Plugin Loader File Path
		 * 
		 * @var bool|string
		 */
		protected $plugin_main = false;
		
		/**
		 * Holds Plugin Directory Path Info
		 * 
		 * @var bool|string
		 */
		public $plugin_path    = false;

		/**
		 * Holds Plugin URL location
		 * 
		 * @var bool|string
		 */
		public $plugin_url     = false;
		/**
		 * Version of Plugin 
		 * 
		 * @var string
		 */
		public $version     = '1.0.0';
		/**
		 * Text Domain of Plugin Scope
		 * 
		 * @var string
		 */
		public $lang_domain    = 'bbpress-gdpr';

		/**
		 * Plugin Slug Used in Places like register style or script etc.
		 * 
		 * @var string
		 */
		public $plugin_slug    = 'bbpress-gdpr'; // slug, will be used in option name & other related places

		private function __construct() {
			// ... leave empty, see Singleton below
		}

		/**
		 * Get the instance of this class.
		 *
		 * @param $loader_file <string> of main plugin file used for detecting plugin directory and url.
		 *
		 * @return Controller|null
		 */
		public static function instance( $loader_file ) {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new Controller();
				$instance->_register_autoload();
				$instance->_setup_globals( $loader_file );
				$instance->_setup_actions();
				$instance->_setup_textdomain();
			}

			return $instance;
		}

		function _register_autoload() {

			spl_autoload_register(
				function ( $class_name ) {

					$class_name = str_replace( '\\', '/', $class_name );

					// Identify if it's valid namespace for this loader
					if ( 'Boss/' !== substr( $class_name, 0, 5 ) ) {
						return false;
					}

					$namespace = explode( '/', $class_name );

					// Identify if it's correct namespace to do auto load.
					if ( $namespace[1] !== $this->namespace ) {
						return false;
					}

					$load = $namespace;

					// remove first two
					unset( $load[0], $load[1] );
					$load = implode( '/', $load );

					// Identify ignored namespace
					if ( in_array(
						$load, array(
							'controller',
							'functions',
						), true
					) ) {
						return false;
					}

					$load = $this->plugin_path . 'includes/' . $load . '.php';
					$load = strtolower( $load );

					if ( file_exists( $load ) ) {
						require_once( $load );

						return true;
					}

					return false;

				}
			);

		}

		protected function _setup_globals( $loader_file ) {

			/**
			 * Plugin Namespace
			 */
			$namespace       = str_replace( '\\', '/', __NAMESPACE__ );
			$namespace       = explode( '/', $namespace );
			$namespace       = $namespace[1];
			$this->namespace = $namespace;

			/**
			 * Set Plugin Path and URL
			 */
			$this->plugin_path = trailingslashit( plugin_dir_path( $loader_file ) );

			$plugin_url = plugin_dir_url( $loader_file );
			if ( is_ssl() ) {
				$plugin_url = str_replace( 'http://', 'https://', $plugin_url );
			}
			$this->plugin_url = $plugin_url;

			/**
			 * Set Plugin Main
			 */
			$plugin_main       = explode( '/', $loader_file );
			$plugin_main       = end( $plugin_main );
			$plugin_main       = str_replace( '.php', '', $plugin_main );
			$plugin_main       = $plugin_main . '/' . $plugin_main . '.php';
			$this->plugin_main = $plugin_main;
		}

		/**
		 * Check if the plugin is activated network wide(in multisite)
		 *
		 * @return boolean
		 */
		public function is_network_activated() {
			if ( ! $this->_network_activated ) {
				$this->_network_activated = 'no';

				if ( is_multisite() ) {
					if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
						require_once ABSPATH . '/wp-admin/includes/plugin.php';
					}

					if ( is_plugin_active_for_network( $this->plugin_main ) ) {
						$this->_network_activated = 'yes';
					}
				}
			}

			return 'yes' === $this->_network_activated ? true : false;
		}

		protected function _setup_actions() {
			add_action( 'init', array( $this, 'init' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		public function init() {
			global $boss_bbpress_gdpr_forums;
			$boss_bbpress_gdpr_forums = new \Boss\bbPress\GDPR\BBP_GDPR_Forums();

			global $boss_bbpress_gdpr_topics;
			$boss_bbpress_gdpr_topics = new \Boss\bbPress\GDPR\BBP_GDPR_Topics();

			global $boss_bbpress_gdpr_replies;
			$boss_bbpress_gdpr_replies = new \Boss\bbPress\GDPR\BBP_GDPR_Replies();
		}

		function enqueue_scripts() {
		}

		public function _setup_textdomain() {

			$locale = apply_filters( 'plugin_locale', get_locale(), $this->lang_domain );

			// first try to load from wp-content/languages/plugins/ directory
			load_textdomain( $this->lang_domain, WP_LANG_DIR . '/plugins/' . $this->lang_domain . '-' . $locale . '.mo' );

			// if not found, then load from plugin_folder/languages directory
			load_plugin_textdomain( $this->lang_domain, false, 'bbpress-gdpr/languages' );

		}
	}
endif;
