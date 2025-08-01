<?php
/**
 * Admin Domain Mappings Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Domain Mapping Management', 'dokan-vendor-domain-mapper'); ?></h1>

    <?php
    // Get statistics
    $stats = $this->get_domain_statistics();
    ?>

    <!-- Statistics -->
    <div class="domain-mapper-stats">
        <div class="stat-box">
            <h3><?php _e('Total Domains', 'dokan-vendor-domain-mapper'); ?></h3>
            <span class="stat-number"><?php echo $stats['total']; ?></span>
        </div>
        <div class="stat-box">
            <h3><?php _e('Pending', 'dokan-vendor-domain-mapper'); ?></h3>
            <span class="stat-number pending"><?php echo $stats['pending']; ?></span>
        </div>
        <div class="stat-box">
            <h3><?php _e('Verified', 'dokan-vendor-domain-mapper'); ?></h3>
            <span class="stat-number verified"><?php echo $stats['verified']; ?></span>
        </div>
        <div class="stat-box">
            <h3><?php _e('Approved', 'dokan-vendor-domain-mapper'); ?></h3>
            <span class="stat-number approved"><?php echo $stats['approved']; ?></span>
        </div>
        <div class="stat-box">
            <h3><?php _e('Live', 'dokan-vendor-domain-mapper'); ?></h3>
            <span class="stat-number live"><?php echo $stats['live']; ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="domain-mapper-filters">
        <form method="get">
            <input type="hidden" name="page" value="dokan-domain-mapping">
            
            <select name="status">
                <option value=""><?php _e('All Statuses', 'dokan-vendor-domain-mapper'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'dokan-vendor-domain-mapper'); ?></option>
                <option value="verified" <?php selected($status_filter, 'verified'); ?>><?php _e('Verified', 'dokan-vendor-domain-mapper'); ?></option>
                <option value="approved" <?php selected($status_filter, 'approved'); ?>><?php _e('Approved', 'dokan-vendor-domain-mapper'); ?></option>
                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'dokan-vendor-domain-mapper'); ?></option>
                <option value="live" <?php selected($status_filter, 'live'); ?>><?php _e('Live', 'dokan-vendor-domain-mapper'); ?></option>
            </select>

            <input type="text" name="vendor_id" placeholder="<?php _e('Vendor ID', 'dokan-vendor-domain-mapper'); ?>" value="<?php echo esc_attr($vendor_filter); ?>">

            <input type="submit" class="button" value="<?php _e('Filter', 'dokan-vendor-domain-mapper'); ?>">
            <a href="<?php echo admin_url('admin.php?page=dokan-domain-mapping'); ?>" class="button"><?php _e('Clear', 'dokan-vendor-domain-mapper'); ?></a>
        </form>
    </div>

    <!-- Domain Mappings Table -->
    <form method="post" id="domain-mappings-form">
        <?php wp_nonce_field('dokan_domain_mapper_bulk_action', 'dokan_domain_mapper_nonce'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value="-1"><?php _e('Bulk Actions', 'dokan-vendor-domain-mapper'); ?></option>
                    <option value="approve"><?php _e('Approve', 'dokan-vendor-domain-mapper'); ?></option>
                    <option value="reject"><?php _e('Reject', 'dokan-vendor-domain-mapper'); ?></option>
                    <option value="delete"><?php _e('Delete', 'dokan-vendor-domain-mapper'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php _e('Apply', 'dokan-vendor-domain-mapper'); ?>">
            </div>

            <div class="alignright">
                <a href="<?php echo admin_url('admin.php?page=dokan-domain-mapping&export=csv'); ?>" class="button"><?php _e('Export CSV', 'dokan-vendor-domain-mapper'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=dokan-domain-mapping&export=json'); ?>" class="button"><?php _e('Export JSON', 'dokan-vendor-domain-mapper'); ?></a>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th scope="col" class="manage-column column-domain"><?php _e('Domain', 'dokan-vendor-domain-mapper'); ?></th>
                    <th scope="col" class="manage-column column-vendor"><?php _e('Vendor', 'dokan-vendor-domain-mapper'); ?></th>
                    <th scope="col" class="manage-column column-status"><?php _e('Status', 'dokan-vendor-domain-mapper'); ?></th>
                    <th scope="col" class="manage-column column-ssl"><?php _e('SSL', 'dokan-vendor-domain-mapper'); ?></th>
                    <th scope="col" class="manage-column column-created"><?php _e('Created', 'dokan-vendor-domain-mapper'); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'dokan-vendor-domain-mapper'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($domain_mappings)): ?>
                    <tr>
                        <td colspan="7"><?php _e('No domain mappings found.', 'dokan-vendor-domain-mapper'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($domain_mappings as $mapping): ?>
                        <?php $details = $this->get_domain_details($mapping); ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="domain_ids[]" value="<?php echo $mapping->id; ?>">
                            </th>
                            <td class="column-domain">
                                <strong><?php echo esc_html($mapping->domain); ?></strong>
                                <?php if ($mapping->status === 'live'): ?>
                                    <br><a href="https://<?php echo esc_attr($mapping->domain); ?>" target="_blank" class="domain-link"><?php _e('Visit Site', 'dokan-vendor-domain-mapper'); ?></a>
                                <?php endif; ?>
                            </td>
                            <td class="column-vendor">
                                <strong><?php echo esc_html($details['vendor_name']); ?></strong>
                                <br><small><?php echo esc_html($details['vendor_email']); ?></small>
                                <br><a href="<?php echo esc_url($details['store_url']); ?>" target="_blank"><?php _e('View Store', 'dokan-vendor-domain-mapper'); ?></a>
                            </td>
                            <td class="column-status">
                                <?php echo $this->get_status_badge($mapping->status); ?>
                            </td>
                            <td class="column-ssl">
                                <?php if ($mapping->ssl_status !== 'none'): ?>
                                    <span class="ssl-status <?php echo esc_attr($mapping->ssl_status); ?>">
                                        <?php echo esc_html(ucfirst($mapping->ssl_status)); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="ssl-status none"><?php _e('None', 'dokan-vendor-domain-mapper'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-created">
                                <?php echo date_i18n(get_option('date_format'), strtotime($mapping->created_at)); ?>
                                <br><small><?php echo date_i18n(get_option('time_format'), strtotime($mapping->created_at)); ?></small>
                            </td>
                            <td class="column-actions">
                                <?php if ($mapping->status === 'verified'): ?>
                                    <button type="button" class="button button-primary approve-domain" data-domain-id="<?php echo $mapping->id; ?>">
                                        <?php _e('Approve', 'dokan-vendor-domain-mapper'); ?>
                                    </button>
                                    <button type="button" class="button reject-domain" data-domain-id="<?php echo $mapping->id; ?>">
                                        <?php _e('Reject', 'dokan-vendor-domain-mapper'); ?>
                                    </button>
                                <?php elseif ($mapping->status === 'approved'): ?>
                                    <button type="button" class="button button-primary generate-config" data-domain-id="<?php echo $mapping->id; ?>">
                                        <?php _e('Generate Config', 'dokan-vendor-domain-mapper'); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="button view-details" data-domain-id="<?php echo $mapping->id; ?>">
                                    <?php _e('Details', 'dokan-vendor-domain-mapper'); ?>
                                </button>
                                
                                <button type="button" class="button delete-domain" data-domain-id="<?php echo $mapping->id; ?>">
                                    <?php _e('Delete', 'dokan-vendor-domain-mapper'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="action2">
                    <option value="-1"><?php _e('Bulk Actions', 'dokan-vendor-domain-mapper'); ?></option>
                    <option value="approve"><?php _e('Approve', 'dokan-vendor-domain-mapper'); ?></option>
                    <option value="reject"><?php _e('Reject', 'dokan-vendor-domain-mapper'); ?></option>
                    <option value="delete"><?php _e('Delete', 'dokan-vendor-domain-mapper'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php _e('Apply', 'dokan-vendor-domain-mapper'); ?>">
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(_n('%s item', '%s items', $total_mappings, 'dokan-vendor-domain-mapper'), number_format_i18n($total_mappings)); ?>
                    </span>
                    
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $args['page']
                    ));
                    
                    if ($page_links) {
                        echo $page_links;
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Reject Domain Modal -->
<div id="reject-domain-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3><?php _e('Reject Domain', 'dokan-vendor-domain-mapper'); ?></h3>
        <p><?php _e('Please provide a reason for rejecting this domain:', 'dokan-vendor-domain-mapper'); ?></p>
        <textarea id="reject-reason" rows="4" cols="50" placeholder="<?php _e('Enter rejection reason...', 'dokan-vendor-domain-mapper'); ?>"></textarea>
        <div class="modal-actions">
            <button type="button" class="button button-primary confirm-reject"><?php _e('Reject', 'dokan-vendor-domain-mapper'); ?></button>
            <button type="button" class="button cancel-reject"><?php _e('Cancel', 'dokan-vendor-domain-mapper'); ?></button>
        </div>
    </div>
</div>

<!-- Domain Details Modal -->
<div id="domain-details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3><?php _e('Domain Details', 'dokan-vendor-domain-mapper'); ?></h3>
        <div id="domain-details-content"></div>
        <div class="modal-actions">
            <button type="button" class="button close-modal"><?php _e('Close', 'dokan-vendor-domain-mapper'); ?></button>
        </div>
    </div>
</div>

<!-- Proxy Config Modal -->
<div id="proxy-config-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3><?php _e('Proxy Configuration', 'dokan-vendor-domain-mapper'); ?></h3>
        <div id="proxy-config-content"></div>
        <div class="modal-actions">
            <button type="button" class="button close-modal"><?php _e('Close', 'dokan-vendor-domain-mapper'); ?></button>
        </div>
    </div>
</div>

<style>
.domain-mapper-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.stat-box {
    background: #fff;
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 5px;
    text-align: center;
    min-width: 120px;
}

.stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.stat-number.pending { color: #f39c12; }
.stat-number.verified { color: #3498db; }
.stat-number.approved { color: #27ae60; }
.stat-number.live { color: #2ecc71; }

.domain-mapper-filters {
    background: #fff;
    padding: 15px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
    border-radius: 5px;
}

.domain-mapper-filters select,
.domain-mapper-filters input[type="text"] {
    margin-right: 10px;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 20px;
    border-radius: 5px;
    width: 80%;
    max-width: 600px;
}

.modal-actions {
    margin-top: 20px;
    text-align: right;
}

.modal-actions .button {
    margin-left: 10px;
}

.ssl-status {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.ssl-status.cloudflare { background: #f39c12; color: #fff; }
.ssl-status.lets_encrypt { background: #3498db; color: #fff; }
.ssl-status.manual { background: #9b59b6; color: #fff; }
.ssl-status.none { background: #95a5a6; color: #fff; }

.domain-link {
    font-size: 12px;
    color: #0073aa;
    text-decoration: none;
}

.domain-link:hover {
    text-decoration: underline;
}
</style> 