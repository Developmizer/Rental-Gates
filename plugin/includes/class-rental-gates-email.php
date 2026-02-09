<?php
/**
 * Rental Gates Email Class
 * 
 * Modern, responsive email system with inline styles for maximum compatibility.
 * Supports transactional and notification emails with consistent branding.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rental_Gates_Email {
    
    /**
     * Email templates with default subjects
     */
    const TEMPLATES = array(
        // Account & Access
        'welcome' => 'Welcome to %s',
        'password_reset' => 'Reset Your Password',
        'magic_link' => 'Your Secure Login Link',
        'tenant_invitation' => 'Your Tenant Portal Access',
        'staff_invitation' => 'You\'ve Been Invited to Join the Team',
        'vendor_invitation' => 'Vendor Portal Access',
        
        // Applications
        'application_received' => 'Application Received',
        'application_approved' => 'Great News - Your Application is Approved!',
        'application_declined' => 'Application Status Update',
        
        // Leases
        'lease_created' => 'Your Lease Agreement is Ready',
        'lease_signed' => 'Lease Agreement Signed',
        'lease_ending' => 'Lease Expiration Notice - Action Required',
        'renewal_offer' => 'Your Lease Renewal Offer',
        
        // Payments
        'payment_receipt' => 'Payment Confirmation',
        'payment_reminder' => 'Rent Payment Reminder',
        'payment_overdue' => 'Important: Payment Overdue',
        'payment_failed' => 'Payment Failed - Action Required',
        
        // Subscriptions
        'subscription_confirmed' => 'Subscription Confirmed - Welcome!',
        
        // Maintenance
        'maintenance_created' => 'Maintenance Request Received',
        'maintenance_assigned' => 'Technician Assigned to Your Request',
        'maintenance_update' => 'Maintenance Request Update',
        'maintenance_completed' => 'Maintenance Request Completed',
        'maintenance_survey' => 'How Did We Do? Rate Your Service',
        
        // Vendor
        'vendor_assignment' => 'New Work Order Assignment',
        'vendor_reminder' => 'Work Order Reminder',
        
        // Communications
        'announcement' => 'Important Announcement',
        'message_received' => 'New Message',
        
        // Leads
        'lead_inquiry' => 'New Inquiry Received',
        'lead_followup' => 'Following Up on Your Interest',
    );
    
    /**
     * Brand colors
     */
    private static $colors = array(
        'primary' => '#6366f1',
        'primary_dark' => '#4f46e5',
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'danger' => '#ef4444',
        'gray_50' => '#f9fafb',
        'gray_100' => '#f3f4f6',
        'gray_200' => '#e5e7eb',
        'gray_500' => '#6b7280',
        'gray_700' => '#374151',
        'gray_900' => '#111827',
        'white' => '#ffffff',
    );
    
    /**
     * Send email
     */
    public static function send($to, $template, $data = array(), $attachments = array()) {
        // Validate email address
        if (empty($to) || !is_email($to)) {
            error_log('Rental Gates Email: Invalid email address: ' . $to);
            return new WP_Error('invalid_email', __('Invalid email address', 'rental-gates'));
        }
        
        $subject = self::get_subject($template, $data);
        $content = self::render_template($template, $data);
        
        if (!$content) {
            error_log('Rental Gates Email: Template not found or empty: ' . $template);
            return new WP_Error('template_not_found', __('Email template not found', 'rental-gates'));
        }
        
        $from_name = get_option('rental_gates_email_from_name', get_bloginfo('name'));
        $from_email = get_option('rental_gates_email_from_address', get_option('admin_email'));
        
        // Validate from email
        if (empty($from_email) || !is_email($from_email)) {
            error_log('Rental Gates Email: Invalid from email address: ' . $from_email);
            $from_email = get_option('admin_email');
        }
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        );
        
        if (!empty($data['reply_to'])) {
            $headers[] = "Reply-To: {$data['reply_to']}";
        }
        
        error_log('Rental Gates Email: Sending email to: ' . $to . ', template: ' . $template . ', subject: ' . $subject);
        
        $sent = wp_mail($to, $subject, $content, $headers, $attachments);
        
        // Log email attempt (with error handling in case activity_log table doesn't exist)
        try {
            self::log_email($to, $template, $subject, $sent);
        } catch (Exception $e) {
            error_log('Rental Gates Email: Failed to log email: ' . $e->getMessage());
        }
        
        if (!$sent) {
            error_log('Rental Gates Email: wp_mail returned false for: ' . $to);
        }
        
        return $sent;
    }
    
    /**
     * Send to multiple recipients
     */
    public static function send_bulk($recipients, $template, $data = array()) {
        $results = array();
        foreach ($recipients as $email) {
            $results[$email] = self::send($email, $template, $data);
        }
        return $results;
    }
    
    /**
     * Get email subject
     */
    private static function get_subject($template, $data) {
        $subject_template = self::TEMPLATES[$template] ?? ucfirst(str_replace('_', ' ', $template));
        
        // Replace %s with organization name
        $org_name = $data['organization_name'] ?? get_bloginfo('name');
        $subject = sprintf($subject_template, $org_name);
        
        // Replace custom placeholders
        if (!empty($data['subject_vars'])) {
            foreach ($data['subject_vars'] as $key => $value) {
                $subject = str_replace('{' . $key . '}', $value, $subject);
            }
        }
        
        return $subject;
    }
    
    /**
     * Render email template
     */
    private static function render_template($template, $data) {
        // Check for custom template in theme
        $template_file = get_stylesheet_directory() . '/rental-gates/emails/' . $template . '.php';
        
        // Fall back to plugin template
        if (!file_exists($template_file)) {
            $template_file = RENTAL_GATES_PLUGIN_DIR . 'templates/emails/' . $template . '.php';
        }
        
        // Fall back to generic template
        if (!file_exists($template_file)) {
            $template_file = RENTAL_GATES_PLUGIN_DIR . 'templates/emails/generic.php';
        }
        
        if (!file_exists($template_file)) {
            return self::render_fallback($template, $data);
        }
        
        // Protect critical local variables from being overwritten by extract()
        // EXTR_SKIP ensures existing vars aren't overwritten, preventing
        // malicious keys like 'template_file' from hijacking the include path
        $_rg_template_file = $template_file;
        $email_data = $data; // Also available as $email_data in templates
        extract($data, EXTR_SKIP);
        ob_start();
        include $_rg_template_file;
        $content = ob_get_clean();
        
        return self::wrap_in_layout($content, $data);
    }
    
    /**
     * Modern responsive email layout with full dark mode support, accessibility, and email client compatibility
     */
    private static function wrap_in_layout($content, $data) {
        $org_name = $data['organization_name'] ?? get_bloginfo('name');
        $org_address = $data['organization_address'] ?? '';
        $org_phone = $data['organization_phone'] ?? '';
        $org_email = $data['organization_email'] ?? '';
        $primary_color = $data['primary_color'] ?? self::$colors['primary'];
        $preheader = $data['preheader'] ?? '';
        
        $logo_url = '';
        if (!empty($data['organization_logo'])) {
            $logo_url = is_numeric($data['organization_logo']) 
                ? wp_get_attachment_url($data['organization_logo']) 
                : $data['organization_logo'];
        }
        
        $year = date('Y');
        
        // Dark mode colors with proper contrast ratios (WCAG AA compliant)
        $bg_light = '#f3f4f6';
        $bg_dark = '#1f2937';
        $container_light = '#ffffff';
        $container_dark = '#111827';
        $text_light = '#374151';
        $text_dark = '#f9fafb';
        $text_gray_light = '#6b7280';
        $text_gray_dark = '#d1d5db';
        $border_light = '#e5e7eb';
        $border_dark = '#374151';
        $footer_bg_light = '#f9fafb';
        $footer_bg_dark = '#1f2937';
        
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title><?php echo esc_html($org_name); ?></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Reset and base styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        
        /* Dark mode support */
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }
        
        /* Media queries for responsive design */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .email-body {
                padding: 24px 20px !important;
            }
            .email-header {
                padding: 24px 20px !important;
            }
            .email-footer {
                padding: 24px 20px !important;
            }
            .mobile-hide {
                display: none !important;
            }
            .mobile-center {
                text-align: center !important;
            }
            h1 {
                font-size: 24px !important;
                line-height: 1.3 !important;
            }
            h2 {
                font-size: 20px !important;
                line-height: 1.4 !important;
            }
        }
        
        /* Dark mode media query */
        @media (prefers-color-scheme: dark) {
            .email-bg {
                background-color: <?php echo $bg_dark; ?> !important;
            }
            .email-container {
                background-color: <?php echo $container_dark; ?> !important;
            }
            .email-body {
                background-color: <?php echo $container_dark; ?> !important;
                color: <?php echo $text_dark; ?> !important;
            }
            .email-footer {
                background-color: <?php echo $footer_bg_dark; ?> !important;
                border-top-color: <?php echo $border_dark; ?> !important;
            }
            .text-primary {
                color: <?php echo $text_dark; ?> !important;
            }
            .text-secondary {
                color: <?php echo $text_gray_dark; ?> !important;
            }
            .border-color {
                border-color: <?php echo $border_dark; ?> !important;
            }
            .footer-text {
                color: <?php echo $text_gray_dark; ?> !important;
            }
            .footer-link {
                color: <?php echo $text_gray_dark; ?> !important;
            }
        }
        
        /* Print styles */
        @media print {
            .email-bg {
                background-color: <?php echo $bg_light; ?> !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; word-spacing: normal; background-color: <?php echo $bg_light; ?>; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    <?php if ($preheader): ?>
    <!-- Preheader text for email clients -->
    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; visibility: hidden;">
        <?php echo esc_html($preheader); ?>
    </div>
    <?php endif; ?>
    
    <!-- Main email wrapper -->
    <div role="article" aria-roledescription="email" aria-label="<?php echo esc_attr(sprintf(__('Email from %s', 'rental-gates'), $org_name)); ?>" lang="en" style="text-size-adjust: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
        <table role="presentation" class="email-bg" style="width: 100%; border: none; border-collapse: collapse; border-spacing: 0; background-color: <?php echo $bg_light; ?>; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
            <tr>
                <td align="center" style="padding: 40px 20px; word-break: break-word;">
                    <!--[if mso]>
                    <table role="presentation" align="center" style="width:600px;" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                    <td>
                    <![endif]-->
                    <table role="presentation" class="email-container" style="width: 100%; max-width: 600px; border: none; border-collapse: collapse; border-spacing: 0; background-color: <?php echo $container_light; ?>; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                        <!-- Header -->
                        <tr>
                            <td class="email-header" style="padding: 32px 40px; text-align: center; background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?> 0%, #8b5cf6 100%); word-break: break-word;">
                                <?php if ($logo_url): ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($org_name); ?>" width="180" height="48" style="max-height: 48px; max-width: 180px; height: auto; width: auto; display: inline-block; border: 0; outline: none; text-decoration: none;">
                                <?php else: ?>
                                    <h1 style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px; line-height: 1.2;">
                                        <?php echo esc_html($org_name); ?>
                                    </h1>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- Body -->
                        <tr>
                            <td class="email-body text-primary" style="padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.7; color: <?php echo $text_light; ?>; word-break: break-word;">
                                <?php echo $content; ?>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td class="email-footer" style="padding: 32px 40px; background-color: <?php echo $footer_bg_light; ?>; border-top: 1px solid <?php echo $border_light; ?>; word-break: break-word;">
                                <table role="presentation" style="width: 100%; border: none; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                                    <tr>
                                        <td style="text-align: center; padding-bottom: 20px; word-break: break-word;">
                                            <p style="margin: 0 0 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; font-weight: 600; color: <?php echo $text_light; ?>; line-height: 1.5;">
                                                <?php echo esc_html($org_name); ?>
                                            </p>
                                            <?php if ($org_address): ?>
                                            <p class="footer-text" style="margin: 0 0 4px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: <?php echo $text_gray_light; ?>; line-height: 1.5;">
                                                <?php echo esc_html($org_address); ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if ($org_phone || $org_email): ?>
                                            <p class="footer-text" style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: <?php echo $text_gray_light; ?>; line-height: 1.5;">
                                                <?php if ($org_phone): ?>
                                                    <a href="tel:<?php echo esc_attr($org_phone); ?>" class="footer-link" style="color: <?php echo $text_gray_light; ?>; text-decoration: none; border-bottom: 1px solid transparent;"><?php echo esc_html($org_phone); ?></a>
                                                    <?php if ($org_email): ?> <span aria-hidden="true" style="color: <?php echo $text_gray_light; ?>;">&bull;</span> <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($org_email): ?>
                                                    <a href="mailto:<?php echo esc_attr($org_email); ?>" class="footer-link" style="color: <?php echo $text_gray_light; ?>; text-decoration: none; border-bottom: 1px solid transparent;"><?php echo esc_html($org_email); ?></a>
                                                <?php endif; ?>
                                            </p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align: center; padding-top: 20px; border-top: 1px solid <?php echo $border_light; ?>; word-break: break-word;">
                                            <p class="footer-text" style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: <?php echo $text_gray_light; ?>; line-height: 1.5;">
                                                &copy; <?php echo $year; ?> <?php echo esc_html($org_name); ?>. <?php _e('All rights reserved.', 'rental-gates'); ?>
                                            </p>
                                            <p class="footer-text" style="margin: 8px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: <?php echo $text_gray_light; ?>; line-height: 1.5;">
                                                <?php _e('Powered by', 'rental-gates'); ?> <a href="https://rentalgates.com" style="color: <?php echo esc_attr($primary_color); ?>; text-decoration: none; border-bottom: 1px solid transparent;">Rental Gates</a>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    <!--[if mso]>
                    </td>
                    </tr>
                    </table>
                    <![endif]-->
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render fallback template
     */
    private static function render_fallback($template, $data) {
        $title = self::TEMPLATES[$template] ?? 'Notification';
        $org_name = $data['organization_name'] ?? get_bloginfo('name');
        $title = sprintf($title, $org_name);
        
        ob_start();
        ?>
        <h2 style="margin: 0 0 16px; font-size: 24px; font-weight: 700; color: #111827;"><?php echo esc_html($title); ?></h2>
        <?php if (!empty($data['message'])): ?>
            <p style="margin: 0 0 24px; color: #374151;"><?php echo wp_kses_post($data['message']); ?></p>
        <?php endif; ?>
        <?php if (!empty($data['action_url'])): ?>
            <?php echo self::button($data['action_url'], $data['action_text'] ?? __('View Details', 'rental-gates')); ?>
        <?php endif; ?>
        <?php
        return self::wrap_in_layout(ob_get_clean(), $data);
    }
    
    /**
     * Generate a CTA button with dark mode support
     */
    public static function button($url, $text, $style = 'primary') {
        $colors = array(
            'primary' => array('bg' => '#6366f1', 'text' => '#ffffff', 'bg_dark' => '#6366f1', 'text_dark' => '#ffffff'),
            'success' => array('bg' => '#10b981', 'text' => '#ffffff', 'bg_dark' => '#10b981', 'text_dark' => '#ffffff'),
            'warning' => array('bg' => '#f59e0b', 'text' => '#ffffff', 'bg_dark' => '#f59e0b', 'text_dark' => '#ffffff'),
            'danger' => array('bg' => '#ef4444', 'text' => '#ffffff', 'bg_dark' => '#ef4444', 'text_dark' => '#ffffff'),
            'outline' => array('bg' => 'transparent', 'text' => '#6366f1', 'border' => '#6366f1', 'bg_dark' => 'transparent', 'text_dark' => '#818cf8', 'border_dark' => '#818cf8'),
        );
        
        $c = $colors[$style] ?? $colors['primary'];
        $border = isset($c['border']) ? "border: 2px solid {$c['border']};" : '';
        $border_dark = isset($c['border_dark']) ? "border: 2px solid {$c['border_dark']};" : '';
        $unique_id = 'btn-' . wp_generate_password(8, false);
        
        return sprintf(
            '<table role="presentation" class="email-button-%s" style="margin: 24px 0; border: none; border-collapse: collapse; border-spacing: 0; width: 100%%; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                <tr>
                    <td align="center" style="word-break: break-word;">
                        <table role="presentation" style="border: none; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                            <tr>
                                <td style="border-radius: 10px; background-color: %s; %s">
                                    <a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 14px 32px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; font-size: 16px; font-weight: 600; color: %s; text-decoration: none; border-radius: 10px; line-height: 1.5; text-align: center; min-width: 200px; -webkit-text-size-adjust: none;">%s</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <style type="text/css">
                @media (prefers-color-scheme: dark) {
                    .email-button-%s td {
                        background-color: %s !important;
                        %s
                    }
                    .email-button-%s a {
                        color: %s !important;
                    }
                }
                @media only screen and (max-width: 600px) {
                    .email-button-%s a {
                        padding: 12px 24px !important;
                        font-size: 15px !important;
                        min-width: auto !important;
                    }
                }
            </style>',
            $unique_id,
            esc_attr($c['bg']),
            $border,
            esc_url($url),
            esc_attr($c['text']),
            esc_html($text),
            $unique_id,
            esc_attr($c['bg_dark'] ?? $c['bg']),
            $border_dark ?: $border,
            $unique_id,
            esc_attr($c['text_dark'] ?? $c['text']),
            $unique_id
        );
    }
    
    /**
     * Generate an info box with dark mode support
     */
    public static function info_box($content, $style = 'info') {
        $styles = array(
            'info' => array(
                'bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e40af',
                'bg_dark' => '#1e3a8a', 'border_dark' => '#3b82f6', 'text_dark' => '#dbeafe'
            ),
            'success' => array(
                'bg' => '#f0fdf4', 'border' => '#bbf7d0', 'text' => '#166534',
                'bg_dark' => '#14532d', 'border_dark' => '#22c55e', 'text_dark' => '#dcfce7'
            ),
            'warning' => array(
                'bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e',
                'bg_dark' => '#78350f', 'border_dark' => '#f59e0b', 'text_dark' => '#fef3c7'
            ),
            'danger' => array(
                'bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b',
                'bg_dark' => '#7f1d1d', 'border_dark' => '#ef4444', 'text_dark' => '#fee2e2'
            ),
            'gray' => array(
                'bg' => '#f9fafb', 'border' => '#e5e7eb', 'text' => '#374151',
                'bg_dark' => '#374151', 'border_dark' => '#6b7280', 'text_dark' => '#f3f4f6'
            ),
        );
        
        $s = $styles[$style] ?? $styles['info'];
        $unique_id = 'info-' . uniqid();
        
        return sprintf(
            '<table role="presentation" class="info-box-%s" style="width: 100%%; margin: 24px 0; border: none; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                <tr>
                    <td style="padding: 20px 24px; background-color: %s; border: 1px solid %s; border-radius: 12px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; font-size: 15px; line-height: 1.6; color: %s; word-break: break-word;">
                        %s
                    </td>
                </tr>
            </table>
            <style type="text/css">
                @media (prefers-color-scheme: dark) {
                    .info-box-%s td {
                        background-color: %s !important;
                        border-color: %s !important;
                        color: %s !important;
                    }
                }
                @media only screen and (max-width: 600px) {
                    .info-box-%s td {
                        padding: 16px 20px !important;
                        font-size: 14px !important;
                    }
                }
            </style>',
            sanitize_html_class($style),
            esc_attr($s['bg']),
            esc_attr($s['border']),
            esc_attr($s['text']),
            $content,
            sanitize_html_class($style),
            esc_attr($s['bg_dark'] ?? $s['bg']),
            esc_attr($s['border_dark'] ?? $s['border']),
            esc_attr($s['text_dark'] ?? $s['text']),
            sanitize_html_class($style)
        );
    }
    
    /**
     * Generate a detail row (label: value) with dark mode support
     */
    public static function detail_row($label, $value, $is_last = false) {
        $border_light = $is_last ? '' : 'border-bottom: 1px solid #e5e7eb;';
        $border_dark = $is_last ? '' : 'border-bottom: 1px solid #374151;';
        $unique_id = 'detail-' . uniqid();
        
        return sprintf(
            '<tr class="detail-row-%s">
                <td style="padding: 12px 0; %s word-break: break-word;">
                    <span style="display: block; font-size: 13px; font-weight: 500; color: #6b7280; margin-bottom: 4px; line-height: 1.5;">%s</span>
                    <span style="display: block; font-size: 15px; font-weight: 600; color: #111827; line-height: 1.5;">%s</span>
                </td>
            </tr>
            <style type="text/css">
                @media (prefers-color-scheme: dark) {
                    .detail-row-%s td {
                        %s
                    }
                    .detail-row-%s span:first-child {
                        color: #d1d5db !important;
                    }
                    .detail-row-%s span:last-child {
                        color: #f9fafb !important;
                    }
                }
            </style>',
            $unique_id,
            $border_light,
            esc_html($label),
            esc_html($value),
            $unique_id,
            $border_dark,
            $unique_id,
            $unique_id
        );
    }
    
    /**
     * Start a details table with dark mode support
     */
    public static function details_table_start() {
        $unique_id = 'details-' . uniqid();
        return sprintf(
            '<table role="presentation" class="details-table-%s" style="width: 100%%; margin: 24px 0; border: 1px solid #e5e7eb; border-radius: 12px; border-collapse: collapse; border-spacing: 0; overflow: hidden; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                <tr><td style="padding: 0 20px; word-break: break-word;">
            <style type="text/css">
                @media (prefers-color-scheme: dark) {
                    .details-table-%s {
                        border-color: #374151 !important;
                    }
                }
                @media only screen and (max-width: 600px) {
                    .details-table-%s {
                        margin: 20px 0 !important;
                    }
                    .details-table-%s td {
                        padding: 0 16px !important;
                    }
                }
            </style>',
            $unique_id,
            $unique_id,
            $unique_id,
            $unique_id
        );
    }
    
    /**
     * End a details table
     */
    public static function details_table_end() {
        return '</td></tr></table>';
    }
    
    /**
     * Generate a divider with dark mode support
     */
    public static function divider() {
        $unique_id = 'divider-' . uniqid();
        return sprintf(
            '<table role="presentation" class="divider-%s" style="width: 100%%; margin: 32px 0; border: none; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                <tr>
                    <td style="height: 1px; border-top: 1px solid #e5e7eb; line-height: 1px; font-size: 1px;">&nbsp;</td>
                </tr>
            </table>
            <style type="text/css">
                @media (prefers-color-scheme: dark) {
                    .divider-%s td {
                        border-top-color: #374151 !important;
                    }
                }
                @media only screen and (max-width: 600px) {
                    .divider-%s {
                        margin: 24px 0 !important;
                    }
                }
            </style>',
            $unique_id,
            $unique_id,
            $unique_id
        );
    }
    
    /**
     * Log sent email
     */
    private static function log_email($to, $template, $subject, $sent) {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        
        $wpdb->insert(
            $tables['activity_log'],
            array(
                'user_id' => get_current_user_id(),
                'action' => 'email_sent',
                'entity_type' => 'email',
                'new_values' => wp_json_encode(array(
                    'to' => $to,
                    'template' => $template,
                    'subject' => $subject,
                    'sent' => $sent,
                    'sent_at' => current_time('mysql'),
                )),
                'created_at' => current_time('mysql'),
            )
        );
    }
    
    // ===== HELPER METHODS FOR COMMON EMAILS =====
    
    /**
     * Send welcome email to new owner
     */
    public static function send_welcome($user_id, $organization_id) {
        $user = get_user_by('ID', $user_id);
        $org = Rental_Gates_Organization::get($organization_id);
        
        if (!$user || !$org) return false;
        
        return self::send($user->user_email, 'welcome', array(
            'user_name' => $user->display_name,
            'organization_name' => $org['name'],
            'dashboard_url' => home_url('/rental-gates/dashboard'),
            'preheader' => sprintf(__('Your account for %s is ready!', 'rental-gates'), $org['name']),
        ));
    }
    
    /**
     * Send tenant portal invitation
     */
    public static function send_tenant_invitation($tenant_id, $temp_password) {
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant) return false;
        
        $org = Rental_Gates_Organization::get($tenant['organization_id']);
        
        return self::send($tenant['email'], 'tenant_invitation', array(
            'tenant_name' => $tenant['first_name'] . ' ' . $tenant['last_name'],
            'organization_name' => $org['name'] ?? '',
            'organization_phone' => $org['contact_phone'] ?? '',
            'organization_email' => $org['contact_email'] ?? '',
            'temp_password' => $temp_password,
            'login_url' => home_url('/rental-gates/login'),
            'preheader' => __('Your tenant portal is ready. Log in to manage your account.', 'rental-gates'),
        ));
    }
    
    /**
     * Send payment receipt
     */
    public static function send_payment_receipt($payment_id) {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, t.email, t.first_name, t.last_name, o.name as org_name, o.contact_phone, o.contact_email
             FROM {$tables['payments']} p
             LEFT JOIN {$tables['tenants']} t ON p.tenant_id = t.id
             LEFT JOIN {$tables['organizations']} o ON p.organization_id = o.id
             WHERE p.id = %d",
            $payment_id
        ), ARRAY_A);
        
        if (!$payment || !$payment['email']) return false;
        
        return self::send($payment['email'], 'payment_receipt', array(
            'tenant_name' => $payment['first_name'] . ' ' . $payment['last_name'],
            'organization_name' => $payment['org_name'],
            'organization_phone' => $payment['contact_phone'],
            'organization_email' => $payment['contact_email'],
            'payment_number' => $payment['payment_number'],
            'amount' => number_format($payment['amount_paid'], 2),
            'payment_date' => date('F j, Y', strtotime($payment['paid_at'])),
            'payment_method' => ucfirst(str_replace('_', ' ', $payment['method'])),
            'preheader' => sprintf(__('Payment of $%s received. Thank you!', 'rental-gates'), number_format($payment['amount_paid'], 2)),
        ));
    }
    
    /**
     * Send maintenance update
     */
    public static function send_maintenance_update($work_order_id, $status) {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        
        $work_order = $wpdb->get_row($wpdb->prepare(
            "SELECT w.*, t.email, t.first_name, o.name as org_name
             FROM {$tables['work_orders']} w
             LEFT JOIN {$tables['tenants']} t ON w.tenant_id = t.id
             LEFT JOIN {$tables['organizations']} o ON w.organization_id = o.id
             WHERE w.id = %d",
            $work_order_id
        ), ARRAY_A);
        
        if (!$work_order || !$work_order['email']) return false;
        
        $template = $status === 'completed' ? 'maintenance_completed' : 'maintenance_update';
        
        return self::send($work_order['email'], $template, array(
            'tenant_name' => $work_order['first_name'],
            'organization_name' => $work_order['org_name'],
            'work_order_id' => $work_order_id,
            'work_order_title' => $work_order['title'],
            'status' => $status,
            'action_url' => home_url('/rental-gates/tenant/maintenance/' . $work_order_id),
            'preheader' => sprintf(__('Update on your maintenance request: %s', 'rental-gates'), $work_order['title']),
        ));
    }
    
    /**
     * Send inquiry notification to property manager
     */
    public static function send_inquiry_notification($org_id, $lead_data, $building_id = 0, $unit_id = 0) {
        $organization = Rental_Gates_Organization::get($org_id);
        if (!$organization || empty($organization['contact_email'])) return false;
        
        $property_name = '';
        if ($unit_id) {
            $unit = Rental_Gates_Unit::get($unit_id);
            $building = $unit ? Rental_Gates_Building::get($unit['building_id']) : null;
            $property_name = $unit ? ($unit['name'] . ' at ' . ($building['name'] ?? '')) : '';
        } elseif ($building_id) {
            $building = Rental_Gates_Building::get($building_id);
            $property_name = $building ? $building['name'] : '';
        }
        
        return self::send($organization['contact_email'], 'lead_inquiry', array(
            'organization_name' => $organization['name'],
            'lead_name' => $lead_data['name'],
            'lead_email' => $lead_data['email'],
            'lead_phone' => $lead_data['phone'] ?? '',
            'lead_message' => $lead_data['notes'] ?? '',
            'property_name' => $property_name,
            'source' => $lead_data['source'] ?? 'profile',
            'action_url' => home_url('/rental-gates/dashboard/leads'),
            'preheader' => sprintf(__('New inquiry from %s', 'rental-gates'), $lead_data['name']),
        ));
    }
    
    /**
     * Send lease signed confirmation
     */
    public static function send_lease_signed($lease_id) {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        
        $lease = Rental_Gates_Lease::get_with_details($lease_id);
        if (!$lease) return false;
        
        $org = Rental_Gates_Organization::get($lease['organization_id']);
        $unit = Rental_Gates_Unit::get($lease['unit_id']);
        $building = $unit ? Rental_Gates_Building::get($unit['building_id']) : null;
        
        // Get tenants
        $tenants = $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$tables['lease_tenants']} lt
             JOIN {$tables['tenants']} t ON lt.tenant_id = t.id
             WHERE lt.lease_id = %d",
            $lease_id
        ), ARRAY_A);
        
        $results = array();
        foreach ($tenants as $tenant) {
            if (!empty($tenant['email'])) {
                $results[$tenant['email']] = self::send($tenant['email'], 'lease_signed', array(
                    'tenant_name' => $tenant['first_name'] . ' ' . $tenant['last_name'],
                    'organization_name' => $org['name'] ?? '',
                    'unit_name' => $unit['name'] ?? '',
                    'building_name' => $building['name'] ?? '',
                    'start_date' => date('F j, Y', strtotime($lease['start_date'])),
                    'end_date' => date('F j, Y', strtotime($lease['end_date'])),
                    'rent_amount' => $lease['rent_amount'],
                    'dashboard_url' => home_url('/rental-gates/tenant'),
                    'preheader' => __('Your lease is now active!', 'rental-gates'),
                ));
            }
        }
        
        return $results;
    }
    
    /**
     * Send renewal offer
     */
    public static function send_renewal_offer($lease_id, $new_rent = null) {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        
        $lease = Rental_Gates_Lease::get($lease_id);
        if (!$lease) return false;
        
        $org = Rental_Gates_Organization::get($lease['organization_id']);
        $unit = Rental_Gates_Unit::get($lease['unit_id']);
        
        $tenants = $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$tables['lease_tenants']} lt
             JOIN {$tables['tenants']} t ON lt.tenant_id = t.id
             WHERE lt.lease_id = %d",
            $lease_id
        ), ARRAY_A);
        
        $results = array();
        foreach ($tenants as $tenant) {
            if (!empty($tenant['email'])) {
                $results[$tenant['email']] = self::send($tenant['email'], 'renewal_offer', array(
                    'tenant_name' => $tenant['first_name'] . ' ' . $tenant['last_name'],
                    'organization_name' => $org['name'] ?? '',
                    'unit_name' => $unit['name'] ?? '',
                    'current_end_date' => date('F j, Y', strtotime($lease['end_date'])),
                    'current_rent' => $lease['rent_amount'],
                    'new_rent_amount' => $new_rent ?? $lease['rent_amount'],
                    'renewal_url' => home_url('/rental-gates/tenant/lease'),
                    'preheader' => __('Your lease renewal offer is ready!', 'rental-gates'),
                ));
            }
        }
        
        return $results;
    }
    
    /**
     * Send payment failed notification
     */
    public static function send_payment_failed($tenant_id, $amount, $reason = '') {
        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || empty($tenant['email'])) return false;
        
        $org = Rental_Gates_Organization::get($tenant['organization_id']);
        
        return self::send($tenant['email'], 'payment_failed', array(
            'tenant_name' => $tenant['first_name'] . ' ' . $tenant['last_name'],
            'organization_name' => $org['name'] ?? '',
            'organization_phone' => $org['contact_phone'] ?? '',
            'amount' => $amount,
            'payment_date' => date('F j, Y'),
            'failure_reason' => $reason,
            'retry_url' => home_url('/rental-gates/tenant/payments'),
            'preheader' => __('Your payment could not be processed', 'rental-gates'),
        ));
    }
    
    /**
     * Send maintenance survey
     */
    public static function send_maintenance_survey($work_order_id) {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        
        $work_order = $wpdb->get_row($wpdb->prepare(
            "SELECT w.*, t.email, t.first_name, o.name as org_name
             FROM {$tables['work_orders']} w
             LEFT JOIN {$tables['tenants']} t ON w.tenant_id = t.id
             LEFT JOIN {$tables['organizations']} o ON w.organization_id = o.id
             WHERE w.id = %d",
            $work_order_id
        ), ARRAY_A);
        
        if (!$work_order || empty($work_order['email'])) return false;
        
        return self::send($work_order['email'], 'maintenance_survey', array(
            'tenant_name' => $work_order['first_name'],
            'organization_name' => $work_order['org_name'],
            'work_order_id' => $work_order_id,
            'work_order_title' => $work_order['title'],
            'completed_date' => date('F j, Y'),
            'survey_url' => home_url('/rental-gates/tenant/maintenance/' . $work_order_id . '/feedback'),
            'preheader' => __('How did we do? Rate your maintenance experience', 'rental-gates'),
        ));
    }
    
    /**
     * Send lead follow-up
     */
    public static function send_lead_followup($lead_id, $message = '') {
        $lead = Rental_Gates_Lead::get($lead_id);
        if (!$lead || empty($lead['email'])) return false;
        
        $org = Rental_Gates_Organization::get($lead['organization_id']);
        
        // Get property name if there's an interest
        $property_name = '';
        if (!empty($lead['interests'])) {
            $interest = $lead['interests'][0];
            if (!empty($interest['unit_id'])) {
                $unit = Rental_Gates_Unit::get($interest['unit_id']);
                $property_name = $unit ? $unit['name'] : '';
            } elseif (!empty($interest['building_id'])) {
                $building = Rental_Gates_Building::get($interest['building_id']);
                $property_name = $building ? $building['name'] : '';
            }
        }
        
        return self::send($lead['email'], 'lead_followup', array(
            'lead_name' => $lead['name'],
            'organization_name' => $org['name'] ?? '',
            'organization_phone' => $org['contact_phone'] ?? '',
            'organization_email' => $org['contact_email'] ?? '',
            'property_name' => $property_name,
            'message' => $message,
            'action_url' => home_url('/rental-gates/listings'),
            'preheader' => __('Following up on your interest', 'rental-gates'),
        ));
    }
    
    /**
     * Send subscription confirmation email
     */
    public static function send_subscription_confirmation($org_id, $subscription_data, $plan_data) {
        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        
        // Get organization details
        $org = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['organizations']} WHERE id = %d",
            $org_id
        ), ARRAY_A);
        
        if (!$org) {
            error_log('Rental Gates Email: Organization not found for org_id: ' . $org_id);
            return false;
        }
        
        // Get user email - try current user first, then owner, then organization contact email
        $user_email = '';
        $user_name = '';
        
        // Try current logged-in user
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            $user_email = $current_user->user_email;
            $user_name = $current_user->display_name;
        }
        
        // Fallback to owner if current user email not available
        if (empty($user_email) && !empty($org['owner_id'])) {
            $owner = get_user_by('ID', $org['owner_id']);
            if ($owner) {
                $user_email = $owner->user_email;
                $user_name = $owner->display_name;
            }
        }
        
        // Final fallback to organization contact email
        if (empty($user_email) && !empty($org['contact_email'])) {
            $user_email = $org['contact_email'];
            $user_name = $org['name'];
        }
        
        if (empty($user_email)) {
            error_log('Rental Gates Email: No email found for org_id: ' . $org_id . ', owner_id: ' . ($org['owner_id'] ?? 'NULL'));
            return false;
        }
        
        // Format billing cycle
        $billing_cycle = $subscription_data['billing_cycle'] ?? 'monthly';
        
        // Format amount
        $amount = number_format(floatval($subscription_data['amount'] ?? 0), 2);
        
        // Format next billing date
        $next_billing_date = '';
        if (!empty($subscription_data['current_period_end'])) {
            $next_billing_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($subscription_data['current_period_end']));
        }
        
        // Get subscription ID (Stripe subscription ID or local ID)
        $subscription_id = $subscription_data['stripe_subscription_id'] ?? '';
        if (empty($subscription_id) && !empty($subscription_data['id'])) {
            $subscription_id = 'RG-' . $subscription_data['id'];
        }
        
        $result = self::send($user_email, 'subscription_confirmed', array(
            'user_name' => $user_name ?: $org['name'],
            'organization_name' => $org['name'],
            'organization_phone' => $org['contact_phone'] ?? '',
            'organization_email' => $org['contact_email'] ?? '',
            'plan_name' => $plan_data['name'] ?? __('Unknown Plan', 'rental-gates'),
            'billing_cycle' => $billing_cycle,
            'amount' => $amount,
            'next_billing_date' => $next_billing_date,
            'subscription_id' => $subscription_id,
            'dashboard_url' => home_url('/rental-gates/dashboard/billing'),
            'preheader' => sprintf(__('Your %s subscription is now active!', 'rental-gates'), $plan_data['name'] ?? __('subscription', 'rental-gates')),
        ));
        
        if (is_wp_error($result)) {
            error_log('Rental Gates Email: Failed to send subscription confirmation: ' . $result->get_error_message());
        } elseif ($result === false) {
            error_log('Rental Gates Email: Subscription confirmation email returned false for: ' . $user_email);
        } else {
            error_log('Rental Gates Email: Subscription confirmation email sent successfully to: ' . $user_email);
        }
        
        return $result;
    }
}
