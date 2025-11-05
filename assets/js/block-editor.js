/**
 * Block Editor JavaScript for Contact Form HubSpot
 *
 * @package ContactFormHubSpot
 */

(function() {
    'use strict';

    // Wait for WordPress to be ready
    if (typeof wp === 'undefined' || !wp.blocks) {
        console.error('WordPress blocks not available');
        return;
    }

    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var ToggleControl = wp.components.ToggleControl;
    var __ = wp.i18n.__;

    registerBlockType('contact-form-hubspot/contact-form', {
        title: 'Contact Form HubSpot',
        description: 'A contact form with HubSpot integration',
        category: 'widgets',
        icon: 'email-alt',
        keywords: ['contact', 'form', 'hubspot', 'email'],
        
        attributes: {
            title: {
                type: 'string',
                default: 'Contact Us'
            },
            description: {
                type: 'string',
                default: 'Send us a message and we will get back to you as soon as possible.'
            },
            showTitle: {
                type: 'boolean',
                default: true
            },
            showDescription: {
                type: 'boolean',
                default: true
            },
            buttonText: {
                type: 'string',
                default: 'Send Message'
            },
            successMessage: {
                type: 'string',
                default: 'Thank you for your message. We will get back to you soon!'
            },
            errorMessage: {
                type: 'string',
                default: 'There was an error sending your message. Please try again.'
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            // Create inspector controls (sidebar settings)
            var inspectorControls = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: 'Form Settings', initialOpen: true },
                    el(ToggleControl, {
                        label: 'Show Title',
                        checked: attributes.showTitle,
                        onChange: function(value) {
                            setAttributes({ showTitle: value });
                        }
                    }),
                    attributes.showTitle && el(TextControl, {
                        label: 'Title',
                        value: attributes.title,
                        onChange: function(value) {
                            setAttributes({ title: value });
                        }
                    }),
                    el(ToggleControl, {
                        label: 'Show Description',
                        checked: attributes.showDescription,
                        onChange: function(value) {
                            setAttributes({ showDescription: value });
                        }
                    }),
                    attributes.showDescription && el(TextareaControl, {
                        label: 'Description',
                        value: attributes.description,
                        onChange: function(value) {
                            setAttributes({ description: value });
                        }
                    }),
                    el(TextControl, {
                        label: 'Button Text',
                        value: attributes.buttonText,
                        onChange: function(value) {
                            setAttributes({ buttonText: value });
                        }
                    })
                ),
                el(
                    PanelBody,
                    { title: 'Messages', initialOpen: false },
                    el(TextareaControl, {
                        label: 'Success Message',
                        value: attributes.successMessage,
                        onChange: function(value) {
                            setAttributes({ successMessage: value });
                        }
                    }),
                    el(TextareaControl, {
                        label: 'Error Message',
                        value: attributes.errorMessage,
                        onChange: function(value) {
                            setAttributes({ errorMessage: value });
                        }
                    })
                )
            );

            // Create simple preview in editor
            var previewContent = el(
                'div',
                { 
                    className: 'contact-form-hubspot-editor-preview',
                    style: {
                        padding: '20px',
                        border: '2px dashed #0073aa',
                        borderRadius: '4px',
                        backgroundColor: '#f0f8ff',
                        textAlign: 'center'
                    }
                },
                el('div', { style: { fontSize: '24px', marginBottom: '10px' } }, '📧'),
                el('h3', { style: { margin: '10px 0' } }, 'Contact Form HubSpot'),
                el('p', { style: { margin: '5px 0', color: '#666' } }, 
                    'Form will be displayed here on the frontend'
                ),
                attributes.showTitle && el('p', { style: { margin: '5px 0', fontSize: '12px' } }, 
                    'Title: ' + attributes.title
                ),
                attributes.showDescription && el('p', { style: { margin: '5px 0', fontSize: '12px' } }, 
                    'Description: ' + attributes.description
                )
            );

            // Wrap with block props so the editor can calculate selection/drag areas correctly
            var blockProps = useBlockProps();

            return el('div', blockProps, inspectorControls, previewContent);
        },

        save: function() {
            // Server-side rendering
            return null;
        }
    });

    

})();