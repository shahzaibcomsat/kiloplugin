<?php
/**
 * Interlinking engine for Auto Interlinker plugin.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core engine that injects interlinks into post content.
 *
 * Supports two modes:
 *  1. On-the-fly: hooks into `the_content` filter for live display.
 *  2. Permanent: directly updates post_content in the database.
 */
class Auto_Interlinker_Engine {

	/**
	 * Single instance.
	 *
	 * @var Auto_Interlinker_Engine
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return Auto_Interlinker_Engine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register content filter.
	 */
	private function __construct() {
		add_filter( 'the_content', array( $this, 'process_content' ), 20 );
	}

	/**
	 * Process post content and inject interlinks (on-the-fly display filter).
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function process_content( $content ) {
		// Only run on single posts/pages in the main query.
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$options = get_option( 'auto_interlinker_settings', array() );

		// Check if interlinking is enabled (default: enabled).
		if ( isset( $options['enabled'] ) && ! $options['enabled'] ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Check excluded post IDs.
		if ( $this->is_post_excluded( $post_id, $options ) ) {
			return $content;
		}

		// Check if this post type is enabled.
		$enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );
		if ( ! in_array( get_post_type( $post_id ), $enabled_post_types, true ) ) {
			return $content;
		}

		$keyword_map = $this->get_keyword_map( $post_id );
		if ( empty( $keyword_map ) ) {
			return $content;
		}

		$max_links    = isset( $options['max_links_per_post'] ) ? absint( $options['max_links_per_post'] ) : 5;
		$open_new_tab = isset( $options['open_new_tab'] ) ? (bool) $options['open_new_tab'] : false;
		$link_once    = isset( $options['link_once'] ) ? (bool) $options['link_once'] : true;
		$nofollow     = isset( $options['nofollow'] ) ? (bool) $options['nofollow'] : false;

		return $this->inject_links(
			$content,
			$keyword_map,
			$post_id,
			$max_links,
			$open_new_tab,
			$link_once,
			$nofollow
		);
	}

	/**
	 * Permanently apply interlinks to a post's content in the database.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if content was updated, false otherwise.
	 */
	public static function apply_links_to_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}

		$options = get_option( 'auto_interlinker_settings', array() );

		// Check if interlinking is enabled.
		if ( isset( $options['enabled'] ) && ! $options['enabled'] ) {
			return false;
		}

		// Check excluded post IDs.
		$instance = self::get_instance();
		if ( $instance->is_post_excluded( $post_id, $options ) ) {
			return false;
		}

		// Check if this post type is enabled.
		$enabled_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return false;
		}

		// First strip any previously inserted auto-interlinks to avoid duplicates.
		$clean_content = self::strip_auto_interlinks( $post->post_content );

		$keyword_map = $instance->get_keyword_map( $post_id );
		if ( empty( $keyword_map ) ) {
			return false;
		}

		$max_links    = isset( $options['max_links_per_post'] ) ? absint( $options['max_links_per_post'] ) : 5;
		$open_new_tab = isset( $options['open_new_tab'] ) ? (bool) $options['open_new_tab'] : false;
		$link_once    = isset( $options['link_once'] ) ? (bool) $options['link_once'] : true;
		$nofollow     = isset( $options['nofollow'] ) ? (bool) $options['nofollow'] : false;

		$new_content = $instance->inject_links(
			$clean_content,
			$keyword_map,
			$post_id,
			$max_links,
			$open_new_tab,
			$link_once,
			$nofollow
		);

		if ( $new_content === $clean_content ) {
			return false; // Nothing changed.
		}

		// Update post content directly (bypass hooks to avoid infinite loops).
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $new_content ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Clear post cache.
		clean_post_cache( $post_id );

		return true;
	}

	/**
	 * Remove all auto-interlinks from content (strips <a class="auto-interlink"> tags).
	 *
	 * @param string $content Post content.
	 * @return string Cleaned content.
	 */
	public static function strip_auto_interlinks( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// Remove <a class="auto-interlink" ...>text</a> and replace with just the text.
		$content = preg_replace(
			'/<a\s[^>]*class=["\'][^"\']*auto-interlink[^"\']*["\'][^>]*>(.*?)<\/a>/is',
			'$1',
			$content
		);

		return $content;
	}

	/**
	 * Inject links into content.
	 *
	 * @param string $content      Post content HTML.
	 * @param array  $keyword_map  Keyword => post data map.
	 * @param int    $current_post Current post ID.
	 * @param int    $max_links    Maximum links to insert.
	 * @param bool   $open_new_tab Open links in new tab.
	 * @param bool   $link_once    Only link each keyword once.
	 * @param bool   $nofollow     Add nofollow attribute.
	 * @return string
	 */
	public function inject_links( $content, $keyword_map, $current_post, $max_links, $open_new_tab, $link_once, $nofollow ) {
		if ( empty( $content ) || empty( $keyword_map ) ) {
			return $content;
		}

		$links_added     = 0;
		$linked_keywords = array();
		$linked_targets  = array();

		// Sort keywords by length (longest first) to avoid partial replacements.
		uksort(
			$keyword_map,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		// Use DOMDocument to safely manipulate HTML.
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Get all text nodes NOT inside <a>, <script>, <style>, <code>, <pre>, <h1>-<h6> tags.
		$text_nodes = $xpath->query(
			'//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6)]'
		);

		if ( false === $text_nodes || 0 === $text_nodes->length ) {
			return $content;
		}

		foreach ( $keyword_map as $keyword => $target ) {
			if ( $links_added >= $max_links ) {
				break;
			}

			$keyword_lower = strtolower( $keyword );

			// Skip if we already linked this keyword (when link_once is true).
			if ( $link_once && in_array( $keyword_lower, $linked_keywords, true ) ) {
				continue;
			}

			// Skip if we already linked to this target post.
			if ( in_array( $target['post_id'], $linked_targets, true ) ) {
				continue;
			}

			$pattern = '/\b(' . preg_quote( $keyword, '/' ) . ')\b/iu';

			$replaced = false;

			// Snapshot text nodes to avoid live NodeList issues.
			$nodes_array = array();
			foreach ( $text_nodes as $node ) {
				$nodes_array[] = $node;
			}

			foreach ( $nodes_array as $text_node ) {
				$text = $text_node->nodeValue;

				if ( ! preg_match( $pattern, $text ) ) {
					continue;
				}

				// Split text around the keyword.
				$parts = preg_split( $pattern, $text, 2, PREG_SPLIT_DELIM_CAPTURE );
				if ( count( $parts ) < 3 ) {
					continue;
				}

				$parent = $text_node->parentNode;
				if ( ! $parent ) {
					continue;
				}

				// Build replacement nodes.
				$before_node = $dom->createTextNode( $parts[0] );
				$after_node  = $dom->createTextNode( $parts[2] );

				// Create <a> element.
				$link_node = $dom->createElement( 'a' );
				$link_node->setAttribute( 'href', esc_url( $target['url'] ) );
				$link_node->setAttribute( 'title', esc_attr( $target['title'] ) );
				if ( $nofollow ) {
					$link_node->setAttribute( 'rel', 'nofollow' );
				}
				if ( $open_new_tab ) {
					$link_node->setAttribute( 'target', '_blank' );
					$rel = $nofollow ? 'nofollow noopener noreferrer' : 'noopener noreferrer';
					$link_node->setAttribute( 'rel', $rel );
				}
				$link_node->setAttribute( 'class', 'auto-interlink' );
				$link_node->appendChild( $dom->createTextNode( $parts[1] ) );

				// Insert nodes before the original text node.
				$parent->insertBefore( $before_node, $text_node );
				$parent->insertBefore( $link_node, $text_node );
				$parent->insertBefore( $after_node, $text_node );

				// Remove original text node.
				$parent->removeChild( $text_node );

				$replaced = true;
				break; // Only replace first occurrence per keyword per pass.
			}

			if ( $replaced ) {
				$links_added++;
				$linked_keywords[] = $keyword_lower;
				$linked_targets[]  = $target['post_id'];

				// Log the link.
				Auto_Interlinker_Database::log_link( $current_post, $target['post_id'], $keyword );

				// Refresh text nodes after DOM modification.
				$text_nodes = $xpath->query(
					'//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6)]'
				);
			}
		}

		// Extract modified HTML body content.
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( $body ) {
			$new_content = '';
			foreach ( $body->childNodes as $child ) {
				$new_content .= $dom->saveHTML( $child );
			}
			return $new_content;
		}

		return $content;
	}

	/**
	 * Check if a post is in the excluded list.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $options Plugin options.
	 * @return bool
	 */
	private function is_post_excluded( $post_id, $options ) {
		if ( empty( $options['exclude_post_ids'] ) ) {
			return false;
		}

		$excluded = array_map( 'absint', explode( ',', $options['exclude_post_ids'] ) );
		$excluded = array_filter( $excluded );

		return in_array( (int) $post_id, $excluded, true );
	}

	/**
	 * Build keyword map for a given post (excludes self-links and excluded posts).
	 *
	 * @param int $current_post_id Current post ID.
	 * @return array keyword => array( post_id, url, title )
	 */
	private function get_keyword_map( $current_post_id ) {
		// Use transient cache (5 minutes).
		$cache_key = 'auto_interlinker_map_' . $current_post_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$options      = get_option( 'auto_interlinker_settings', array() );
		$all_keywords = Auto_Interlinker_Database::get_all_keywords();
		$map          = array();

		// Build excluded IDs list.
		$excluded = array();
		if ( ! empty( $options['exclude_post_ids'] ) ) {
			$excluded = array_map( 'absint', explode( ',', $options['exclude_post_ids'] ) );
			$excluded = array_filter( $excluded );
		}

		foreach ( $all_keywords as $row ) {
			$row_post_id = (int) $row->post_id;

			// Skip self-links.
			if ( $row_post_id === (int) $current_post_id ) {
				continue;
			}

			// Skip excluded posts.
			if ( in_array( $row_post_id, $excluded, true ) ) {
				continue;
			}

			$keyword = strtolower( trim( $row->keyword ) );

			if ( empty( $keyword ) ) {
				continue;
			}

			// Only add if not already mapped (first/highest-frequency wins).
			if ( ! isset( $map[ $keyword ] ) ) {
				$permalink = get_permalink( $row_post_id );
				if ( $permalink ) {
					$map[ $keyword ] = array(
						'post_id' => $row_post_id,
						'url'     => $permalink,
						'title'   => isset( $row->post_title ) ? $row->post_title : '',
					);
				}
			}
		}

		set_transient( $cache_key, $map, 5 * MINUTE_IN_SECONDS );

		return $map;
	}

	/**
	 * Invalidate the keyword map cache for a post or all posts.
	 *
	 * @param int|null $post_id Post ID, or null to clear all.
	 */
	public static function invalidate_cache( $post_id = null ) {
		if ( $post_id ) {
			delete_transient( 'auto_interlinker_map_' . $post_id );
		} else {
			// Clear all cached maps.
			global $wpdb;
			$wpdb->query(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_auto_interlinker_map_%' OR option_name LIKE '_transient_timeout_auto_interlinker_map_%'"
			);
		}
	}
}
