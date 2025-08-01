<?php
/**
 * Vendor Domain Mapping Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$vendor_dashboard = new Dokan_Domain_Mapper_Vendor_Dashboard();
$limit_info = $vendor_dashboard->get_domain_limit_info($vendor_id);
?>

<div class="dokan-dashboard-header">
    <h1 class="entry-title"><?php _e('Store Domain Management', 'dokan-vendor-domain-mapper'); ?></h1>
</div>

<div class="dokan-dashboard-content">
    <!-- Domain Limit Info -->
    <div class="domain-limit-info">
        <div class="limit-box">
            <h3><?php _e('Domain Limits', 'dokan-vendor-domain-mapper'); ?></h3>
            <p>
                <?php printf(__('You can add up to %d domain(s). Currently using %d domain(s).', 'dokan-vendor-domain-mapper'), 
                    $limit_info['max'], $limit_info['current']); ?>
            </p>
            <?php if ($limit_info['remaining'] > 0): ?>
                <p class="remaining"><?php printf(__('%d domain(s) remaining', 'dokan-vendor-domain-mapper'), $limit_info['remaining']); ?></p>
            <?php else: ?>
                <p class="limit-reached"><?php _e('Domain limit reached', 'dokan-vendor-domain-mapper'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add New Domain -->
    <?php if ($limit_info['can_add']): ?>
        <div class="add-domain-section">
            <h3><?php _e('Add New Domain', 'dokan-vendor-domain-mapper'); ?></h3>
            <form id="add-domain-form" class="domain-form">
                <div class="form-row">
                    <label for="domain-input"><?php _e('Domain Name:', 'dokan-vendor-domain-mapper'); ?></label>
                    <input type="text" id="domain-input" name="domain" placeholder="example.com" required>
                    <button type="submit" class="button button-primary"><?php _e('Add Domain', 'dokan-vendor-domain-mapper'); ?></button>
                </div>
                <p class="description">
                    <?php _e('Enter your domain name (e.g., mystore.com or shop.vendor.com). Do not include http:// or https://', 'dokan-vendor-domain-mapper'); ?>
                </p>
            </form>
        </div>
    <?php endif; ?>

    <!-- Domain Statistics -->
    <div class="domain-stats">
        <div class="stat-item">
            <span class="stat-number"><?php echo $stats['total']; ?></span>
            <span class="stat-label"><?php _e('Total', 'dokan-vendor-domain-mapper'); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-number pending"><?php echo $stats['pending']; ?></span>
            <span class="stat-label"><?php _e('Pending', 'dokan-vendor-domain-mapper'); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-number verified"><?php echo $stats['verified']; ?></span>
            <span class="stat-label"><?php _e('Verified', 'dokan-vendor-domain-mapper'); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-number approved"><?php echo $stats['approved']; ?></span>
            <span class="stat-label"><?php _e('Approved', 'dokan-vendor-domain-mapper'); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-number live"><?php echo $stats['live']; ?></span>
            <span class="stat-label"><?php _e('Live', 'dokan-vendor-domain-mapper'); ?></span>
        </div>
    </div>

    <!-- Domain List -->
    <div class="domain-list">
        <h3><?php _e('Your Domains', 'dokan-vendor-domain-mapper'); ?></h3>
        
        <?php if (empty($domains)): ?>
            <div class="no-domains">
                <p><?php _e('You haven\'t added any domains yet.', 'dokan-vendor-domain-mapper'); ?></p>
                <?php if ($limit_info['can_add']): ?>
                    <p><?php _e('Add your first domain above to get started.', 'dokan-vendor-domain-mapper'); ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="domain-grid">
                <?php foreach ($domains as $domain): ?>
                    <div class="domain-card" data-domain-id="<?php echo $domain->id; ?>">
                        <div class="domain-header">
                            <h4><?php echo esc_html($domain->domain); ?></h4>
                            <?php echo $vendor_dashboard->get_domain_status_badge($domain->status); ?>
                        </div>
                        
                        <div class="domain-body">
                            <p class="status-description">
                                <?php echo $vendor_dashboard->get_domain_status_description($domain->status); ?>
                            </p>
                            
                            <?php if ($domain->status === 'pending'): ?>
                                <div class="verification-info">
                                    <h5><?php _e('DNS Verification Required', 'dokan-vendor-domain-mapper'); ?></h5>
                                    <div class="txt-record">
                                        <strong><?php _e('TXT Record:', 'dokan-vendor-domain-mapper'); ?></strong>
                                        <code><?php echo esc_html($domain->verification_token); ?></code>
                                    </div>
                                    <button type="button" class="button button-primary verify-domain" 
                                            data-domain-id="<?php echo $domain->id; ?>" 
                                            data-domain="<?php echo esc_attr($domain->domain); ?>" 
                                            data-token="<?php echo esc_attr($domain->verification_token); ?>">
                                        <?php _e('Verify Domain', 'dokan-vendor-domain-mapper'); ?>
                                    </button>
                                    <button type="button" class="button show-instructions">
                                        <?php _e('View Instructions', 'dokan-vendor-domain-mapper'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($domain->status === 'live'): ?>
                                <div class="live-info">
                                    <a href="https://<?php echo esc_attr($domain->domain); ?>" target="_blank" class="button button-primary">
                                        <?php _e('Visit Site', 'dokan-vendor-domain-mapper'); ?>
                                    </a>
                                    <button type="button" class="button check-health">
                                        <?php _e('Check Health', 'dokan-vendor-domain-mapper'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="domain-footer">
                            <div class="domain-actions">
                                <?php echo $vendor_dashboard->get_domain_action_buttons($domain); ?>
                            </div>
                            <div class="domain-meta">
                                <small>
                                    <?php printf(__('Added: %s', 'dokan-vendor-domain-mapper'), 
                                        date_i18n(get_option('date_format'), strtotime($domain->created_at))); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Verification Instructions Modal -->
<div id="verification-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3><?php _e('DNS Verification Instructions', 'dokan-vendor-domain-mapper'); ?></h3>
        <div id="verification-content"></div>
        <div class="modal-actions">
            <button type="button" class="button close-modal"><?php _e('Close', 'dokan-vendor-domain-mapper'); ?></button>
        </div>
    </div>
</div>

<!-- Domain Health Modal -->
<div id="health-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3><?php _e('Domain Health Check', 'dokan-vendor-domain-mapper'); ?></h3>
        <div id="health-content"></div>
        <div class="modal-actions">
            <button type="button" class="button close-modal"><?php _e('Close', 'dokan-vendor-domain-mapper'); ?></button>
        </div>
    </div>
</div>

<style>
.dokan-dashboard-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.domain-limit-info {
    margin-bottom: 30px;
}

.limit-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.limit-box h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.limit-box .remaining {
    color: #28a745;
    font-weight: bold;
}

.limit-box .limit-reached {
    color: #dc3545;
    font-weight: bold;
}

.add-domain-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.add-domain-section h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.domain-form .form-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.domain-form label {
    min-width: 120px;
    font-weight: bold;
}

.domain-form input[type="text"] {
    flex: 1;
    max-width: 300px;
}

.domain-form .description {
    color: #666;
    font-size: 14px;
    margin: 0;
}

.domain-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    justify-content: center;
}

.stat-item {
    text-align: center;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    min-width: 80px;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.stat-number.pending { color: #f39c12; }
.stat-number.verified { color: #3498db; }
.stat-number.approved { color: #27ae60; }
.stat-number.live { color: #2ecc71; }

.stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.domain-list h3 {
    margin-bottom: 20px;
    color: #333;
}

.no-domains {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

.domain-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.domain-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.3s ease;
}

.domain-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.domain-header {
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.domain-header h4 {
    margin: 0;
    color: #333;
    font-size: 16px;
}

.domain-body {
    padding: 15px;
}

.status-description {
    color: #666;
    font-size: 14px;
    margin: 0 0 15px 0;
}

.verification-info h5 {
    margin: 0 0 10px 0;
    color: #333;
}

.txt-record {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 15px;
}

.txt-record code {
    display: block;
    margin-top: 5px;
    word-break: break-all;
    font-size: 12px;
}

.verification-info .button,
.live-info .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.domain-footer {
    padding: 15px;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.domain-actions .button {
    margin-right: 5px;
    margin-bottom: 5px;
}

.domain-meta small {
    color: #666;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.status-pending { background: #fff3cd; color: #856404; }
.status-badge.status-verified { background: #d1ecf1; color: #0c5460; }
.status-badge.status-approved { background: #d4edda; color: #155724; }
.status-badge.status-rejected { background: #f8d7da; color: #721c24; }
.status-badge.status-live { background: #d1e7dd; color: #0f5132; }

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
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-actions {
    margin-top: 20px;
    text-align: right;
}

.modal-actions .button {
    margin-left: 10px;
}

@media (max-width: 768px) {
    .domain-form .form-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .domain-form input[type="text"] {
        max-width: none;
    }
    
    .domain-stats {
        flex-wrap: wrap;
    }
    
    .domain-grid {
        grid-template-columns: 1fr;
    }
    
    .domain-footer {
        flex-direction: column;
        gap: 10px;
    }
}
</style> 