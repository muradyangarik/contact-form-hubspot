/**
 * Admin JavaScript for Contact Form HubSpot
 *
 * @package ContactFormHubSpot
 */

(function($) {
    'use strict';

    // Admin Handler
    class AdminHandler {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initTabs();
        }

        bindEvents() {
            // Test HubSpot connection
            $(document).on('click', '#test-connection', this.testConnection.bind(this));
            
            // Clear rate limits
            $(document).on('click', '#clear-rate-limits', this.clearRateLimits.bind(this));
            
            // View log details
            $(document).on('click', '.view-log-details', this.viewLogDetails.bind(this));
        }

        initTabs() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                const target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target panel
                $('.tab-panel').removeClass('active');
                $(target).addClass('active');
            });
        }

        testConnection() {
            const $button = $('#test-connection');
            const originalText = $button.text();
            
            // Remove any existing notices
            $('.notice').remove();
            
            $button.prop('disabled', true).text(contactFormHubSpotAdmin.messages.testing);
            
            $.ajax({
                url: contactFormHubSpotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'contact_form_hubspot_test_connection',
                    nonce: contactFormHubSpotAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const message = response.data && response.data.message 
                            ? response.data.message 
                            : contactFormHubSpotAdmin.messages.success;
                        this.showNotice('success', message);
                    } else {
                        const message = response.data && response.data.message 
                            ? response.data.message 
                            : contactFormHubSpotAdmin.messages.error;
                        this.showNotice('error', message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Test connection error:', error);
                    this.showNotice('error', contactFormHubSpotAdmin.messages.error);
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }

        clearRateLimits() {
            if (!confirm('Are you sure you want to clear all rate limits? This will allow all blocked IP addresses to submit forms again.')) {
                return;
            }
            
            const $button = $('#clear-rate-limits');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(contactFormHubSpotAdmin.messages.clearing);
            
            $.ajax({
                url: contactFormHubSpotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'contact_form_hubspot_clear_rate_limits',
                    nonce: contactFormHubSpotAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', response.data.message || contactFormHubSpotAdmin.messages.cleared);
                    } else {
                        this.showNotice('error', response.data || 'Failed to clear rate limits.');
                    }
                },
                error: () => {
                    this.showNotice('error', 'Failed to clear rate limits.');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }

        viewLogDetails(logId) {
            // This would open a modal or redirect to a detailed view
            // For now, we'll just show an alert
            alert('Log details for ID: ' + logId + '\n\nThis would show detailed information about the form submission.');
        }

        showNotice(type, message) {
            // Remove any existing notices first
            $('.notice.contact-form-notice').remove();
            
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $(`
                <div class="notice ${noticeClass} is-dismissible contact-form-notice" style="display:none;">
                    <p><strong>${message}</strong></p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            // Add notice after the h1 title
            $('.wrap h1').after($notice);
            
            // Fade in the notice
            $notice.fadeIn(300);
            
            // Scroll to the notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 300);
            
            // Auto-dismiss after 8 seconds
            setTimeout(() => {
                $notice.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 8000);
            
            // Handle manual dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new AdminHandler();
    });

})(jQuery);