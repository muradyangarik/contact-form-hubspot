<?php
/**
 * Gutenberg block class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gutenberg block class for contact form
 */
class ContactFormHubSpot_Gutenberg_Block {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_Gutenberg_Block
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_Gutenberg_Block
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
        // Ensure block registration happens even if this class is instantiated during the 'init' action
        if (did_action('init')) {
            $this->register_block();
        } else {
            add_action('init', array($this, 'register_block'));
        }
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        // Editor assets are provided via block.json (editorScript/editorStyle)
    }
    
    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Prefer metadata registration to keep editor/frontend in sync
        $block_dir = CONTACT_FORM_HUBSPOT_PLUGIN_DIR . 'blocks/contact-form';
        $result = register_block_type(
            $block_dir,
            array(
                'render_callback' => array($this, 'render_contact_form'),
            )
        );
        
        
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Register frontend assets
        wp_register_style(
            'contact-form-hubspot-frontend',
            CONTACT_FORM_HUBSPOT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CONTACT_FORM_HUBSPOT_VERSION
        );
        
        wp_register_script(
            'contact-form-hubspot-frontend',
            CONTACT_FORM_HUBSPOT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            CONTACT_FORM_HUBSPOT_VERSION,
            true
        );
        
        // Only enqueue if the block is present on the page
        if (has_block('contact-form-hubspot/contact-form')) {
            wp_enqueue_style('contact-form-hubspot-frontend');
            wp_enqueue_script('contact-form-hubspot-frontend');
            
            // Localize script with AJAX data
            wp_localize_script(
                'contact-form-hubspot-frontend',
                'contactFormHubSpot',
                array(
                    'ajaxUrl' => rest_url('company/v1/contact'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'messages' => array(
                        'sending' => __('Sending...', 'contact-form-hubspot'),
                        'success' => __('Thank you for your message. We will get back to you soon!', 'contact-form-hubspot'),
                        'error' => __('There was an error sending your message. Please try again.', 'contact-form-hubspot'),
                        'validation' => array(
                            'required' => __('This field is required.', 'contact-form-hubspot'),
                            'email' => __('Please enter a valid email address.', 'contact-form-hubspot'),
                        ),
                    ),
                )
            );
        }
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() { /* handled by block.json */ }
    
    /**
     * Render contact form block
     *
     * @param array $attributes Block attributes
     * @param string $content Block content
     * @return string Rendered HTML
     */
    public function render_contact_form($attributes, $content = '') {
        
        
        // Parse attributes with defaults
        $attributes = wp_parse_args($attributes, array(
            'title' => __('Contact Us', 'contact-form-hubspot'),
            'description' => __('Send us a message and we will get back to you as soon as possible.', 'contact-form-hubspot'),
            'showTitle' => true,
            'showDescription' => true,
            'buttonText' => __('Send Message', 'contact-form-hubspot'),
            'successMessage' => __('Thank you for your message. We will get back to you soon!', 'contact-form-hubspot'),
            'errorMessage' => __('There was an error sending your message. Please try again.', 'contact-form-hubspot'),
        ));
        
        // Generate unique form ID
        $form_id = 'contact-form-' . wp_generate_uuid4();
        
        // Get current timestamp for time trap
        $antispam = ContactFormHubSpot_Antispam::get_instance();
        $form_timestamp = $antispam->generate_form_timestamp();
        
        // Enqueue frontend assets
        wp_enqueue_style('contact-form-hubspot-frontend');
        wp_enqueue_script('contact-form-hubspot-frontend');
        
        ob_start();
        ?>
        <div class="contact-form-hubspot-wrapper" data-form-id="<?php echo esc_attr($form_id); ?>">
            <?php if ($attributes['showTitle'] && !empty($attributes['title'])): ?>
                <h2 class="contact-form-title"><?php echo esc_html($attributes['title']); ?></h2>
            <?php endif; ?>
            
            <?php if ($attributes['showDescription'] && !empty($attributes['description'])): ?>
                <p class="contact-form-description"><?php echo esc_html($attributes['description']); ?></p>
            <?php endif; ?>
            
            <form id="<?php echo esc_attr($form_id); ?>" class="contact-form-hubspot" method="post">
                <div class="contact-form-messages" style="display: none;"></div>
                
                <div class="contact-form-fields">
                    <div class="contact-form-row">
                        <div class="contact-form-field contact-form-field-first-name">
                            <label for="<?php echo esc_attr($form_id); ?>-first-name">
                                <?php _e('First Name', 'contact-form-hubspot'); ?> <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="<?php echo esc_attr($form_id); ?>-first-name" 
                                name="first_name" 
                                required 
                                maxlength="255"
                                aria-describedby="<?php echo esc_attr($form_id); ?>-first-name-error"
                            >
                            <div class="contact-form-error" id="<?php echo esc_attr($form_id); ?>-first-name-error"></div>
                        </div>
                        
                        <div class="contact-form-field contact-form-field-last-name">
                            <label for="<?php echo esc_attr($form_id); ?>-last-name">
                                <?php _e('Last Name', 'contact-form-hubspot'); ?> <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="<?php echo esc_attr($form_id); ?>-last-name" 
                                name="last_name" 
                                required 
                                maxlength="255"
                                aria-describedby="<?php echo esc_attr($form_id); ?>-last-name-error"
                            >
                            <div class="contact-form-error" id="<?php echo esc_attr($form_id); ?>-last-name-error"></div>
                        </div>
                    </div>
                    
                    <div class="contact-form-field contact-form-field-email">
                        <label for="<?php echo esc_attr($form_id); ?>-email">
                            <?php _e('Email', 'contact-form-hubspot'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="<?php echo esc_attr($form_id); ?>-email" 
                            name="email" 
                            required 
                            maxlength="255"
                            aria-describedby="<?php echo esc_attr($form_id); ?>-email-error"
                        >
                        <div class="contact-form-error" id="<?php echo esc_attr($form_id); ?>-email-error"></div>
                    </div>
                    
                    <div class="contact-form-field contact-form-field-subject">
                        <label for="<?php echo esc_attr($form_id); ?>-subject">
                            <?php _e('Subject', 'contact-form-hubspot'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="<?php echo esc_attr($form_id); ?>-subject" 
                            name="subject" 
                            required 
                            maxlength="255"
                            aria-describedby="<?php echo esc_attr($form_id); ?>-subject-error"
                        >
                        <div class="contact-form-error" id="<?php echo esc_attr($form_id); ?>-subject-error"></div>
                    </div>
                    
                    <div class="contact-form-field contact-form-field-message">
                        <label for="<?php echo esc_attr($form_id); ?>-message">
                            <?php _e('Message', 'contact-form-hubspot'); ?> <span class="required">*</span>
                        </label>
                        <textarea 
                            id="<?php echo esc_attr($form_id); ?>-message" 
                            name="message" 
                            required 
                            rows="5"
                            aria-describedby="<?php echo esc_attr($form_id); ?>-message-error"
                        ></textarea>
                        <div class="contact-form-error" id="<?php echo esc_attr($form_id); ?>-message-error"></div>
                    </div>
                    
                    <!-- Honeypot field (hidden) -->
                    <div class="contact-form-field contact-form-field-website" style="display: none;">
                        <label for="<?php echo esc_attr($form_id); ?>-website">
                            <?php _e('Website', 'contact-form-hubspot'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="<?php echo esc_attr($form_id); ?>-website" 
                            name="website" 
                            tabindex="-1" 
                            autocomplete="off"
                        >
                    </div>
                    
                    <!-- Hidden timestamp field -->
                    <input type="hidden" name="form_timestamp" value="<?php echo esc_attr($form_timestamp); ?>">
                    
                    <!-- Nonce field -->
                    <?php wp_nonce_field('wp_rest', '_wpnonce'); ?>
                </div>
                
                <div class="contact-form-actions">
                    <button type="submit" class="contact-form-submit">
                        <span class="button-text"><?php echo esc_html($attributes['buttonText']); ?></span>
                        <span class="button-loading" style="display: none;">
                            <span class="spinner"></span>
                            <?php _e('Sending...', 'contact-form-hubspot'); ?>
                        </span>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}