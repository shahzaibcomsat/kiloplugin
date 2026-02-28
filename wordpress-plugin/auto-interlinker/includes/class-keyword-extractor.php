<?php
/**
 * Keyword extractor for Auto Interlinker plugin.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Extracts relevant keywords from post content.
 */
class Auto_Interlinker_Keyword_Extractor {

    /**
     * Common English stop words to ignore.
     *
     * @var array
     */
    private static $stop_words = array(
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and',
        'any', 'are', 'as', 'at', 'be', 'because', 'been', 'before', 'being', 'below',
        'between', 'both', 'but', 'by', 'can', 'did', 'do', 'does', 'doing', 'down',
        'during', 'each', 'few', 'for', 'from', 'further', 'get', 'got', 'had', 'has',
        'have', 'having', 'he', 'her', 'here', 'hers', 'herself', 'him', 'himself',
        'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself', 'just',
        'me', 'more', 'most', 'my', 'myself', 'no', 'nor', 'not', 'now', 'of', 'off',
        'on', 'once', 'only', 'or', 'other', 'our', 'ours', 'ourselves', 'out', 'over',
        'own', 's', 'same', 'she', 'should', 'so', 'some', 'such', 't', 'than', 'that',
        'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they',
        'this', 'those', 'through', 'to', 'too', 'under', 'until', 'up', 'us', 'very',
        'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom',
        'why', 'will', 'with', 'you', 'your', 'yours', 'yourself', 'yourselves',
        'also', 'back', 'been', 'come', 'could', 'go', 'good', 'great', 'know', 'like',
        'look', 'make', 'many', 'may', 'might', 'much', 'need', 'new', 'one', 'people',
        'said', 'say', 'see', 'take', 'think', 'time', 'two', 'use', 'way', 'well',
        'would', 'year',
    );

    /**
     * Extract keywords from post content and title.
     *
     * @param int $post_id Post ID.
     * @return array Associative array of keyword => frequency.
     */
    public static function extract( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array();
        }

        $options      = get_option( 'auto_interlinker_settings', array() );
        $min_length   = isset( $options['min_keyword_length'] ) ? absint( $options['min_keyword_length'] ) : 4;
        $max_keywords = isset( $options['max_keywords_per_post'] ) ? absint( $options['max_keywords_per_post'] ) : 20;

        // Combine title and content for analysis.
        $title   = $post->post_title;
        $content = wp_strip_all_tags( $post->post_content );
        $excerpt = $post->post_excerpt;

        // Give title words extra weight.
        $text = $title . ' ' . $title . ' ' . $title . ' ' . $excerpt . ' ' . $content;

        // Also extract from tags and categories.
        $tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
        if ( ! empty( $tags ) ) {
            $text .= ' ' . implode( ' ', $tags );
        }

        $categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
        if ( ! empty( $categories ) ) {
            $text .= ' ' . implode( ' ', $categories );
        }

        $keywords = self::analyze_text( $text, $min_length );

        // Extract multi-word phrases (bigrams and trigrams).
        $phrases = self::extract_phrases( $content, $min_length );
        foreach ( $phrases as $phrase => $freq ) {
            if ( isset( $keywords[ $phrase ] ) ) {
                $keywords[ $phrase ] += $freq * 2; // Boost phrase matches.
            } else {
                $keywords[ $phrase ] = $freq * 2;
            }
        }

        // Sort by frequency descending.
        arsort( $keywords );

        // Return top N keywords.
        return array_slice( $keywords, 0, $max_keywords, true );
    }

    /**
     * Analyze text and return word frequencies.
     *
     * @param string $text       Raw text.
     * @param int    $min_length Minimum word length.
     * @return array
     */
    private static function analyze_text( $text, $min_length = 4 ) {
        // Normalize text.
        $text = mb_strtolower( $text );
        $text = preg_replace( '/[^a-z0-9\s\-]/u', ' ', $text );
        $text = preg_replace( '/\s+/', ' ', trim( $text ) );

        $words     = explode( ' ', $text );
        $frequency = array();

        foreach ( $words as $word ) {
            $word = trim( $word, '-' );
            if (
                strlen( $word ) >= $min_length &&
                ! in_array( $word, self::$stop_words, true ) &&
                ! is_numeric( $word )
            ) {
                if ( isset( $frequency[ $word ] ) ) {
                    $frequency[ $word ]++;
                } else {
                    $frequency[ $word ] = 1;
                }
            }
        }

        return $frequency;
    }

    /**
     * Extract meaningful multi-word phrases (bigrams/trigrams).
     *
     * @param string $text       Content text.
     * @param int    $min_length Minimum word length.
     * @return array
     */
    private static function extract_phrases( $text, $min_length = 4 ) {
        $text    = mb_strtolower( wp_strip_all_tags( $text ) );
        $text    = preg_replace( '/[^a-z0-9\s]/u', ' ', $text );
        $text    = preg_replace( '/\s+/', ' ', trim( $text ) );
        $words   = explode( ' ', $text );
        $phrases = array();
        $count   = count( $words );

        for ( $i = 0; $i < $count - 1; $i++ ) {
            $w1 = trim( $words[ $i ] );
            $w2 = isset( $words[ $i + 1 ] ) ? trim( $words[ $i + 1 ] ) : '';
            $w3 = isset( $words[ $i + 2 ] ) ? trim( $words[ $i + 2 ] ) : '';

            // Skip stop words at start/end of phrase.
            if ( in_array( $w1, self::$stop_words, true ) || strlen( $w1 ) < $min_length ) {
                continue;
            }

            // Bigram.
            if ( ! empty( $w2 ) && ! in_array( $w2, self::$stop_words, true ) && strlen( $w2 ) >= $min_length ) {
                $bigram = $w1 . ' ' . $w2;
                $phrases[ $bigram ] = isset( $phrases[ $bigram ] ) ? $phrases[ $bigram ] + 1 : 1;

                // Trigram.
                if ( ! empty( $w3 ) && ! in_array( $w3, self::$stop_words, true ) && strlen( $w3 ) >= $min_length ) {
                    $trigram = $w1 . ' ' . $w2 . ' ' . $w3;
                    $phrases[ $trigram ] = isset( $phrases[ $trigram ] ) ? $phrases[ $trigram ] + 1 : 1;
                }
            }
        }

        // Only keep phrases that appear more than once.
        return array_filter( $phrases, function( $freq ) {
            return $freq > 1;
        } );
    }

    /**
     * Get the stop words list.
     *
     * @return array
     */
    public static function get_stop_words() {
        return self::$stop_words;
    }
}
