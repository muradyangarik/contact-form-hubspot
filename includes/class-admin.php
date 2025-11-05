<?php
/**
 * Admin interface class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for plugin settings and management
 */
class ContactFormHubSpot_Admin {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_Admin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_contact_form_hubspot_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_contact_form_hubspot_clear_rate_limits', array($this, 'ajax_clear_rate_limits'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Contact Form HubSpot', 'contact-form-hubspot'),
            __('Contact Form HubSpot', 'contact-form-hubspot'),
            'manage_options',
            'contact-form-hubspot',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // HubSpot API Token
        register_setting(
            'contact_form_hubspot_settings',
            'contact_form_hubspot_api_token',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        
        // Email Subject Template
        register_setting(
            'contact_form_hubspot_settings',
            'contact_form_hubspot_email_subject',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => __('New Contact Form Submission', 'contact-form-hubspot'),
            )
        );
        
        // Email Body Template
        register_setting(
            'contact_form_hubspot_settings',
            'contact_form_hubspot_email_body',
            array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default' => $this->get_default_email_template(),
            )
        );
        
        // Enable DNS Check
        register_setting(
            'contact_form_hubspot_settings',
            'contact_form_hubspot_enable_dns_check',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true,
            )
        );
        
        // Rate Limit (submissions per hour)
        register_setting(
            'contact_form_hubspot_settings',
            'contact_form_hubspot_rate_limit',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 3,
            )
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_contact-form-hubspot') {
            return;
        }
        
        wp_enqueue_script(
            'contact-form-hubspot-admin',
            CONTACT_FORM_HUBSPOT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CONTACT_FORM_HUBSPOT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'contact-form-hubspot-admin',
            CONTACT_FORM_HUBSPOT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CONTACT_FORM_HUBSPOT_VERSION
        );
        
        wp_localize_script(
            'contact-form-hubspot-admin',
            'contactFormHubSpotAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('contact_form_hubspot_admin'),
                'messages' => array(
                    'testing' => __('Testing connection...', 'contact-form-hubspot'),
                    'success' => __('Connection successful!', 'contact-form-hubspot'),
                    'error' => __('Connection failed. Please check your API token.', 'contact-form-hubspot'),
                    'clearing' => __('Clearing rate limits...', 'contact-form-hubspot'),
                    'cleared' => __('Rate limits cleared successfully.', 'contact-form-hubspot'),
                ),
            )
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'contact-form-hubspot'));
        }
        
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'contact_form_hubspot_settings')) {
            $this->save_settings();
        }
        
        // Get current settings
        $api_token = get_option('contact_form_hubspot_api_token', '');
        $email_subject = get_option('contact_form_hubspot_email_subject', __('New Contact Form Submission', 'contact-form-hubspot'));
        $email_body = get_option('contact_form_hubspot_email_body', $this->get_default_email_template());
        $enable_dns_check = get_option('contact_form_hubspot_enable_dns_check', true);
        $rate_limit = get_option('contact_form_hubspot_rate_limit', 3);
        
        // Get statistics
        $stats = $this->get_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Contact Form HubSpot Settings', 'contact-form-hubspot'); ?></h1>
            
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully.', 'contact-form-hubspot'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="contact-form-hubspot-admin">
                <div class="admin-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#settings" class="nav-tab nav-tab-active"><?php _e('Settings', 'contact-form-hubspot'); ?></a>
                        <a href="#statistics" class="nav-tab"><?php _e('Statistics', 'contact-form-hubspot'); ?></a>
                        <a href="#tools" class="nav-tab"><?php _e('Tools', 'contact-form-hubspot'); ?></a>
                    </nav>
                    
                    <div class="tab-content">
                        <!-- Settings Tab -->
                        <div id="settings" class="tab-panel active">
                            <form method="post" action="">
                                <?php wp_nonce_field('contact_form_hubspot_settings'); ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="api_token"><?php _e('HubSpot API Token', 'contact-form-hubspot'); ?></label>
                                        </th>
                                        <td>
                                            <input 
                                                type="password" 
                                                id="api_token" 
                                                name="contact_form_hubspot_api_token" 
                                                value="<?php echo esc_attr($api_token); ?>" 
                                                class="regular-text"
                                                placeholder="<?php _e('Enter your HubSpot Private App Token', 'contact-form-hubspot'); ?>"
                                            >
                                            <button type="button" id="test-connection" class="button">
                                                <?php _e('Test Connection', 'contact-form-hubspot'); ?>
                                            </button>
                                            <p class="description">
                                                <?php _e('Get your Private App Token from HubSpot Developer Settings.', 'contact-form-hubspot'); ?>
                                                <a href="https://developers.hubspot.com/docs/api/private-apps" target="_blank">
                                                    <?php _e('Learn more', 'contact-form-hubspot'); ?>
                                                </a>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="email_subject"><?php _e('Email Subject Template', 'contact-form-hubspot'); ?></label>
                                        </th>
                                        <td>
                                            <input 
                                                type="text" 
                                                id="email_subject" 
                                                name="contact_form_hubspot_email_subject" 
                                                value="<?php echo esc_attr($email_subject); ?>" 
                                                class="regular-text"
                                            >
                                            <p class="description">
                                                <?php _e('Subject line for admin notification emails. Use placeholders: {{first_name}}, {{last_name}}, {{email}}, {{subject}}', 'contact-form-hubspot'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="email_body"><?php _e('Email Body Template', 'contact-form-hubspot'); ?></label>
                                        </th>
                                        <td>
                                            <textarea 
                                                id="email_body" 
                                                name="contact_form_hubspot_email_body" 
                                                rows="10" 
                                                class="large-text"
                                            ><?php echo esc_textarea($email_body); ?></textarea>
                                            <p class="description">
                                                <?php _e('HTML template for admin notification emails. Use placeholders: {{first_name}}, {{last_name}}, {{email}}, {{subject}}, {{message}}, {{hubspot_status}}, {{hubspot_contact_id}}, {{hubspot_error}}', 'contact-form-hubspot'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="enable_dns_check"><?php _e('Enable DNS Check', 'contact-form-hubspot'); ?></label>
                                        </th>
                                        <td>
                                            <input 
                                                type="checkbox" 
                                                id="enable_dns_check" 
                                                name="contact_form_hubspot_enable_dns_check" 
                                                value="1" 
                                                <?php checked($enable_dns_check); ?>
                                            >
                                            <label for="enable_dns_check">
                                                <?php _e('Check DNS MX/A records for email validation', 'contact-form-hubspot'); ?>
                                            </label>
                                            <p class="description">
                                                <?php _e('This adds an extra layer of email validation but may slow down form submissions.', 'contact-form-hubspot'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">
                                            <label for="rate_limit"><?php _e('Rate Limit', 'contact-form-hubspot'); ?></label>
                                        </th>
                                        <td>
                                            <input 
                                                type="number" 
                                                id="rate_limit" 
                                                name="contact_form_hubspot_rate_limit" 
                                                value="<?php echo esc_attr($rate_limit); ?>" 
                                                min="1" 
                                                max="100" 
                                                class="small-text"
                                            >
                                            <label for="rate_limit">
                                                <?php _e('submissions per hour per IP address', 'contact-form-hubspot'); ?>
                                            </label>
                                            <p class="description">
                                                <?php _e('Maximum number of form submissions allowed per IP address per hour.', 'contact-form-hubspot'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <?php submit_button(); ?>
                            </form>
                        </div>
                        
                        <!-- Statistics Tab -->
                        <div id="statistics" class="tab-panel">
                            <h3><?php _e('Form Submission Statistics', 'contact-form-hubspot'); ?></h3>
                            
                            <div class="stats-grid">
                                <div class="stat-box">
                                    <h4><?php _e('Total Submissions', 'contact-form-hubspot'); ?></h4>
                                    <div class="stat-number"><?php echo esc_html($stats['total_submissions']); ?></div>
                                </div>
                                
                                <div class="stat-box">
                                    <h4><?php _e('Successful Submissions', 'contact-form-hubspot'); ?></h4>
                                    <div class="stat-number success"><?php echo esc_html($stats['successful_submissions']); ?></div>
                                </div>
                                
                                <?php /* Failed Submissions box intentionally removed */ ?>
                                
                                <div class="stat-box">
                                    <h4><?php _e('Success Rate', 'contact-form-hubspot'); ?></h4>
                                    <div class="stat-number"><?php echo esc_html($stats['success_rate']); ?>%</div>
                                </div>
                            </div>
                            
                            <h4><?php _e('All Submissions', 'contact-form-hubspot'); ?></h4>
                            <div class="recent-submissions">
                                <?php if (!empty($stats['recent_submissions'])): ?>
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Date', 'contact-form-hubspot'); ?></th>
                                                <th><?php _e('Email', 'contact-form-hubspot'); ?></th>
                                                <th><?php _e('Status', 'contact-form-hubspot'); ?></th>
                                                <th><?php _e('HubSpot ID', 'contact-form-hubspot'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['recent_submissions'] as $submission): ?>
                                                <tr>
                                                    <td><?php echo esc_html($submission['timestamp']); ?></td>
                                                    <td><?php echo esc_html($submission['email']); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo esc_attr($submission['result']); ?>">
                                                            <?php echo esc_html(ucfirst($submission['result'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo esc_html($submission['hubspot_id'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p><?php _e('No recent submissions found.', 'contact-form-hubspot'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Logs Tab removed by request -->
                        
                        <!-- Tools Tab -->
                        <div id="tools" class="tab-panel">
                            <h3><?php _e('Tools', 'contact-form-hubspot'); ?></h3>
                            
                            <div class="tools-section">
                                <h4><?php _e('Rate Limiting', 'contact-form-hubspot'); ?></h4>
                                <p><?php _e('Clear all rate limits to allow blocked IP addresses to submit forms again.', 'contact-form-hubspot'); ?></p>
                                <button type="button" id="clear-rate-limits" class="button">
                                    <?php _e('Clear All Rate Limits', 'contact-form-hubspot'); ?>
                                </button>
                            </div>
                            
                            <div class="tools-section">
                                <h4><?php _e('Database', 'contact-form-hubspot'); ?></h4>
                                <p><?php _e('Logs are automatically rotated after 30 days. You can manually trigger log rotation.', 'contact-form-hubspot'); ?></p>
                                <button type="button" id="rotate-logs" class="button">
                                    <?php _e('Rotate Logs Now', 'contact-form-hubspot'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings
        if (isset($_POST['contact_form_hubspot_api_token'])) {
            update_option('contact_form_hubspot_api_token', sanitize_text_field($_POST['contact_form_hubspot_api_token']));
        }
        
        if (isset($_POST['contact_form_hubspot_email_subject'])) {
            update_option('contact_form_hubspot_email_subject', sanitize_text_field($_POST['contact_form_hubspot_email_subject']));
        }
        
        if (isset($_POST['contact_form_hubspot_email_body'])) {
            update_option('contact_form_hubspot_email_body', wp_kses_post($_POST['contact_form_hubspot_email_body']));
        }
        
        if (isset($_POST['contact_form_hubspot_enable_dns_check'])) {
            update_option('contact_form_hubspot_enable_dns_check', true);
        } else {
            update_option('contact_form_hubspot_enable_dns_check', false);
        }
        
        if (isset($_POST['contact_form_hubspot_rate_limit'])) {
            $rate_limit = absint($_POST['contact_form_hubspot_rate_limit']);
            if ($rate_limit >= 1 && $rate_limit <= 100) {
                update_option('contact_form_hubspot_rate_limit', $rate_limit);
            }
        }
        
        // Redirect to prevent resubmission
        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('options-general.php?page=contact-form-hubspot')));
        exit;
    }
    
    /**
     * Get default email template
     *
     * @return string Default email template
     */
    private function get_default_email_template() {
        return '<h2>' . __('New Contact Form Submission', 'contact-form-hubspot') . '</h2>
        
<p><strong>' . __('Name:', 'contact-form-hubspot') . '</strong> {{first_name}} {{last_name}}</p>
<p><strong>' . __('Email:', 'contact-form-hubspot') . '</strong> {{email}}</p>
<p><strong>' . __('Subject:', 'contact-form-hubspot') . '</strong> {{subject}}</p>
<p><strong>' . __('Message:', 'contact-form-hubspot') . '</strong></p>
<p>{{message}}</p>

<hr>
<p><strong>' . __('HubSpot Status:', 'contact-form-hubspot') . '</strong> {{hubspot_status}}</p>
{{#if hubspot_contact_id}}<p><strong>' . __('HubSpot Contact ID:', 'contact-form-hubspot') . '</strong> {{hubspot_contact_id}}</p>{{/if}}
{{#if hubspot_error}}<p><strong>' . __('HubSpot Error:', 'contact-form-hubspot') . '</strong> {{hubspot_error}}</p>{{/if}}

<p><em>' . __('This message was sent from your WordPress contact form.', 'contact-form-hubspot') . '</em></p>';
    }
    
    /**
     * Get statistics
     *
     * @return array Statistics data
     */
    private function get_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        // Get total submissions
        $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Get successful submissions
        $successful_submissions = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE result = %s", 'success')
        );
        
        // Get failed submissions
        $failed_submissions = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE result = %s", 'failed')
        );
        
        // Calculate success rate
        $success_rate = $total_submissions > 0 ? round(($successful_submissions / $total_submissions) * 100, 1) : 0;
        
        // Get all submissions ordered by newest first
        $recent_submissions = $wpdb->get_results(
            "SELECT timestamp, email, result, hubspot_id FROM {$table_name} ORDER BY id DESC",
            ARRAY_A
        );
        
        return array(
            'total_submissions' => $total_submissions,
            'successful_submissions' => $successful_submissions,
            'failed_submissions' => $failed_submissions,
            'success_rate' => $success_rate,
            'recent_submissions' => $recent_submissions,
        );
    }
    
    /**
     * Display logs table
     */
    private function display_logs_table() {
        $database = ContactFormHubSpot_Database::get_instance();
        
        $page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
        $search = isset($_GET['log_search']) ? sanitize_text_field($_GET['log_search']) : '';
        
        $logs_data = $database->get_logs($page, 20, $search);
        
        ?>
        <div class="logs-controls">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="contact-form-hubspot">
                <input type="hidden" name="tab" value="logs">
                <input type="search" name="log_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search logs...', 'contact-form-hubspot'); ?>">
                <input type="submit" class="button" value="<?php _e('Search', 'contact-form-hubspot'); ?>">
                <?php if ($search): ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=contact-form-hubspot&tab=logs')); ?>" class="button">
                        <?php _e('Clear', 'contact-form-hubspot'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (!empty($logs_data['logs'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'contact-form-hubspot'); ?></th>
                        <th><?php _e('Email', 'contact-form-hubspot'); ?></th>
                        <th><?php _e('Status', 'contact-form-hubspot'); ?></th>
                        <th><?php _e('HubSpot ID', 'contact-form-hubspot'); ?></th>
                        <th><?php _e('IP Address', 'contact-form-hubspot'); ?></th>
                        <th><?php _e('Actions', 'contact-form-hubspot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs_data['logs'] as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['timestamp']); ?></td>
                            <td><?php echo esc_html($log['email']); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($log['result']); ?>">
                                    <?php echo esc_html(ucfirst($log['result'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['hubspot_id'] ?: '-'); ?></td>
                            <td><?php echo esc_html($log['user_ip'] ?: '-'); ?></td>
                            <td>
                                <button type="button" class="button view-log-details" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <?php _e('View Details', 'contact-form-hubspot'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($logs_data['pages'] > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('log_page', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $logs_data['pages'],
                            'current' => $page,
                        );
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p><?php _e('No logs found.', 'contact-form-hubspot'); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * AJAX handler for testing HubSpot connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('contact_form_hubspot_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'contact-form-hubspot'));
        }
        
        $hubspot_api = ContactFormHubSpot_HubSpot_API::get_instance();
        $result = $hubspot_api->test_connection();
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for clearing rate limits
     */
    public function ajax_clear_rate_limits() {
        check_ajax_referer('contact_form_hubspot_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'contact-form-hubspot'));
        }
        
        global $wpdb;
        
        // Delete all rate limit transients
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_contact_form_rate_limit_%' OR option_name LIKE '_transient_timeout_contact_form_rate_limit_%'"
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleared %d rate limit entries.', 'contact-form-hubspot'), $deleted),
        ));
    }
}



