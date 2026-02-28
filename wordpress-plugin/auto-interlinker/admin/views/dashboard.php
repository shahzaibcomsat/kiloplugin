<?php
/**
 * Dashboard view for Auto Interlinker.
 *
 * @package AutoInterlinker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_enabled = isset( $options['enabled'] ) ? (bool) $options['enabled'] : true;
?>
<div class="wrap ai-wrap">
    <h1 class="ai-page-title">
        <span class="dashicons dashicons-admin-links"></span>
        <?php esc_html_e( 'Auto Interlinker — Dashboard', 'auto-interlinker' ); ?>
    </h1>

    <div class="ai-status-bar <?php echo $is_enabled ? 'ai-status-active' : 'ai-status-inactive'; ?>">
        <span class="dashicons <?php echo $is_enabled ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
        <?php
        if ( $is_enabled ) {
            esc_html_e( 'Auto Interlinking is ACTIVE', 'auto-interlinker' );
        } else {
            esc_html_e( 'Auto Interlinking is DISABLED', 'auto-interlinker' );
        }
        ?>
        &nbsp;—&nbsp;
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=auto-interlinker-settings' ) ); ?>">
            <?php esc_html_e( 'Change in Settings', 'auto-interlinker' ); ?>
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="ai-stats-grid">
        <div class="ai-stat-card">
            <div class="ai-stat-number"><?php echo esc_html( number_format_i18n( (int) $post_count ) ); ?></div>
            <div class="ai-stat-label"><?php esc_html_e( 'Posts Indexed', 'auto-interlinker' ); ?></div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-number"><?php echo esc_html( number_format_i18n( (int) $keyword_count ) ); ?></div>
            <div class="ai-stat-label"><?php esc_html_e( 'Keywords Indexed', 'auto-interlinker' ); ?></div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-number"><?php echo esc_html( number_format_i18n( (int) $total_links ) ); ?></div>
            <div class="ai-stat-label"><?php esc_html_e( 'Links Inserted', 'auto-interlinker' ); ?></div>
        </div>
    </div>

    <!-- Actions -->
    <div class="ai-card">
        <h2><?php esc_html_e( 'Quick Actions', 'auto-interlinker' ); ?></h2>
        <p><?php esc_html_e( 'Use the button below to scan all published posts and rebuild the keyword index. This is useful after changing settings or installing the plugin for the first time.', 'auto-interlinker' ); ?></p>
        <button id="ai-reprocess-all" class="button button-primary button-large">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e( 'Reprocess All Posts', 'auto-interlinker' ); ?>
        </button>
        <span id="ai-reprocess-status" class="ai-action-status"></span>
    </div>

    <!-- Process Single Post -->
    <div class="ai-card">
        <h2><?php esc_html_e( 'Process Single Post', 'auto-interlinker' ); ?></h2>
        <p><?php esc_html_e( 'Enter a post ID to extract and index keywords for that specific post.', 'auto-interlinker' ); ?></p>
        <div class="ai-inline-form">
            <input type="number" id="ai-single-post-id" class="regular-text" placeholder="<?php esc_attr_e( 'Post ID', 'auto-interlinker' ); ?>" min="1" />
            <button id="ai-process-single" class="button button-secondary">
                <?php esc_html_e( 'Process Post', 'auto-interlinker' ); ?>
            </button>
            <span id="ai-single-status" class="ai-action-status"></span>
        </div>
    </div>

    <!-- Recent Links -->
    <div class="ai-card">
        <h2><?php esc_html_e( 'Recent Interlinks', 'auto-interlinker' ); ?></h2>
        <?php if ( empty( $link_stats ) ) : ?>
            <p class="ai-empty"><?php esc_html_e( 'No interlinks logged yet. Links are recorded when visitors view posts.', 'auto-interlinker' ); ?></p>
        <?php else : ?>
            <table class="widefat striped ai-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Source Post', 'auto-interlinker' ); ?></th>
                        <th><?php esc_html_e( 'Keyword', 'auto-interlinker' ); ?></th>
                        <th><?php esc_html_e( 'Target Post', 'auto-interlinker' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'auto-interlinker' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $link_stats as $link ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $link->source_post_id ) ); ?>">
                                    <?php echo esc_html( $link->source_title ?: '#' . $link->source_post_id ); ?>
                                </a>
                            </td>
                            <td><code><?php echo esc_html( $link->keyword ); ?></code></td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $link->target_post_id ) ); ?>">
                                    <?php echo esc_html( $link->target_title ?: '#' . $link->target_post_id ); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $link->created_at ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=auto-interlinker-logs' ) ); ?>" class="button">
                    <?php esc_html_e( 'View All Logs', 'auto-interlinker' ); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>
