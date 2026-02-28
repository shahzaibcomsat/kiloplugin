<?php
/**
 * Settings view for Auto Interlinker.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$enabled             = isset( $options['enabled'] ) ? (bool) $options['enabled'] : true;
$selected_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );
$max_links           = isset( $options['max_links_per_post'] ) ? absint( $options['max_links_per_post'] ) : 5;
$max_keywords        = isset( $options['max_keywords_per_post'] ) ? absint( $options['max_keywords_per_post'] ) : 20;
$min_length          = isset( $options['min_keyword_length'] ) ? absint( $options['min_keyword_length'] ) : 4;
$open_new_tab        = isset( $options['open_new_tab'] ) ? (bool) $options['open_new_tab'] : false;
$link_once           = isset( $options['link_once'] ) ? (bool) $options['link_once'] : true;
$nofollow            = isset( $options['nofollow'] ) ? (bool) $options['nofollow'] : false;
$exclude_post_ids    = isset( $options['exclude_post_ids'] ) ? $options['exclude_post_ids'] : '';
?>
<div class="wrap ai-wrap">
    <h1 class="ai-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e( 'Auto Interlinker — Settings', 'auto-interlinker' ); ?>
    </h1>

    <?php settings_errors( 'auto_interlinker_settings' ); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'auto_interlinker_settings_group' ); ?>

        <!-- General Settings -->
        <div class="ai-card">
            <h2><?php esc_html_e( 'General Settings', 'auto-interlinker' ); ?></h2>
            <table class="form-table ai-form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Interlinking', 'auto-interlinker' ); ?></th>
                    <td>
                        <label class="ai-toggle">
                            <input type="checkbox" name="auto_interlinker_settings[enabled]" value="1" <?php checked( $enabled ); ?> />
                            <span class="ai-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Master switch to enable or disable automatic interlinking.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Post Types', 'auto-interlinker' ); ?></th>
                    <td>
                        <?php foreach ( $post_types as $pt ) : ?>
                            <label class="ai-checkbox-label">
                                <input
                                    type="checkbox"
                                    name="auto_interlinker_settings[post_types][]"
                                    value="<?php echo esc_attr( $pt->name ); ?>"
                                    <?php checked( in_array( $pt->name, $selected_post_types, true ) ); ?>
                                />
                                <?php echo esc_html( $pt->label ); ?>
                                <code>(<?php echo esc_html( $pt->name ); ?>)</code>
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Select which post types to scan and interlink.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Exclude Post IDs', 'auto-interlinker' ); ?></th>
                    <td>
                        <input
                            type="text"
                            name="auto_interlinker_settings[exclude_post_ids]"
                            value="<?php echo esc_attr( $exclude_post_ids ); ?>"
                            class="regular-text"
                            placeholder="1, 2, 3"
                        />
                        <p class="description"><?php esc_html_e( 'Comma-separated list of post IDs to exclude from interlinking.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Link Settings -->
        <div class="ai-card">
            <h2><?php esc_html_e( 'Link Settings', 'auto-interlinker' ); ?></h2>
            <table class="form-table ai-form-table">
                <tr>
                    <th scope="row">
                        <label for="max_links_per_post"><?php esc_html_e( 'Max Links Per Post', 'auto-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="max_links_per_post"
                            name="auto_interlinker_settings[max_links_per_post]"
                            value="<?php echo esc_attr( $max_links ); ?>"
                            min="1"
                            max="50"
                            class="small-text"
                        />
                        <p class="description"><?php esc_html_e( 'Maximum number of interlinks to insert per post. Recommended: 3–7.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="link_once"><?php esc_html_e( 'Link Each Keyword Once', 'auto-interlinker' ); ?></label>
                    </th>
                    <td>
                        <label class="ai-toggle">
                            <input type="checkbox" id="link_once" name="auto_interlinker_settings[link_once]" value="1" <?php checked( $link_once ); ?> />
                            <span class="ai-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Only link the first occurrence of each keyword in a post.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="open_new_tab"><?php esc_html_e( 'Open Links in New Tab', 'auto-interlinker' ); ?></label>
                    </th>
                    <td>
                        <label class="ai-toggle">
                            <input type="checkbox" id="open_new_tab" name="auto_interlinker_settings[open_new_tab]" value="1" <?php checked( $open_new_tab ); ?> />
                            <span class="ai-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Add target="_blank" to all interlinks.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nofollow"><?php esc_html_e( 'Add Nofollow', 'auto-interlinker' ); ?></label>
                    </th>
                    <td>
                        <label class="ai-toggle">
                            <input type="checkbox" id="nofollow" name="auto_interlinker_settings[nofollow]" value="1" <?php checked( $nofollow ); ?> />
                            <span class="ai-toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Add rel="nofollow" to all interlinks (not recommended for internal links).', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Keyword Settings -->
        <div class="ai-card">
            <h2><?php esc_html_e( 'Keyword Extraction Settings', 'auto-interlinker' ); ?></h2>
            <table class="form-table ai-form-table">
                <tr>
                    <th scope="row">
                        <label for="max_keywords_per_post"><?php esc_html_e( 'Max Keywords Per Post', 'auto-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="max_keywords_per_post"
                            name="auto_interlinker_settings[max_keywords_per_post]"
                            value="<?php echo esc_attr( $max_keywords ); ?>"
                            min="5"
                            max="100"
                            class="small-text"
                        />
                        <p class="description"><?php esc_html_e( 'Maximum number of keywords to extract and index per post.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="min_keyword_length"><?php esc_html_e( 'Minimum Keyword Length', 'auto-interlinker' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="min_keyword_length"
                            name="auto_interlinker_settings[min_keyword_length]"
                            value="<?php echo esc_attr( $min_length ); ?>"
                            min="2"
                            max="10"
                            class="small-text"
                        />
                        <p class="description"><?php esc_html_e( 'Minimum number of characters for a word to be considered a keyword.', 'auto-interlinker' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Settings', 'auto-interlinker' ), 'primary large' ); ?>
    </form>
</div>
