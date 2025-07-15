<?php
/**
 * Provide a admin area view for the plugin settings
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

<div class="wrap otc-settings-page">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'otc_save_settings', 'otc_settings_nonce' ); ?>
		
		<div class="card">
			<h2><?php esc_html_e( 'Master Controls', 'online-texas-core' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="plugin_enabled"><?php esc_html_e( 'Plugin Functionality', 'online-texas-core' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="plugin_enabled" name="plugin_enabled" value="1" <?php checked( !empty( $options['plugin_enabled'] ) ); ?>>
							<?php esc_html_e( 'Enable Plugin Functionality', 'online-texas-core' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Master switch to enable/disable all plugin features.', 'online-texas-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="auto_create_for_new_vendors"><?php esc_html_e( 'Auto-Create Products', 'online-texas-core' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="auto_create_for_new_vendors" name="auto_create_for_new_vendors" value="1" <?php checked( !empty( $options['auto_create_for_new_vendors'] ) ); ?>>
							<?php esc_html_e( 'Auto-create products for new vendors', 'online-texas-core' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically create vendor products when new vendors are approved.', 'online-texas-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vendor_product_status"><?php esc_html_e( 'Default Product Status', 'online-texas-core' ); ?></label>
					</th>
					<td>
						<select id="vendor_product_status" name="vendor_product_status">
							<option value="draft" <?php selected( $options['vendor_product_status'] ?? 'draft', 'draft' ); ?>>
								<?php esc_html_e( 'Draft', 'online-texas-core' ); ?>
							</option>
							<option value="pending" <?php selected( $options['vendor_product_status'] ?? 'draft', 'pending' ); ?>>
								<?php esc_html_e( 'Pending Review', 'online-texas-core' ); ?>
							</option>
							<option value="publish" <?php selected( $options['vendor_product_status'] ?? 'draft', 'publish' ); ?>>
								<?php esc_html_e( 'Published', 'online-texas-core' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Default status for newly created vendor products.', 'online-texas-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="debug_mode"><?php esc_html_e( 'Debug Mode', 'online-texas-core' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="debug_mode" name="debug_mode" value="1" <?php checked( !empty( $options['debug_mode'] ) ); ?>>
							<?php esc_html_e( 'Enable debug logging', 'online-texas-core' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Log plugin activity for troubleshooting. Disable in production for better performance.', 'online-texas-core' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( esc_html__( 'Save Settings', 'online-texas-core' ) ); ?>
	</form>

	<?php
	// Show debug log if enabled
	if ( ! empty( $options['debug_mode'] ) ) :
		$debug_log = get_option( 'otc_debug_log', array() );
		if ( ! empty( $debug_log ) ) :
	?>
	<div class="card">
		<h2><?php esc_html_e( 'Recent Debug Log', 'online-texas-core' ); ?></h2>
		<div class="otc-debug-log">
			<?php foreach ( array_reverse( array_slice( $debug_log, -10 ) ) as $entry ) : ?>
			<div class="otc-log-entry otc-log-<?php echo esc_attr( $entry['type'] ); ?>">
				<span class="otc-log-time"><?php echo esc_html( $entry['timestamp'] ); ?></span>
				<span class="otc-log-message"><?php echo esc_html( $entry['message'] ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
		<p>
			<button type="button" class="button" id="otc-clear-debug-log">
				<?php esc_html_e( 'Clear Debug Log', 'online-texas-core' ); ?>
			</button>
		</p>
	</div>
	<?php 
		endif;
	endif; 
	?>
</div>

<script>
jQuery(document).ready(function($) {
	$('#otc-clear-debug-log').on('click', function() {
		if (confirm('<?php echo esc_js( __( 'Are you sure you want to clear the debug log?', 'online-texas-core' ) ); ?>')) {
			var button = $(this);
			button.prop('disabled', true);
			
			$.post(ajaxurl, {
				action: 'otc_clear_debug_log',
				nonce: '<?php echo wp_create_nonce( 'otc_nonce' ); ?>'
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