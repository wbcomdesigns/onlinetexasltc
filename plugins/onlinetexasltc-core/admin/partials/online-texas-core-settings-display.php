<?php
/**
 * Provide a settings page view for the plugin
 *
 * This file is used to markup the settings page of the plugin.
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

// Set default values
$defaults = array(
	'auto_create_for_new_vendors' => true,
	'debug_mode'                  => false,
	'vendor_product_status'       => 'draft'
);

$options = wp_parse_args( $options, $defaults );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'otc_save_settings', 'otc_settings_nonce' ); ?>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="auto_create_for_new_vendors">
							<?php esc_html_e( 'Auto-create Products for New Vendors', 'online-texas-core' ); ?>
						</label>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Auto-create Products for New Vendors', 'online-texas-core' ); ?></span>
							</legend>
							<label for="auto_create_for_new_vendors">
								<input name="auto_create_for_new_vendors" type="checkbox" id="auto_create_for_new_vendors" value="1" <?php checked( $options['auto_create_for_new_vendors'] ); ?> />
								<?php esc_html_e( 'Automatically create vendor products when someone becomes a vendor', 'online-texas-core' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="vendor_product_status">
							<?php esc_html_e( 'New Vendor Product Status', 'online-texas-core' ); ?>
						</label>
					</th>
					<td>
						<select name="vendor_product_status" id="vendor_product_status">
							<option value="draft" <?php selected( $options['vendor_product_status'], 'draft' ); ?>>
								<?php esc_html_e( 'Draft', 'online-texas-core' ); ?>
							</option>
							<option value="pending" <?php selected( $options['vendor_product_status'], 'pending' ); ?>>
								<?php esc_html_e( 'Pending Review', 'online-texas-core' ); ?>
							</option>
							<option value="publish" <?php selected( $options['vendor_product_status'], 'publish' ); ?>>
								<?php esc_html_e( 'Published', 'online-texas-core' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Default status for newly created vendor products. Draft is recommended to let vendors review before publishing.', 'online-texas-core' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="debug_mode">
							<?php esc_html_e( 'Debug Mode', 'online-texas-core' ); ?>
						</label>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Debug Mode', 'online-texas-core' ); ?></span>
							</legend>
							<label for="debug_mode">
								<input name="debug_mode" type="checkbox" id="debug_mode" value="1" <?php checked( $options['debug_mode'] ); ?> />
								<?php esc_html_e( 'Enable detailed logging for troubleshooting', 'online-texas-core' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Enable this only when troubleshooting issues. Debug logs can be viewed in the main plugin page.', 'online-texas-core' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>

	<hr>

	<!-- Quick Actions -->
	<div class="card">
		<h2><?php esc_html_e( 'Quick Actions', 'online-texas-core' ); ?></h2>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Vendor Management', 'online-texas-core' ); ?></th>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=online-texas-core' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Manage Vendor Sync', 'online-texas-core' ); ?>
						</a>
						<p class="description">
							<?php esc_html_e( 'View statistics and manually sync vendor products.', 'online-texas-core' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Debug Log', 'online-texas-core' ); ?></th>
					<td>
						<button type="button" class="button button-secondary" id="otc-clear-debug-log">
							<?php esc_html_e( 'Clear Debug Log', 'online-texas-core' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Clear all debug log entries.', 'online-texas-core' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Current Settings Summary -->
	<div class="card">
		<h2><?php esc_html_e( 'Current Configuration', 'online-texas-core' ); ?></h2>
		
		<table class="widefat">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Auto-create for New Vendors', 'online-texas-core' ); ?></strong></td>
					<td>
						<span class="otc-status <?php echo $options['auto_create_for_new_vendors'] ? 'otc-status-active' : 'otc-status-inactive'; ?>">
							<?php echo $options['auto_create_for_new_vendors'] ? esc_html__( 'Enabled', 'online-texas-core' ) : esc_html__( 'Disabled', 'online-texas-core' ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'New Product Status', 'online-texas-core' ); ?></strong></td>
					<td><?php echo esc_html( ucfirst( $options['vendor_product_status'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Debug Mode', 'online-texas-core' ); ?></strong></td>
					<td>
						<span class="otc-status <?php echo $options['debug_mode'] ? 'otc-status-active' : 'otc-status-inactive'; ?>">
							<?php echo $options['debug_mode'] ? esc_html__( 'Enabled', 'online-texas-core' ) : esc_html__( 'Disabled', 'online-texas-core' ); ?>
						</span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Plugin Information -->
	<div class="card">
		<h2><?php esc_html_e( 'How It Works', 'online-texas-core' ); ?></h2>
		
		<ol>
			<li>
				<strong><?php esc_html_e( 'Admin Creates Product:', 'online-texas-core' ); ?></strong>
				<?php esc_html_e( 'When an admin creates a WooCommerce product and links it to LearnDash courses, the plugin detects this.', 'online-texas-core' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Vendor Products Created:', 'online-texas-core' ); ?></strong>
				<?php esc_html_e( 'The plugin automatically creates duplicate products for all active vendors with the format "Store Name - Product Name".', 'online-texas-core' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'LearnDash Groups:', 'online-texas-core' ); ?></strong>
				<?php esc_html_e( 'Each vendor product gets its own LearnDash group linked to the same courses, with the vendor as group leader.', 'online-texas-core' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Vendor Customization:', 'online-texas-core' ); ?></strong>
				<?php esc_html_e( 'Vendors can set their own pricing and publish when ready. Pricing remains independent.', 'online-texas-core' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Automatic Updates:', 'online-texas-core' ); ?></strong>
				<?php esc_html_e( 'When admin updates the base product, vendor products sync automatically (description only for published products).', 'online-texas-core' ); ?>
			</li>
		</ol>
	</div>

	<!-- Requirements -->
	<div class="card">
		<h2><?php esc_html_e( 'Requirements', 'online-texas-core' ); ?></h2>
		
		<p><?php esc_html_e( 'This plugin requires the following plugins to be installed and active:', 'online-texas-core' ); ?></p>
		
		<ul>
			<li>
				<strong><?php esc_html_e( 'WooCommerce', 'online-texas-core' ); ?>:</strong>
				<?php if ( defined( 'WC_VERSION' ) ) : ?>
					<span class="otc-status otc-status-active"><?php printf( esc_html__( 'Active (v%s)', 'online-texas-core' ), esc_html( WC_VERSION ) ); ?></span>
				<?php else : ?>
					<span class="otc-status otc-status-inactive"><?php esc_html_e( 'Not Active', 'online-texas-core' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Dokan', 'online-texas-core' ); ?>:</strong>
				<?php if ( defined( 'DOKAN_PLUGIN_VERSION' ) ) : ?>
					<span class="otc-status otc-status-active"><?php printf( esc_html__( 'Active (v%s)', 'online-texas-core' ), esc_html( DOKAN_PLUGIN_VERSION ) ); ?></span>
				<?php else : ?>
					<span class="otc-status otc-status-inactive"><?php esc_html_e( 'Not Active', 'online-texas-core' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'LearnDash', 'online-texas-core' ); ?>:</strong>
				<?php if ( defined( 'LEARNDASH_VERSION' ) ) : ?>
					<span class="otc-status otc-status-active"><?php printf( esc_html__( 'Active (v%s)', 'online-texas-core' ), esc_html( LEARNDASH_VERSION ) ); ?></span>
				<?php else : ?>
					<span class="otc-status otc-status-inactive"><?php esc_html_e( 'Not Active', 'online-texas-core' ); ?></span>
				<?php endif; ?>
			</li>
		</ul>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Clear debug log
	$('#otc-clear-debug-log').on('click', function() {
		if (confirm('<?php echo esc_js( __( 'Are you sure you want to clear the debug log?', 'online-texas-core' ) ); ?>')) {
			var button = $(this);
			var originalText = button.text();
			
			button.prop('disabled', true).text('<?php echo esc_js( __( 'Clearing...', 'online-texas-core' ) ); ?>');
			
			$.post(ajaxurl, {
				action: 'otc_clear_debug_log',
				nonce: '<?php echo esc_js( wp_create_nonce( 'otc_nonce' ) ); ?>'
			}, function(response) {
				if (response.success) {
					alert('<?php echo esc_js( __( 'Debug log cleared successfully.', 'online-texas-core' ) ); ?>');
				} else {
					alert('<?php echo esc_js( __( 'Failed to clear debug log.', 'online-texas-core' ) ); ?>');
				}
			}).always(function() {
				button.prop('disabled', false).text(originalText);
			});
		}
	});
});
</script>