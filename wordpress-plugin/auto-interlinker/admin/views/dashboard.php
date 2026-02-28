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

	<!-- How It Works -->
	<div class="ai-card ai-info-card">
		<h2><?php esc_html_e( 'How It Works', 'auto-interlinker' ); ?></h2>
		<ol class="ai-steps">
			<li><?php esc_html_e( 'The plugin scans all your published posts and extracts important keywords from each post\'s title and content.', 'auto-interlinker' ); ?></li>
			<li><?php esc_html_e( 'It then searches other posts for those keywords and automatically inserts clickable internal links.', 'auto-interlinker' ); ?></li>
			<li><?php esc_html_e( 'Links are permanently written into post content — they appear in your editor and are visible to search engines.', 'auto-interlinker' ); ?></li>
			<li><?php esc_html_e( 'New posts are processed automatically when published. Click "Reprocess All Posts" to apply links to existing posts.', 'auto-interlinker' ); ?></li>
		</ol>
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
		<p><?php esc_html_e( 'Use "Reprocess All Posts" to scan all published posts, extract keywords, and permanently insert interlinks. Run this after installing the plugin or changing settings.', 'auto-interlinker' ); ?></p>

		<div class="ai-action-row">
			<button id="ai-reprocess-all" class="button button-primary button-large">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Reprocess All Posts', 'auto-interlinker' ); ?>
			</button>
			<span id="ai-reprocess-status" class="ai-action-status"></span>
		</div>

		<hr class="ai-divider">

		<p><?php esc_html_e( 'Use "Remove All Interlinks" to strip all auto-inserted links from post content (useful if you want to start fresh or disable the plugin cleanly).', 'auto-interlinker' ); ?></p>
		<div class="ai-action-row">
			<button id="ai-strip-all-links" class="button button-secondary button-large ai-btn-danger">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Remove All Interlinks', 'auto-interlinker' ); ?>
			</button>
			<span id="ai-strip-status" class="ai-action-status"></span>
		</div>
	</div>

	<!-- Process Single Post -->
	<div class="ai-card">
		<h2><?php esc_html_e( 'Process Single Post', 'auto-interlinker' ); ?></h2>
		<p><?php esc_html_e( 'Enter a post ID to extract keywords and apply interlinks to that specific post.', 'auto-interlinker' ); ?></p>
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
			<p class="ai-empty">
				<?php esc_html_e( 'No interlinks logged yet. Click "Reprocess All Posts" above to scan your posts and insert links automatically.', 'auto-interlinker' ); ?>
			</p>
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
