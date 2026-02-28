<?php
/**
 * Keywords view for Auto Interlinker.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Group keywords by post.
$keywords_by_post = array();
foreach ( $all_keywords as $row ) {
    $keywords_by_post[ $row->post_id ][] = $row;
}
?>
<div class="wrap ai-wrap">
    <h1 class="ai-page-title">
        <span class="dashicons dashicons-tag"></span>
        <?php esc_html_e( 'Auto Interlinker — Keywords', 'auto-interlinker' ); ?>
    </h1>

    <p><?php esc_html_e( 'Below is the keyword index. Keywords are automatically extracted from post content. You can also add custom keywords to any post.', 'auto-interlinker' ); ?></p>

    <!-- Add Custom Keyword -->
    <div class="ai-card">
        <h2><?php esc_html_e( 'Add Custom Keyword', 'auto-interlinker' ); ?></h2>
        <div class="ai-inline-form">
            <input type="number" id="ai-kw-post-id" class="regular-text" placeholder="<?php esc_attr_e( 'Post ID', 'auto-interlinker' ); ?>" min="1" />
            <input type="text" id="ai-kw-keyword" class="regular-text" placeholder="<?php esc_attr_e( 'Keyword or phrase', 'auto-interlinker' ); ?>" />
            <button id="ai-add-keyword" class="button button-primary">
                <?php esc_html_e( 'Add Keyword', 'auto-interlinker' ); ?>
            </button>
            <span id="ai-kw-status" class="ai-action-status"></span>
        </div>
    </div>

    <!-- Keyword Index -->
    <?php if ( empty( $keywords_by_post ) ) : ?>
        <div class="ai-card">
            <p class="ai-empty">
                <?php esc_html_e( 'No keywords indexed yet. Go to the Dashboard and click "Reprocess All Posts" to build the keyword index.', 'auto-interlinker' ); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="ai-card">
            <h2><?php esc_html_e( 'Keyword Index', 'auto-interlinker' ); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %d: number of posts */
                    esc_html__( 'Showing keywords for %d posts.', 'auto-interlinker' ),
                    count( $keywords_by_post )
                );
                ?>
            </p>

            <div id="ai-keywords-accordion">
                <?php foreach ( $keywords_by_post as $post_id => $keywords ) :
                    $post = get_post( $post_id );
                    if ( ! $post ) continue;
                ?>
                    <div class="ai-accordion-item">
                        <div class="ai-accordion-header">
                            <span class="dashicons dashicons-arrow-right-alt2 ai-accordion-icon"></span>
                            <strong><?php echo esc_html( $post->post_title ); ?></strong>
                            <span class="ai-badge"><?php echo count( $keywords ); ?> <?php esc_html_e( 'keywords', 'auto-interlinker' ); ?></span>
                            <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" class="ai-post-link">
                                <span class="dashicons dashicons-external"></span>
                            </a>
                            <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="ai-post-link">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                        </div>
                        <div class="ai-accordion-body" style="display:none;">
                            <table class="widefat striped ai-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Keyword', 'auto-interlinker' ); ?></th>
                                        <th><?php esc_html_e( 'Frequency', 'auto-interlinker' ); ?></th>
                                        <th><?php esc_html_e( 'Type', 'auto-interlinker' ); ?></th>
                                        <th><?php esc_html_e( 'Actions', 'auto-interlinker' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $keywords as $kw ) : ?>
                                        <tr id="ai-kw-row-<?php echo esc_attr( $kw->id ); ?>">
                                            <td><code><?php echo esc_html( $kw->keyword ); ?></code></td>
                                            <td><?php echo esc_html( $kw->frequency ); ?></td>
                                            <td>
                                                <?php if ( $kw->is_custom ) : ?>
                                                    <span class="ai-badge ai-badge-custom"><?php esc_html_e( 'Custom', 'auto-interlinker' ); ?></span>
                                                <?php else : ?>
                                                    <span class="ai-badge ai-badge-auto"><?php esc_html_e( 'Auto', 'auto-interlinker' ); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button
                                                    class="button button-small ai-delete-keyword"
                                                    data-id="<?php echo esc_attr( $kw->id ); ?>"
                                                >
                                                    <?php esc_html_e( 'Delete', 'auto-interlinker' ); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
