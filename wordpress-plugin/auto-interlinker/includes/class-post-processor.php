<?php
/**
 * Post processor for Auto Interlinker plugin.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles extracting and saving keywords when posts are saved or on bulk runs.
 */
class Auto_Interlinker_Post_Processor {

    /**
     * Single instance.
     *
     * @var Auto_Interlinker_Post_Processor
     */
    private static $instance = null;

    /**
     * Get single instance.
     *
     * @return Auto_Interlinker_Post_Processor
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — register hooks.
     */
    private function __construct() {
        add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
        add_action( 'delete_post', array( $this, 'on_delete_post' ) );
    }

    /**
     * Process a post when it is saved.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function on_save_post( $post_id, $post ) {
        // Skip autosaves, revisions, and non-published posts.
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( 'publish' !== $post->post_status ) {
            return;
        }

        $options            = get_option( 'auto_interlinker_settings', array() );
        $enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );

        if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
            return;
        }

        self::process_post( $post_id );
    }

    /**
     * Clean up keywords when a post is deleted.
     *
     * @param int $post_id Post ID.
     */
    public function on_delete_post( $post_id ) {
        global $wpdb;
        $table = $wpdb->prefix . Auto_Interlinker_Database::$keyword_table;
        $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );

        // Invalidate cache.
        Auto_Interlinker_Engine::invalidate_cache();
    }

    /**
     * Extract and save keywords for a single post.
     *
     * @param int $post_id Post ID.
     * @return array Extracted keywords.
     */
    public static function process_post( $post_id ) {
        $keywords = Auto_Interlinker_Keyword_Extractor::extract( $post_id );

        if ( ! empty( $keywords ) ) {
            Auto_Interlinker_Database::save_keywords( $post_id, $keywords );
        }

        // Invalidate cached keyword maps.
        Auto_Interlinker_Engine::invalidate_cache();

        return $keywords;
    }

    /**
     * Bulk process all published posts (called by cron or manual trigger).
     *
     * @param int $batch_size Number of posts to process per run.
     * @return int Number of posts processed.
     */
    public static function bulk_process_posts( $batch_size = 50 ) {
        $options            = get_option( 'auto_interlinker_settings', array() );
        $enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );

        // Get the offset from a transient to allow batching.
        $offset = (int) get_transient( 'auto_interlinker_bulk_offset' );

        $posts = get_posts( array(
            'post_type'      => $enabled_post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        if ( empty( $posts ) ) {
            // Reset offset when done.
            delete_transient( 'auto_interlinker_bulk_offset' );
            return 0;
        }

        foreach ( $posts as $post_id ) {
            self::process_post( $post_id );
        }

        // Advance offset for next batch.
        set_transient( 'auto_interlinker_bulk_offset', $offset + count( $posts ), DAY_IN_SECONDS );

        return count( $posts );
    }

    /**
     * Full bulk reprocess — resets offset and processes all posts.
     *
     * @return int Total posts processed.
     */
    public static function full_reprocess() {
        delete_transient( 'auto_interlinker_bulk_offset' );
        Auto_Interlinker_Database::clear_all_keywords();
        Auto_Interlinker_Engine::invalidate_cache();

        $options            = get_option( 'auto_interlinker_settings', array() );
        $enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );

        $total = 0;
        $page  = 1;

        do {
            $posts = get_posts( array(
                'post_type'      => $enabled_post_types,
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'paged'          => $page,
                'fields'         => 'ids',
            ) );

            foreach ( $posts as $post_id ) {
                self::process_post( $post_id );
                $total++;
            }

            $page++;
        } while ( ! empty( $posts ) );

        return $total;
    }
}
