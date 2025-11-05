/**
 * Frontend JavaScript for Contact Form HubSpot
 *
 * @package ContactFormHubSpot
 */

(function($) {
    'use strict';

    // Handle form submission
    $('.contact-form-hubspot').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitButton = $form.find('.contact-form-submit');
        var $messages = $form.find('.contact-form-messages');

        // Clear previous messages and errors
        $messages.hide().removeClass('success error').html('');
        $form.find('.contact-form-error').removeClass('visible').html('');

        // Get form data
        var formData = {
            first_name: $form.find('input[name="first_name"]').val(),
            last_name: $form.find('input[name="last_name"]').val(),
            email: $form.find('input[name="email"]').val(),
            subject: $form.find('input[name="subject"]').val(),
            message: $form.find('textarea[name="message"]').val(),
            website: $form.find('input[name="website"]').val(), // Honeypot
            form_timestamp: $form.find('input[name="form_timestamp"]').val(),
            _wpnonce: $form.find('input[name="_wpnonce"]').val()
        };

        // Disable submit button
        $submitButton.prop('disabled', true).addClass('loading');

        // Send AJAX request
        $.ajax({
            url: contactFormHubSpot.ajaxUrl,
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            headers: {
                'X-WP-Nonce': contactFormHubSpot.nonce
            },
            success: function(response) {
                if (response.success || (response.data && response.data.status === 200)) {
                    $messages
                        .addClass('success')
                        .html(response.message || (response.data && response.data.message) || contactFormHubSpot.messages.success)
                        .show();
                    
                    // Reset form
                    $form[0].reset();
                } else {
                    var errorMessage = response.message || contactFormHubSpot.messages.error;
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    
                    $messages
                        .addClass('error')
                        .html(errorMessage)
                        .show();

                    // Show field errors if any
                    if (response.errors) {
                        $.each(response.errors, function(field, message) {
                            $form.find('#' + $form.attr('id') + '-' + field + '-error')
                                .addClass('visible')
                                .html(message);
                        });
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Contact form error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                var errorMessage = contactFormHubSpot.messages.error;
                
                // Try to parse error response
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        if (errorData.message) {
                            errorMessage = errorData.message;
                        }
                    } catch (e) {
                        // Keep default error message
                    }
                }
                
                $messages
                    .addClass('error')
                    .html(errorMessage)
                    .show();
            },
            complete: function() {
                // Re-enable submit button
                $submitButton.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // Real-time validation
    $('.contact-form-hubspot input[required], .contact-form-hubspot textarea[required]').on('blur', function() {
        var $field = $(this);
        var $error = $field.closest('.contact-form-field').find('.contact-form-error');
        var value = $field.val().trim();

        if (!value) {
            $error.addClass('visible').html(contactFormHubSpot.messages.validation.required);
        } else if ($field.attr('type') === 'email' && !isValidEmail(value)) {
            $error.addClass('visible').html(contactFormHubSpot.messages.validation.email);
        } else {
            $error.removeClass('visible').html('');
        }
    });

    // Email validation helper
    function isValidEmail(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

})(jQuery);