<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://wbcomdesigns.com/
 * @since      1.0.0
 *
 * @package    Online_Texas_Core
 * @subpackage Online_Texas_Core/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="otc-dashboard">
		<!-- Statistics Overview -->
		<div class="card">
			<h2><?php esc_html_e( 'Overview', 'online-texas-core' ); ?></h2>
			<div class="otc-stats-grid">
				<div class="otc-stat-item">
					<div class="otc-stat-number"><?php echo esc_html( $stats['admin_products'] ); ?></div>
					<div class="otc-stat-label"><?php esc_html_e( 'Admin Products with Courses', 'online-texas-core' ); ?></div>
				</div>
				<div class="otc-stat-item">
					<div class="otc-stat-number"><?php echo esc_html( $stats['vendor_products'] ); ?></div>
					<div class="otc-stat-label"><?php esc_html_e( 'Vendor Products Created', 'online-texas-core' ); ?></div>
				</div>
				<div class="otc-stat-item">
					<div class="otc-stat-number"><?php echo esc_html( $stats['active_vendors'] ); ?></div>
					<div class="otc-stat-label"><?php esc_html_e( 'Active Vendors', 'online-texas-core' ); ?></div>
				</div>
				<div class="otc-stat-item">
					<div class="otc-stat-number"><?php echo esc_html( $stats['learndash_groups'] ); ?></div>
					<div class="otc-stat-label"><?php esc_html_e( 'LearnDash Groups', 'online-texas-core' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Quick Actions -->
		<div class="card">
			<h2><?php esc_html_e( 'Quick Actions', 'online-texas-core' ); ?></h2>
			<div class="otc-actions-grid">
				<div class="otc-action-item">
					<button type="button" class="button button-primary button-large" id="otc-sync-all-vendors">
						<?php esc_html_e( 'Sync All Vendors', 'online-texas-core' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Create missing vendor products for all active vendors.', 'online-texas-core' ); ?>
					</p>
				</div>
				<div class="otc-action-item">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=online-texas-core-settings' ) ); ?>" class="button button-secondary button-large">
						<?php esc_html_e( 'Plugin Settings', 'online-texas-core' ); ?>
					</a>
					<p class="description">
						<?php esc_html_e( 'Configure plugin behavior and options.', 'online-texas-core' ); ?>
					</p>
				</div>
				<div class="otc-action-item">
					<button type="button" class="button button-secondary button-large" id="otc-clear-debug-log">
						<?php esc_html_e( 'Clear Debug Log', 'online-texas-core' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Clear all debug log entries.', 'online-texas-core' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Manual Vendor Sync -->
		<div class="card">
			<h2><?php esc_html_e( 'Manual Vendor Sync', 'online-texas-core' ); ?></h2>
			
			<?php
			if ( function_exists( 'dokan_get_sellers' ) ) {
				$vendors = dokan_get_sellers( array( 'status' => 'approved' ) );
				
				if ( ! empty( $vendors['users'] ) ) :
			?>
			<div class="otc-vendor-sync-section">
				<p><?php esc_html_e( 'Select specific vendors to sync with admin products:', 'online-texas-core' ); ?></p>
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Vendor', 'online-texas-core' ); ?></th>
							<th><?php esc_html_e( 'Status', 'online-texas-core' ); ?></th>
							<th><?php esc_html_e( 'Products', 'online-texas-core' ); ?></th>
							<th><?php esc_html_e( 'Action', 'online-texas-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $vendors['users'] as $vendor ) :
							$is_enabled = function_exists( 'dokan_is_seller_enabled' ) ? dokan_is_seller_enabled( $vendor->ID ) : true;
							
							// Count vendor products
							global $wpdb;
							$product_count = $wpdb->get_var( $wpdb->prepare(
								"SELECT COUNT(*) FROM {$wpdb->posts} p 
								INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
								WHERE p.post_type = 'product' 
								AND p.post_author = %d 
								AND pm.meta_key = '_parent_product_id'",
								$vendor->ID
							) );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $vendor->display_name ); ?></strong><br>
								<small><?php echo esc_html( $vendor->user_email ); ?></small>
							</td>
							<td>
								<span class="otc-status <?php echo $is_enabled ? 'otc-status-active' : 'otc-status-inactive'; ?>">
									<?php echo $is_enabled ? esc_html__( 'Active', 'online-texas-core' ) : esc_html__( 'Inactive', 'online-texas-core' ); ?>
								</span>
							</td>
							<td><?php echo esc_html( intval( $product_count ) ); ?></td>
							<td>
								<button type="button" class="button button-small otc-sync-vendor" 
								        data-vendor-id="<?php echo esc_attr( $vendor->ID ); ?>">
									<?php esc_html_e( 'Sync This Vendor', 'online-texas-core' ); ?>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php 
				else :
			?>
			<p><?php esc_html_e( 'No active vendors found.', 'online-texas-core' ); ?></p>
			<?php 
				endif;
			} else {
			?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Dokan plugin is not active. Vendor sync functionality is not available.', 'online-texas-core' ); ?></p>
			</div>
			<?php } ?>
		</div>

		<!-- Debug Log (if enabled) -->
		<?php
		$options = get_option( 'otc_options', array() );
		if ( ! empty( $options['debug_mode'] ) ) :
			$debug_log = get_option( 'otc_debug_log', array() );
			if ( ! empty( $debug_log ) ) :
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Recent Debug Log', 'online-texas-core' ); ?></h2>
			<div class="otc-debug-log">
				<?php foreach ( array_reverse( array_slice( $debug_log, -20 ) ) as $entry ) : ?>
				<div class="otc-log-entry otc-log-<?php echo esc_attr( $entry['type'] ); ?>">
					<span class="otc-log-time"><?php echo esc_html( $entry['timestamp'] ); ?></span>
					<span class="otc-log-message"><?php echo esc_html( $entry['message'] ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php 
			endif;
		endif; 
		?>

		<!-- System Status -->
		<div class="card">
			<h2><?php esc_html_e( 'System Status', 'online-texas-core' ); ?></h2>
			<table class="widefat">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'WordPress Version', 'online-texas-core' ); ?></strong></td>
						<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WooCommerce', 'online-texas-core' ); ?></strong></td>
						<td>
							<?php if ( defined( 'WC_VERSION' ) ) : ?>
								<span class="otc-status otc-status-active"><?php echo esc_html( WC_VERSION ); ?></span>
							<?php else : ?>
								<span class="otc-status otc-status-inactive"><?php esc_html_e( 'Not Active', 'online-texas-core' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Dokan', 'online-texas-core' ); ?></strong></td>
						<td>
							<?php if ( defined( 'DOKAN_PLUGIN_VERSION' ) ) : ?>
								<span class="otc-status otc-status-active"><?php echo esc_html( DOKAN_PLUGIN_VERSION ); ?></span>
							<?php else : ?>
								<span class="otc-status otc-status-inactive"><?php esc_html_e( 'Not Active', 'online-texas-core' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'LearnDash', 'online-texas-core' ); ?></strong></td>
						<td>
							<?php if ( defined( 'LEARNDASH_VERSION' ) ) : ?>
								<span class="otc-status otc-status-active"><?php echo esc_html( LEARNDASH_VERSION ); ?></span>
							<?php else : ?>
								<span class="otc-status otc-status-inactive"><?php esc_html_e( 'Not Active', 'online-texas-core' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Loading overlay -->
	<div id="otc-loading-overlay" style="display: none;">
		<div class="otc-loading-spinner"></div>
		<div class="otc-loading-text"><?php esc_html_e( 'Processing...', 'online-texas-core' ); ?></div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Sync all vendors
	$('#otc-sync-all-vendors').on('click', function() {
		if (confirm('<?php echo esc_js( __( 'This will create missing products for all vendors. Continue?', 'online-texas-core' ) ); ?>')) {
			var button = $(this);
			var originalText = button.text();
			
			button.prop('disabled', true).text('<?php echo esc_js( __( 'Syncing...', 'online-texas-core' ) ); ?>');
			$('#otc-loading-overlay').show();
			
			$.post(otc_ajax.ajax_url, {
				action: 'otc_manual_vendor_sync',
				vendor_id: 'all',
				nonce: otc_ajax.nonce
			}, function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert('<?php echo esc_js( __( 'Error:', 'online-texas-core' ) ); ?> ' + response.data.message);
				}
			}).always(function() {
				button.prop('disabled', false).text(originalText);
				$('#otc-loading-overlay').hide();
			});
		}
	});
	
	// Sync individual vendor
	$('.otc-sync-vendor').on('click', function() {
		var button = $(this);
		var vendorId = button.data('vendor-id');
		var originalText = button.text();
		
		button.prop('disabled', true).text('<?php echo esc_js( __( 'Syncing...', 'online-texas-core' ) ); ?>');
		
		$.post(otc_ajax.ajax_url, {
			action: 'otc_manual_vendor_sync',
			vendor_id: vendorId,
			nonce: otc_ajax.nonce
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
			} else {
				alert('<?php echo esc_js( __( 'Error:', 'online-texas-core' ) ); ?> ' + response.data.message);
			}
		}).always(function() {
			button.prop('disabled', false).text(originalText);
		});
	});
	
	// Clear debug log
	$('#otc-clear-debug-log').on('click', function() {
		if (confirm('<?php echo esc_js( __( 'Are you sure you want to clear the debug log?', 'online-texas-core' ) ); ?>')) {
			var button = $(this);
			
			button.prop('disabled', true);
			
			$.post(otc_ajax.ajax_url, {
				action: 'otc_clear_debug_log',
				nonce: otc_ajax.nonce
			}, function(response) {
				if (response.success) {
					$('.otc-debug-log').html('<p><?php echo esc_js( __( 'Debug log cleared.', 'online-texas-core' ) ); ?></p>');
				}
			}).always(function() {
				button.prop('disabled', false);
			});
		}
	});
});
</script>