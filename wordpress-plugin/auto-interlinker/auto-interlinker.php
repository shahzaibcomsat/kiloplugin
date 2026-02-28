<?php
/**
 * Plugin Name: Auto Interlinker
 * Plugin URI:  https://example.com/auto-interlinker
 * Description: Automatically reads all published posts and permanently inserts internal links by detecting matching keywords — improving SEO and user navigation.
 * Version:     1.1.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: auto-interlinker
 * Domain Path: /languages
 *
 * @package AutoInterlinker
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AUTO_INTERLINKER_VERSION', '1.1.0' );
define( 'AUTO_INTERLINKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTO_INTERLINKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AUTO_INTERLINKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class Auto_Interlinker {

	/**
	 * Single instance of the plugin.
	 *
	 * @var Auto_Interlinker
	 */
	private static $instance = null;

	/**
	 * Get the single instance of the plugin.
	 *
	 * @return Auto_Interlinker
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once AUTO_INTERLINKER_PLUGIN_DIR . 'includes/class-database.php';
		require_once AUTO_INTERLINKER_PLUGIN_DIR . 'includes/class-keyword-extractor.php';
		require_once AUTO_INTERLINKER_PLUGIN_DIR . 'includes/class-interlinking-engine.php';
		require_once AUTO_INTERLINKER_PLUGIN_DIR . 'includes/class-post-processor.php';

		if ( is_admin() ) {
			require_once AUTO_INTERLINKER_PLUGIN_DIR . 'admin/class-admin.php';
		}
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Plugin activation: create tables, set defaults, schedule cron.
	 */
	public function activate() {
		// Create database tables.
		Auto_Interlinker_Database::install();

		// Set default settings if not already configured.
		if ( ! get_option( 'auto_interlinker_settings' ) ) {
			update_option(
				'auto_interlinker_settings',
				array(
					'enabled'               => 1,
					'post_types'            => array( 'post', 'page' ),
					'max_links_per_post'    => 5,
					'max_keywords_per_post' => 20,
					'min_keyword_length'    => 4,
					'open_new_tab'          => 0,
					'link_once'             => 1,
					'nofollow'              => 0,
					'exclude_post_ids'      => '',
				)
			);
		}

		// Schedule hourly bulk processing cron.
		if ( ! wp_next_scheduled( 'auto_interlinker_bulk_process' ) ) {
			wp_schedule_event( time(), 'hourly', 'auto_interlinker_bulk_process' );
		}
	}

	/**
	 * Initialize plugin components.
	 */
	public function init() {
		// Initialize the interlinking engine (hooks into the_content filter).
		Auto_Interlinker_Engine::get_instance();

		// Initialize post processor (hooks into save_post).
		Auto_Interlinker_Post_Processor::get_instance();

		if ( is_admin() ) {
			Auto_Interlinker_Admin::get_instance();
		}

		// Schedule cron for bulk processing (in case it was cleared).
		if ( ! wp_next_scheduled( 'auto_interlinker_bulk_process' ) ) {
			wp_schedule_event( time(), 'hourly', 'auto_interlinker_bulk_process' );
		}

		// Register cron action callbacks.
		add_action( 'auto_interlinker_bulk_process', array( 'Auto_Interlinker_Post_Processor', 'bulk_process_posts' ) );
		add_action( 'auto_interlinker_relink_others', array( 'Auto_Interlinker_Post_Processor', 'relink_posts_referencing' ) );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'auto_interlinker_bulk_process' );
		wp_clear_scheduled_hook( 'auto_interlinker_relink_others' );
	}
}

// Boot the plugin.
Auto_Interlinker::get_instance();
