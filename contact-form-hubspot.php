<?php
/**
 * Plugin Name: Contact Form HubSpot
 * Description: A WordPress contact form plugin with Gutenberg block, REST API, and HubSpot integration.
 * Version: 1.0.0
 * Author: Garik Muradyan
 * Author URI: mailto:muradyangarik@gmail.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: contact-form-hubspot
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CONTACT_FORM_HUBSPOT_VERSION', '1.0.0');
define('CONTACT_FORM_HUBSPOT_PLUGIN_FILE', __FILE__);
define('CONTACT_FORM_HUBSPOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CONTACT_FORM_HUBSPOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CONTACT_FORM_HUBSPOT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class ContactFormHubSpot {
    
    /**
     * Single instance of the plugin
     *
     * @var ContactFormHubSpot
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot
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
        $this->load_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-database.php';
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-hubspot-api.php';
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-email-validator.php';
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-antispam.php';
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-admin.php';
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-wp-cli.php';
        require_once CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'includes/class-gutenberg-block.php';
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        ContactFormHubSpot_Database::get_instance();
        ContactFormHubSpot_REST_API::get_instance();
        ContactFormHubSpot_HubSpot_API::get_instance();
        ContactFormHubSpot_Email_Validator::get_instance();
        ContactFormHubSpot_Antispam::get_instance();
        ContactFormHubSpot_Admin::get_instance();
        ContactFormHubSpot_Gutenberg_Block::get_instance();
        
        // Register shortcode
        add_shortcode('contact_form_hubspot', array($this, 'render_shortcode'));
        
        // Initialize WP-CLI command if in CLI context
        if (defined('WP_CLI') && WP_CLI) {
            ContactFormHubSpot_WP_CLI::get_instance();
        }
    }
    
    /**
     * Load text domain for internationalization
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'contact-form-hubspot',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Render shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Contact Us', 'contact-form-hubspot'),
            'description' => __('Send us a message and we will get back to you as soon as possible.', 'contact-form-hubspot'),
            'show_title' => 'true',
            'show_description' => 'true',
            'button_text' => __('Send Message', 'contact-form-hubspot'),
        ), $atts, 'contact_form_hubspot');
        
        // Convert string booleans to actual booleans
        $atts['show_title'] = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_description'] = filter_var($atts['show_description'], FILTER_VALIDATE_BOOLEAN);
        
        // Convert to block attributes format
        $attributes = array(
            'title' => $atts['title'],
            'description' => $atts['description'],
            'showTitle' => $atts['show_title'],
            'showDescription' => $atts['show_description'],
            'buttonText' => $atts['button_text'],
        );
        
        // Use the block's render method
        $block = ContactFormHubSpot_Gutenberg_Block::get_instance();
        return $block->render_contact_form($attributes);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database table
        ContactFormHubSpot_Database::get_instance();
        ContactFormHubSpot_Database::create_table();
        
        // Schedule log rotation cron job
        if (!wp_next_scheduled('contact_form_hubspot_rotate_logs')) {
            wp_schedule_event(time(), 'daily', 'contact_form_hubspot_rotate_logs');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron job
        wp_clear_scheduled_hook('contact_form_hubspot_rotate_logs');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
ContactFormHubSpot::get_instance();