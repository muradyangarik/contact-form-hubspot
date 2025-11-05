# Contact Form HubSpot WordPress Plugin

A comprehensive WordPress contact form plugin with Gutenberg block, REST API, HubSpot integration, and advanced security features.

## Features

-  **Gutenberg Block**: Easy-to-use contact form block for the WordPress editor
-  **Advanced Security**: Honeypot, time trap, rate limiting, and CSRF protection
-  **Email Validation**: RFC-compliant email validation with DNS checks
-  **HubSpot Integration**: Direct contact creation in HubSpot CRM
-  **Comprehensive Logging**: Custom database table with 30-day rotation
-  **WP-CLI Commands**: Command-line tools for testing and management
-  **Internationalization**: Full i18n support with .pot file
- **Unit Tests**: Comprehensive test suite with WordPress test environment
- **CI/CD Pipeline**: GitHub Actions workflow for automated testing
-  **Docker Support**: Complete Docker development environment

## Installation

### Manual Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/contact-form-hubspot/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your HubSpot API token in Settings > Contact Form HubSpot

### Docker Installation

1. Clone the repository
2. Run `docker-compose up -d`
3. Access WordPress at `http://localhost:8080`
4. Access phpMyAdmin at `http://localhost:8081`
5. Access MailHog at `http://localhost:8025`

## Configuration

### HubSpot Setup

1. Go to HubSpot Developer Settings
2. Create a Private App
3. Generate an API token with CRM permissions
4. Enter the token in WordPress Settings > Contact Form HubSpot

### Form Configuration

1. Add the Contact Form block to any page or post
2. Customize the form title, description, and button text
3. Configure success and error messages
4. The form includes all necessary security measures automatically

## Usage

### Gutenberg Block

1. In the WordPress editor, click the "+" button
2. Search for "Contact Form HubSpot"
3. Add the block to your page
4. Customize the form settings in the block sidebar
5. Publish your page

### WP-CLI Commands

```bash
# Test HubSpot connection
wp contact-form test-hubspot

# Show submission statistics
wp contact-form stats

# View submission logs
wp contact-form logs

# Clear rate limits
wp contact-form clear-rate-limits

# Rotate logs (delete old entries)
wp contact-form rotate-logs
```


### Security Measures

1. **Honeypot Field**: Hidden field that bots fill but humans don't
2. **Time Trap**: Prevents submissions faster than 3 seconds or older than 1 hour
3. **Rate Limiting**: Maximum 3 submissions per hour per IP address
4. **CSRF Protection**: WordPress nonce verification
5. **Input Sanitization**: All inputs sanitized and validated
6. **SQL Injection Prevention**: Prepared statements for all database queries

## Development

### Prerequisites

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Node.js 16 or higher (for asset building)
- Composer (for dependencies)

### Setup Development Environment

1. Clone the repository
2. Run `composer install`
3. Run `npm install`
4. Set up WordPress test environment
5. Run `npm run build` to build assets

### Running Tests

```bash
# Install WordPress test environment
bash bin/install-wp-tests.sh wordpress_test root root localhost latest

# Run PHPUnit tests
./vendor/bin/phpunit

# Run PHP_CodeSniffer
./vendor/bin/phpcs --standard=phpcs.xml
```

### Code Standards

This plugin follows WordPress Coding Standards:

- WordPress-Core
- WordPress-Extra
- WordPress-Docs

Run `./vendor/bin/phpcs` to check code standards compliance.

## API Reference

### REST API Endpoint

**POST** `/wp-json/company/v1/contact`

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| first_name | string | Yes | User's first name |
| last_name | string | Yes | User's last name |
| email | string | Yes | User's email address |
| subject | string | Yes | Message subject |
| message | string | Yes | Message content |
| website | string | No | Honeypot field (should be empty) |
| form_timestamp | integer | Yes | Form load timestamp |
| _wpnonce | string | Yes | WordPress nonce |

#### Response

```json
{
  "success": true,
  "message": "Thank you for your message. We will get back to you soon!",
  "contact_id": "12345"
}
```


This plugin is licensed under the GPL v2 or later.

## Support

For support, please open an issue on GitHub or contact the plugin author.

## Changelog

### 1.0.0
- Initial release
- Gutenberg block implementation
- REST API endpoint
- HubSpot integration
- Security measures
- Admin interface
- WP-CLI commands
- Unit tests
- CI/CD pipeline
- Docker support
- Internationalization



