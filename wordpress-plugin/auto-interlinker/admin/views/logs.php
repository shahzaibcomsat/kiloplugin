<?php
/**
 * Link log view for Auto Interlinker.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap ai-wrap">
    <h1 class="ai-page-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e( 'Auto Interlinker — Link Log', 'auto-interlinker' ); ?>
    </h1>

    <p><?php esc_html_e( 'This log records every interlink that has been inserted into post content. Links are logged when a visitor views a post.', 'auto-interlinker' ); ?></p>

    <div class="ai-card">
        <div class="ai-card-header">
            <h2><?php esc_html_e( 'Interlink Log', 'auto-interlinker' ); ?></h2>
            <button id="ai-clear-logs" class="button button-secondary">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e( 'Clear All Logs', 'auto-interlinker' ); ?>
            </button>
        </div>

        <?php if ( empty( $link_stats ) ) : ?>
            <p class="ai-empty"><?php esc_html_e( 'No links logged yet.', 'auto-interlinker' ); ?></p>
        <?php else : ?>
            <table class="widefat striped ai-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Source Post', 'auto-interlinker' ); ?></th>
                        <th><?php esc_html_e( 'Keyword Used', 'auto-interlinker' ); ?></th>
                        <th><?php esc_html_e( 'Target Post', 'auto-interlinker' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'auto-interlinker' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $link_stats as $i => $link ) : ?>
                        <tr>
                            <td><?php echo esc_html( $i + 1 ); ?></td>
                            <td>
                                <?php if ( $link->source_title ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( $link->source_post_id ) ); ?>" target="_blank">
                                        <?php echo esc_html( $link->source_title ); ?>
                                    </a>
                                    <br>
                                    <small>
                                        <a href="<?php echo esc_url( get_edit_post_link( $link->source_post_id ) ); ?>">
                                            <?php esc_html_e( 'Edit', 'auto-interlinker' ); ?>
                                        </a>
                                    </small>
                                <?php else : ?>
                                    <em><?php echo esc_html( '#' . $link->source_post_id ); ?></em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html( $link->keyword ); ?></code></td>
                            <td>
                                <?php if ( $link->target_title ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( $link->target_post_id ) ); ?>" target="_blank">
                                        <?php echo esc_html( $link->target_title ); ?>
                                    </a>
                                    <br>
                                    <small>
                                        <a href="<?php echo esc_url( get_edit_post_link( $link->target_post_id ) ); ?>">
                                            <?php esc_html_e( 'Edit', 'auto-interlinker' ); ?>
                                        </a>
                                    </small>
                                <?php else : ?>
                                    <em><?php echo esc_html( '#' . $link->target_post_id ); ?></em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $link->created_at ) ) ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
