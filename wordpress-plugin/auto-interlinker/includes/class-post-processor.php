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
 * Handles extracting/saving keywords and permanently applying interlinks to posts.
 */
class Auto_Interlinker_Post_Processor {

	/**
	 * Single instance.
	 *
	 * @var Auto_Interlinker_Post_Processor
	 */
	private static $instance = null;

	/**
	 * Flag to prevent recursive save_post calls.
	 *
	 * @var bool
	 */
	private static $is_applying_links = false;

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
		// Priority 20 so it runs after other plugins have finished saving.
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
		// Prevent infinite loops when we update post content ourselves.
		if ( self::$is_applying_links ) {
			return;
		}

		// Skip autosaves and revisions.
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

		// Step 1: Extract and save keywords for this post.
		self::process_post( $post_id );

		// Step 2: Apply interlinks permanently to this post.
		self::$is_applying_links = true;
		Auto_Interlinker_Engine::apply_links_to_post( $post_id );
		self::$is_applying_links = false;

		// Step 3: Re-apply links to OTHER posts that may now link to this post.
		// We do this asynchronously via a scheduled event to avoid slowing down the save.
		if ( ! wp_next_scheduled( 'auto_interlinker_relink_others', array( $post_id ) ) ) {
			wp_schedule_single_event( time() + 30, 'auto_interlinker_relink_others', array( $post_id ) );
		}
	}

	/**
	 * Clean up keywords and remove links when a post is deleted.
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
	 * Re-apply links to posts that may reference the newly saved post.
	 * Called via scheduled event.
	 *
	 * @param int $new_post_id The newly saved post ID.
	 */
	public static function relink_posts_referencing( $new_post_id ) {
		$options            = get_option( 'auto_interlinker_settings', array() );
		$enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );

		// Get keywords for the new post.
		$keywords = Auto_Interlinker_Database::get_keywords_for_post( $new_post_id );
		if ( empty( $keywords ) ) {
			return;
		}

		// Find posts that contain any of these keywords in their content.
		global $wpdb;
		$keyword_values = array_map( function( $k ) { return $k->keyword; }, $keywords );

		// Limit to top 5 keywords to avoid too many queries.
		$keyword_values = array_slice( $keyword_values, 0, 5 );

		foreach ( $keyword_values as $keyword ) {
			$posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_status = 'publish'
					 AND post_type IN ('" . implode( "','", array_map( 'esc_sql', $enabled_post_types ) ) . "')
					 AND ID != %d
					 AND post_content LIKE %s
					 LIMIT 20",
					$new_post_id,
					'%' . $wpdb->esc_like( $keyword ) . '%'
				)
			);

			foreach ( $posts as $post_id ) {
				self::$is_applying_links = true;
				Auto_Interlinker_Engine::invalidate_cache( (int) $post_id );
				Auto_Interlinker_Engine::apply_links_to_post( (int) $post_id );
				self::$is_applying_links = false;
			}
		}
	}

	/**
	 * Bulk process all published posts — extract keywords only.
	 *
	 * @param int $batch_size Number of posts to process per run.
	 * @return int Number of posts processed.
	 */
	public static function bulk_process_posts( $batch_size = 50 ) {
		$options            = get_option( 'auto_interlinker_settings', array() );
		$enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );

		// Get the offset from a transient to allow batching.
		$offset = (int) get_transient( 'auto_interlinker_bulk_offset' );

		$posts = get_posts(
			array(
				'post_type'      => $enabled_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

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
	 * Full bulk reprocess — resets offset, re-extracts keywords, and permanently applies links.
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

		// Pass 1: Extract keywords for all posts.
		do {
			$posts = get_posts(
				array(
					'post_type'      => $enabled_post_types,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'paged'          => $page,
					'fields'         => 'ids',
				)
			);

			foreach ( $posts as $post_id ) {
				self::process_post( $post_id );
				$total++;
			}

			$page++;
		} while ( ! empty( $posts ) );

		// Pass 2: Apply interlinks permanently to all posts now that keyword index is complete.
		$page = 1;
		do {
			$posts = get_posts(
				array(
					'post_type'      => $enabled_post_types,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'paged'          => $page,
					'fields'         => 'ids',
				)
			);

			self::$is_applying_links = true;
			foreach ( $posts as $post_id ) {
				Auto_Interlinker_Engine::apply_links_to_post( $post_id );
			}
			self::$is_applying_links = false;

			$page++;
		} while ( ! empty( $posts ) );

		return $total;
	}

	/**
	 * Strip all auto-interlinks from all posts (undo permanent links).
	 *
	 * @return int Number of posts cleaned.
	 */
	public static function strip_all_links() {
		$options            = get_option( 'auto_interlinker_settings', array() );
		$enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );

		$total = 0;
		$page  = 1;

		do {
			$posts = get_posts(
				array(
					'post_type'      => $enabled_post_types,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'paged'          => $page,
					'fields'         => 'ids',
				)
			);

			global $wpdb;
			self::$is_applying_links = true;

			foreach ( $posts as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				$clean = Auto_Interlinker_Engine::strip_auto_interlinks( $post->post_content );
				if ( $clean !== $post->post_content ) {
					$wpdb->update(
						$wpdb->posts,
						array( 'post_content' => $clean ),
						array( 'ID' => $post_id ),
						array( '%s' ),
						array( '%d' )
					);
					clean_post_cache( $post_id );
					$total++;
				}
			}

			self::$is_applying_links = false;
			$page++;
		} while ( ! empty( $posts ) );

		Auto_Interlinker_Database::clear_link_logs();

		return $total;
	}
}
