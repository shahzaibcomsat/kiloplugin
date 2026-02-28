<?php
/**
 * Database handler for Auto Interlinker plugin.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all database operations.
 */
class Auto_Interlinker_Database {

    /**
     * Table name for keyword index.
     *
     * @var string
     */
    public static $keyword_table = 'auto_interlinker_keywords';

    /**
     * Table name for link log.
     *
     * @var string
     */
    public static $links_table = 'auto_interlinker_links';

    /**
     * Install / create database tables on plugin activation.
     */
    public static function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $keyword_table   = $wpdb->prefix . self::$keyword_table;
        $links_table     = $wpdb->prefix . self::$links_table;

        // Keywords index table: stores keywords extracted from each post.
        $sql_keywords = "CREATE TABLE IF NOT EXISTS {$keyword_table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id     BIGINT(20) UNSIGNED NOT NULL,
            keyword     VARCHAR(255)        NOT NULL,
            frequency   INT(11)             NOT NULL DEFAULT 1,
            is_custom   TINYINT(1)          NOT NULL DEFAULT 0,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id  (post_id),
            KEY keyword  (keyword(191))
        ) {$charset_collate};";

        // Links log table: tracks which links were inserted where.
        $sql_links = "CREATE TABLE IF NOT EXISTS {$links_table} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_post_id  BIGINT(20) UNSIGNED NOT NULL,
            target_post_id  BIGINT(20) UNSIGNED NOT NULL,
            keyword         VARCHAR(255)        NOT NULL,
            created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY source_post_id (source_post_id),
            KEY target_post_id (target_post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_keywords );
        dbDelta( $sql_links );

        update_option( 'auto_interlinker_db_version', AUTO_INTERLINKER_VERSION );
    }

    /**
     * Save keywords for a post (replaces existing entries).
     *
     * @param int   $post_id  Post ID.
     * @param array $keywords Associative array of keyword => frequency.
     */
    public static function save_keywords( $post_id, $keywords ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$keyword_table;

        // Remove auto-extracted keywords (keep custom ones).
        $wpdb->delete(
            $table,
            array(
                'post_id'   => $post_id,
                'is_custom' => 0,
            ),
            array( '%d', '%d' )
        );

        foreach ( $keywords as $keyword => $frequency ) {
            $wpdb->insert(
                $table,
                array(
                    'post_id'   => $post_id,
                    'keyword'   => sanitize_text_field( $keyword ),
                    'frequency' => absint( $frequency ),
                    'is_custom' => 0,
                ),
                array( '%d', '%s', '%d', '%d' )
            );
        }
    }

    /**
     * Add a custom keyword for a post.
     *
     * @param int    $post_id Post ID.
     * @param string $keyword Keyword.
     */
    public static function add_custom_keyword( $post_id, $keyword ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$keyword_table;

        $wpdb->insert(
            $table,
            array(
                'post_id'   => $post_id,
                'keyword'   => sanitize_text_field( $keyword ),
                'frequency' => 1,
                'is_custom' => 1,
            ),
            array( '%d', '%s', '%d', '%d' )
        );
    }

    /**
     * Delete a keyword entry.
     *
     * @param int $id Keyword row ID.
     */
    public static function delete_keyword( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$keyword_table;
        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Get all keywords for a specific post.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public static function get_keywords_for_post( $post_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$keyword_table;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE post_id = %d ORDER BY frequency DESC",
                $post_id
            )
        );
    }

    /**
     * Get all keywords across all posts (for building the interlink map).
     *
     * @return array
     */
    public static function get_all_keywords() {
        global $wpdb;
        $table = $wpdb->prefix . self::$keyword_table;

        return $wpdb->get_results(
            "SELECT k.keyword, k.post_id, p.post_title, p.post_status
             FROM {$table} k
             INNER JOIN {$wpdb->posts} p ON p.ID = k.post_id
             WHERE p.post_status = 'publish'
             ORDER BY k.frequency DESC"
        );
    }

    /**
     * Log an inserted link.
     *
     * @param int    $source_post_id Source post ID.
     * @param int    $target_post_id Target post ID.
     * @param string $keyword        Keyword used.
     */
    public static function log_link( $source_post_id, $target_post_id, $keyword ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$links_table;

        // Avoid duplicate log entries.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE source_post_id = %d AND target_post_id = %d AND keyword = %s",
                $source_post_id,
                $target_post_id,
                $keyword
            )
        );

        if ( ! $exists ) {
            $wpdb->insert(
                $table,
                array(
                    'source_post_id' => $source_post_id,
                    'target_post_id' => $target_post_id,
                    'keyword'        => sanitize_text_field( $keyword ),
                ),
                array( '%d', '%d', '%s' )
            );
        }
    }

    /**
     * Get link statistics.
     *
     * @param int $limit Number of rows to return.
     * @return array
     */
    public static function get_link_stats( $limit = 50 ) {
        global $wpdb;
        $table = $wpdb->prefix . self::$links_table;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, 
                        sp.post_title AS source_title,
                        tp.post_title AS target_title
                 FROM {$table} l
                 LEFT JOIN {$wpdb->posts} sp ON sp.ID = l.source_post_id
                 LEFT JOIN {$wpdb->posts} tp ON tp.ID = l.target_post_id
                 ORDER BY l.created_at DESC
                 LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Clear all link logs.
     */
    public static function clear_link_logs() {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::$links_table );
    }

    /**
     * Clear all keyword data.
     */
    public static function clear_all_keywords() {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::$keyword_table );
    }
}
