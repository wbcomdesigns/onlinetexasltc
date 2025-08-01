<?php
/**
 * Notifications Class
 * 
 * Handles email notifications for domain status changes and events
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dokan_Domain_Notifications {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'setup_notification_functionality'));
    }

    /**
     * Setup notification functionality
     */
    public function setup_notification_functionality() {
        // Hook into domain status changes
        add_action('dokan_domain_status_changed', array($this, 'notify_status_change'), 10, 3);
        add_action('dokan_domain_ssl_expiring', array($this, 'notify_ssl_expiry'), 10, 2);
        add_action('dokan_domain_ssl_provisioned', array($this, 'notify_ssl_provisioned'), 10, 2);
        add_action('dokan_domain_transferred', array($this, 'notify_domain_transfer'), 10, 3);
        
        // Add settings
        add_action('admin_init', array($this, 'register_notification_settings'));
    }

    /**
     * Register notification settings
     */
    public function register_notification_settings() {
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_email_notifications');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_admin_email');
        register_setting('dokan_domain_mapper_settings', 'dokan_domain_mapper_email_template');
    }

    /**
     * Notify domain status change
     */
    public function notify_status_change($domain_id, $old_status, $new_status) {
        global $wpdb;

        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping) {
            return;
        }

        $vendor = get_user_by('id', $domain_mapping->vendor_id);
        if (!$vendor) {
            return;
        }

        // Check if notifications are enabled
        if (get_option('dokan_domain_mapper_email_notifications', 'yes') !== 'yes') {
            return;
        }

        $subject = sprintf(__('Domain Status Changed: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        
        $message = $this->get_status_change_message($domain_mapping, $old_status, $new_status);
        
        // Send to vendor
        $this->send_email($vendor->user_email, $subject, $message);
        
        // Send to admin if status is approved or rejected
        if (in_array($new_status, array('approved', 'rejected'))) {
            $admin_email = get_option('dokan_domain_mapper_admin_email', get_option('admin_email'));
            $admin_subject = sprintf(__('Domain %s: %s', 'dokan-vendor-domain-mapper'), $new_status, $domain_mapping->domain);
            $admin_message = $this->get_admin_status_message($domain_mapping, $old_status, $new_status);
            $this->send_email($admin_email, $admin_subject, $admin_message);
        }
    }

    /**
     * Notify SSL expiry
     */
    public function notify_ssl_expiry($domain_mapping, $ssl_info) {
        $vendor = get_user_by('id', $domain_mapping->vendor_id);
        if (!$vendor) {
            return;
        }

        $subject = sprintf(__('SSL Certificate Expiring: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        
        $message = sprintf(
            __('Hello %s,

Your SSL certificate for %s is expiring soon.

Domain: %s
Expiry Date: %s
Days Remaining: %d

Please renew your SSL certificate to avoid service interruption.

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $vendor->display_name,
            $domain_mapping->domain,
            $domain_mapping->domain,
            $ssl_info['expiry'],
            $ssl_info['days_remaining'],
            get_bloginfo('name')
        );

        $this->send_email($vendor->user_email, $subject, $message);
    }

    /**
     * Notify SSL provisioned
     */
    public function notify_ssl_provisioned($domain_mapping, $provider) {
        $vendor = get_user_by('id', $domain_mapping->vendor_id);
        if (!$vendor) {
            return;
        }

        $subject = sprintf(__('SSL Certificate Provisioned: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        
        $message = sprintf(
            __('Hello %s,

Your SSL certificate for %s has been successfully provisioned.

Domain: %s
SSL Provider: %s
Status: Active

Your domain is now secure with HTTPS.

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $vendor->display_name,
            $domain_mapping->domain,
            $domain_mapping->domain,
            ucfirst($provider),
            get_bloginfo('name')
        );

        $this->send_email($vendor->user_email, $subject, $message);
    }

    /**
     * Notify domain transfer
     */
    public function notify_domain_transfer($domain_id, $old_vendor_id, $new_vendor_id) {
        global $wpdb;

        $domain_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dokan_domain_mappings WHERE id = %d",
            $domain_id
        ));

        if (!$domain_mapping) {
            return;
        }

        $old_vendor = get_user_by('id', $old_vendor_id);
        $new_vendor = get_user_by('id', $new_vendor_id);

        if (!$old_vendor || !$new_vendor) {
            return;
        }

        // Notify old vendor
        $old_vendor_subject = sprintf(__('Domain Transferred: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        $old_vendor_message = sprintf(
            __('Hello %s,

Your domain %s has been transferred to another vendor.

Domain: %s
New Owner: %s
Transfer Date: %s

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $old_vendor->display_name,
            $domain_mapping->domain,
            $domain_mapping->domain,
            $new_vendor->display_name,
            current_time('mysql'),
            get_bloginfo('name')
        );

        $this->send_email($old_vendor->user_email, $old_vendor_subject, $old_vendor_message);

        // Notify new vendor
        $new_vendor_subject = sprintf(__('Domain Received: %s', 'dokan-vendor-domain-mapper'), $domain_mapping->domain);
        $new_vendor_message = sprintf(
            __('Hello %s,

You have received a domain transfer.

Domain: %s
Previous Owner: %s
Transfer Date: %s

The domain is now under your management.

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $new_vendor->display_name,
            $domain_mapping->domain,
            $old_vendor->display_name,
            current_time('mysql'),
            get_bloginfo('name')
        );

        $this->send_email($new_vendor->user_email, $new_vendor_subject, $new_vendor_message);
    }

    /**
     * Get status change message
     */
    private function get_status_change_message($domain_mapping, $old_status, $new_status) {
        $vendor = get_user_by('id', $domain_mapping->vendor_id);
        
        $status_descriptions = array(
            'pending' => __('Your domain is pending verification.', 'dokan-vendor-domain-mapper'),
            'verified' => __('Your domain has been verified successfully.', 'dokan-vendor-domain-mapper'),
            'approved' => __('Your domain has been approved and is now live.', 'dokan-vendor-domain-mapper'),
            'rejected' => __('Your domain has been rejected.', 'dokan-vendor-domain-mapper'),
            'live' => __('Your domain is now live and accessible.', 'dokan-vendor-domain-mapper')
        );

        $message = sprintf(
            __('Hello %s,

Your domain status has changed.

Domain: %s
Previous Status: %s
New Status: %s

%s

Best regards,
%s', 'dokan-vendor-domain-mapper'),
            $vendor->display_name,
            $domain_mapping->domain,
            ucfirst($old_status),
            ucfirst($new_status),
            $status_descriptions[$new_status] ?? '',
            get_bloginfo('name')
        );

        return $message;
    }

    /**
     * Get admin status message
     */
    private function get_admin_status_message($domain_mapping, $old_status, $new_status) {
        $vendor = get_user_by('id', $domain_mapping->vendor_id);
        
        $message = sprintf(
            __('Domain status changed:

Domain: %s
Vendor: %s (%s)
Previous Status: %s
New Status: %s
Change Date: %s

View domain details: %s', 'dokan-vendor-domain-mapper'),
            $domain_mapping->domain,
            $vendor->display_name,
            $vendor->user_email,
            ucfirst($old_status),
            ucfirst($new_status),
            current_time('mysql'),
            admin_url('admin.php?page=dokan-domain-mapping')
        );

        return $message;
    }

    /**
     * Send email notification
     */
    private function send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        $template = get_option('dokan_domain_mapper_email_template', 'default');
        $formatted_message = $this->format_email_message($message, $template);

        return wp_mail($to, $subject, $formatted_message, $headers);
    }

    /**
     * Format email message with template
     */
    private function format_email_message($message, $template = 'default') {
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $admin_email = get_option('admin_email');

        $html_template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $site_name . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                <h2 style="color: #0073aa; margin: 0;">' . $site_name . '</h2>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                ' . nl2br($message) . '
            </div>
            
            <div style="text-align: center; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px; font-size: 12px; color: #666;">
                <p>This email was sent from <a href="' . $site_url . '">' . $site_name . '</a></p>
                <p>If you have any questions, please contact us at <a href="mailto:' . $admin_email . '">' . $admin_email . '</a></p>
            </div>
        </body>
        </html>';

        return $html_template;
    }

    /**
     * Send bulk notification
     */
    public function send_bulk_notification($recipients, $subject, $message) {
        $results = array();
        
        foreach ($recipients as $recipient) {
            $result = $this->send_email($recipient['email'], $subject, $message);
            $results[] = array(
                'email' => $recipient['email'],
                'success' => $result
            );
        }
        
        return $results;
    }

    /**
     * Get notification templates
     */
    public function get_notification_templates() {
        return array(
            'default' => __('Default Template', 'dokan-vendor-domain-mapper'),
            'minimal' => __('Minimal Template', 'dokan-vendor-domain-mapper'),
            'professional' => __('Professional Template', 'dokan-vendor-domain-mapper')
        );
    }

    /**
     * Test email notification
     */
    public function test_notification($email) {
        $subject = __('Test Email from Dokan Domain Mapper', 'dokan-vendor-domain-mapper');
        $message = __('This is a test email to verify that email notifications are working correctly.

If you received this email, the notification system is functioning properly.

Best regards,
' . get_bloginfo('name'), 'dokan-vendor-domain-mapper');

        return $this->send_email($email, $subject, $message);
    }
} 