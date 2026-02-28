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
 * Core engine that injects interlinks into post content on the fly.
 */
class Auto_Interlinker_Engine {

    /**
     * Single instance.
     *
     * @var Auto_Interlinker_Engine
     */
    private static $instance = null;

    /**
     * Cached keyword map: keyword => array of post data.
     *
     * @var array|null
     */
    private $keyword_map = null;

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
     * Process post content and inject interlinks.
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

        // Check if interlinking is enabled.
        if ( isset( $options['enabled'] ) && ! $options['enabled'] ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
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

        $max_links        = isset( $options['max_links_per_post'] ) ? absint( $options['max_links_per_post'] ) : 5;
        $open_new_tab     = isset( $options['open_new_tab'] ) ? (bool) $options['open_new_tab'] : false;
        $link_once        = isset( $options['link_once'] ) ? (bool) $options['link_once'] : true;
        $nofollow         = isset( $options['nofollow'] ) ? (bool) $options['nofollow'] : false;

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
    private function inject_links( $content, $keyword_map, $current_post, $max_links, $open_new_tab, $link_once, $nofollow ) {
        $links_added      = 0;
        $linked_keywords  = array();
        $linked_targets   = array();

        // Sort keywords by length (longest first) to avoid partial replacements.
        uksort( $keyword_map, function( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        } );

        // Use DOMDocument to safely manipulate HTML.
        $dom = new DOMDocument( '1.0', 'UTF-8' );
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $xpath = new DOMXPath( $dom );

        // Get all text nodes that are NOT inside <a>, <script>, <style>, <code>, <pre> tags.
        $text_nodes = $xpath->query(
            '//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre)]'
        );

        if ( false === $text_nodes || 0 === $text_nodes->length ) {
            return $content;
        }

        foreach ( $keyword_map as $keyword => $target ) {
            if ( $links_added >= $max_links ) {
                break;
            }

            // Skip if we already linked this keyword (when link_once is true).
            if ( $link_once && in_array( strtolower( $keyword ), $linked_keywords, true ) ) {
                continue;
            }

            // Skip if we already linked to this target post.
            if ( in_array( $target['post_id'], $linked_targets, true ) ) {
                continue;
            }

            // Build link attributes.
            $rel    = $nofollow ? ' rel="nofollow"' : '';
            $target_attr = $open_new_tab ? ' target="_blank"' : '';

            $pattern = '/\b(' . preg_quote( $keyword, '/' ) . ')\b/iu';

            $replaced = false;

            // Iterate text nodes (snapshot to avoid live NodeList issues).
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
                $linked_keywords[] = strtolower( $keyword );
                $linked_targets[]  = $target['post_id'];

                // Log the link.
                Auto_Interlinker_Database::log_link( $current_post, $target['post_id'], $keyword );

                // Refresh text nodes after DOM modification.
                $text_nodes = $xpath->query(
                    '//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre)]'
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
     * Build keyword map for a given post (excludes self-links).
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

        $all_keywords = Auto_Interlinker_Database::get_all_keywords();
        $map          = array();

        foreach ( $all_keywords as $row ) {
            // Skip self-links.
            if ( (int) $row->post_id === (int) $current_post_id ) {
                continue;
            }

            $keyword = strtolower( $row->keyword );

            // Only add if not already mapped (first/highest-frequency wins).
            if ( ! isset( $map[ $keyword ] ) ) {
                $map[ $keyword ] = array(
                    'post_id' => (int) $row->post_id,
                    'url'     => get_permalink( $row->post_id ),
                    'title'   => $row->post_title,
                );
            }
        }

        set_transient( $cache_key, $map, 5 * MINUTE_IN_SECONDS );

        return $map;
    }

    /**
     * Invalidate the keyword map cache for a post.
     *
     * @param int $post_id Post ID.
     */
    public static function invalidate_cache( $post_id = null ) {
        if ( $post_id ) {
            delete_transient( 'auto_interlinker_map_' . $post_id );
        } else {
            // Clear all cached maps.
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_auto_interlinker_map_%'"
            );
        }
    }
}
