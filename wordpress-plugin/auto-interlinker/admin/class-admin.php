<?php
/**
 * Admin class for Auto Interlinker plugin.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all admin functionality.
 */
class Auto_Interlinker_Admin {

    /**
     * Single instance.
     *
     * @var Auto_Interlinker_Admin
     */
    private static $instance = null;

    /**
     * Get single instance.
     *
     * @return Auto_Interlinker_Admin
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
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_ai_reprocess_all', array( $this, 'ajax_reprocess_all' ) );
        add_action( 'wp_ajax_ai_process_single', array( $this, 'ajax_process_single' ) );
        add_action( 'wp_ajax_ai_add_keyword', array( $this, 'ajax_add_keyword' ) );
        add_action( 'wp_ajax_ai_delete_keyword', array( $this, 'ajax_delete_keyword' ) );
        add_action( 'wp_ajax_ai_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_ai_get_post_keywords', array( $this, 'ajax_get_post_keywords' ) );

        // Add settings link on plugins page.
        add_filter( 'plugin_action_links_' . AUTO_INTERLINKER_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Register admin menu pages.
     */
    public function add_menu_pages() {
        add_menu_page(
            __( 'Auto Interlinker', 'auto-interlinker' ),
            __( 'Auto Interlinker', 'auto-interlinker' ),
            'manage_options',
            'auto-interlinker',
            array( $this, 'render_dashboard_page' ),
            'dashicons-admin-links',
            80
        );

        add_submenu_page(
            'auto-interlinker',
            __( 'Dashboard', 'auto-interlinker' ),
            __( 'Dashboard', 'auto-interlinker' ),
            'manage_options',
            'auto-interlinker',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'auto-interlinker',
            __( 'Settings', 'auto-interlinker' ),
            __( 'Settings', 'auto-interlinker' ),
            'manage_options',
            'auto-interlinker-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'auto-interlinker',
            __( 'Keywords', 'auto-interlinker' ),
            __( 'Keywords', 'auto-interlinker' ),
            'manage_options',
            'auto-interlinker-keywords',
            array( $this, 'render_keywords_page' )
        );

        add_submenu_page(
            'auto-interlinker',
            __( 'Link Log', 'auto-interlinker' ),
            __( 'Link Log', 'auto-interlinker' ),
            'manage_options',
            'auto-interlinker-logs',
            array( $this, 'render_logs_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'auto_interlinker_settings_group',
            'auto_interlinker_settings',
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['enabled']             = ! empty( $input['enabled'] ) ? 1 : 0;
        $sanitized['post_types']          = isset( $input['post_types'] ) ? array_map( 'sanitize_text_field', (array) $input['post_types'] ) : array( 'post' );
        $sanitized['max_links_per_post']  = isset( $input['max_links_per_post'] ) ? absint( $input['max_links_per_post'] ) : 5;
        $sanitized['max_keywords_per_post'] = isset( $input['max_keywords_per_post'] ) ? absint( $input['max_keywords_per_post'] ) : 20;
        $sanitized['min_keyword_length']  = isset( $input['min_keyword_length'] ) ? absint( $input['min_keyword_length'] ) : 4;
        $sanitized['open_new_tab']        = ! empty( $input['open_new_tab'] ) ? 1 : 0;
        $sanitized['link_once']           = ! empty( $input['link_once'] ) ? 1 : 0;
        $sanitized['nofollow']            = ! empty( $input['nofollow'] ) ? 1 : 0;
        $sanitized['exclude_post_ids']    = isset( $input['exclude_post_ids'] ) ? sanitize_text_field( $input['exclude_post_ids'] ) : '';

        // Invalidate cache when settings change.
        Auto_Interlinker_Engine::invalidate_cache();

        return $sanitized;
    }

    /**
     * Enqueue admin CSS and JS.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        $pages = array(
            'toplevel_page_auto-interlinker',
            'auto-interlinker_page_auto-interlinker-settings',
            'auto-interlinker_page_auto-interlinker-keywords',
            'auto-interlinker_page_auto-interlinker-logs',
        );

        if ( ! in_array( $hook, $pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'auto-interlinker-admin',
            AUTO_INTERLINKER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            AUTO_INTERLINKER_VERSION
        );

        wp_enqueue_script(
            'auto-interlinker-admin',
            AUTO_INTERLINKER_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            AUTO_INTERLINKER_VERSION,
            true
        );

        wp_localize_script(
            'auto-interlinker-admin',
            'autoInterlinker',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'auto_interlinker_nonce' ),
                'strings' => array(
                    'processing'    => __( 'Processing...', 'auto-interlinker' ),
                    'done'          => __( 'Done!', 'auto-interlinker' ),
                    'error'         => __( 'An error occurred. Please try again.', 'auto-interlinker' ),
                    'confirmClear'  => __( 'Are you sure you want to clear all link logs?', 'auto-interlinker' ),
                    'confirmReprocess' => __( 'This will reprocess all posts. This may take a while. Continue?', 'auto-interlinker' ),
                ),
            )
        );
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard_page() {
        $options     = get_option( 'auto_interlinker_settings', array() );
        $link_stats  = Auto_Interlinker_Database::get_link_stats( 10 );
        $total_links = count( Auto_Interlinker_Database::get_link_stats( 9999 ) );

        // Count indexed keywords.
        global $wpdb;
        $keyword_count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . Auto_Interlinker_Database::$keyword_table );
        $post_count    = $wpdb->get_var( 'SELECT COUNT(DISTINCT post_id) FROM ' . $wpdb->prefix . Auto_Interlinker_Database::$keyword_table );

        include AUTO_INTERLINKER_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $options    = get_option( 'auto_interlinker_settings', array() );
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        include AUTO_INTERLINKER_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render the keywords page.
     */
    public function render_keywords_page() {
        $all_keywords = Auto_Interlinker_Database::get_all_keywords();
        include AUTO_INTERLINKER_PLUGIN_DIR . 'admin/views/keywords.php';
    }

    /**
     * Render the link log page.
     */
    public function render_logs_page() {
        $link_stats = Auto_Interlinker_Database::get_link_stats( 100 );
        include AUTO_INTERLINKER_PLUGIN_DIR . 'admin/views/logs.php';
    }

    // -------------------------------------------------------------------------
    // AJAX Handlers
    // -------------------------------------------------------------------------

    /**
     * AJAX: Reprocess all posts.
     */
    public function ajax_reprocess_all() {
        check_ajax_referer( 'auto_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'auto-interlinker' ) ) );
        }

        $count = Auto_Interlinker_Post_Processor::full_reprocess();

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of posts processed */
                __( 'Successfully processed %d posts.', 'auto-interlinker' ),
                $count
            ),
            'count'   => $count,
        ) );
    }

    /**
     * AJAX: Process a single post.
     */
    public function ajax_process_single() {
        check_ajax_referer( 'auto_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'auto-interlinker' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'auto-interlinker' ) ) );
        }

        $keywords = Auto_Interlinker_Post_Processor::process_post( $post_id );

        wp_send_json_success( array(
            'message'  => sprintf(
                /* translators: %d: number of keywords */
                __( 'Extracted %d keywords.', 'auto-interlinker' ),
                count( $keywords )
            ),
            'keywords' => $keywords,
        ) );
    }

    /**
     * AJAX: Add a custom keyword to a post.
     */
    public function ajax_add_keyword() {
        check_ajax_referer( 'auto_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'auto-interlinker' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

        if ( ! $post_id || empty( $keyword ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data.', 'auto-interlinker' ) ) );
        }

        Auto_Interlinker_Database::add_custom_keyword( $post_id, $keyword );
        Auto_Interlinker_Engine::invalidate_cache();

        wp_send_json_success( array( 'message' => __( 'Keyword added.', 'auto-interlinker' ) ) );
    }

    /**
     * AJAX: Delete a keyword.
     */
    public function ajax_delete_keyword() {
        check_ajax_referer( 'auto_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'auto-interlinker' ) ) );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'auto-interlinker' ) ) );
        }

        Auto_Interlinker_Database::delete_keyword( $id );
        Auto_Interlinker_Engine::invalidate_cache();

        wp_send_json_success( array( 'message' => __( 'Keyword deleted.', 'auto-interlinker' ) ) );
    }

    /**
     * AJAX: Clear link logs.
     */
    public function ajax_clear_logs() {
        check_ajax_referer( 'auto_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'auto-interlinker' ) ) );
        }

        Auto_Interlinker_Database::clear_link_logs();

        wp_send_json_success( array( 'message' => __( 'Link logs cleared.', 'auto-interlinker' ) ) );
    }

    /**
     * AJAX: Get keywords for a specific post.
     */
    public function ajax_get_post_keywords() {
        check_ajax_referer( 'auto_interlinker_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'auto-interlinker' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'auto-interlinker' ) ) );
        }

        $keywords = Auto_Interlinker_Database::get_keywords_for_post( $post_id );

        wp_send_json_success( array( 'keywords' => $keywords ) );
    }

    /**
     * Add settings link on plugins page.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=auto-interlinker-settings' ) . '">' . __( 'Settings', 'auto-interlinker' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}
