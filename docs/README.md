# SORN Manager WordPress Plugin

A comprehensive WordPress plugin for managing System of Records Notices (SORNs) with AI-powered features and Federal Register integration.

## Features

- AI-powered SORN management using GPT Trainer API
- Federal Register integration
- FedRAMP system catalog integration
- Data source management (files, URLs, Q&A pairs)
- Secure WordPress admin interface
- Modern, responsive UI

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- MySQL 5.6 or higher
- GPT Trainer API access token

## Installation

1. Download the plugin zip file
2. Upload to your WordPress site through the plugins page
3. Activate the plugin
4. Go to Settings > SORN Manager to configure your API token

## Configuration

### API Token

1. Obtain your GPT Trainer API token
2. Navigate to WordPress admin > SORN Manager > Settings
3. Enter your API token in the designated field
4. Click "Save Settings"

### Plugin Settings

The plugin can be configured through the WordPress admin interface:

- **API Token**: Your GPT Trainer API authentication token
- **Test Mode**: Enable/disable test mode for development
- **Debug Logging**: Enable/disable detailed logging

## Usage

### Managing Data Sources

1. Navigate to SORN Manager in the WordPress admin menu
2. Click "Create Data Source" to add new training data
3. Choose the source type:
   - File Upload: Upload documents (PDF, TXT, JSON)
   - URL: Import data from a web URL
   - Q&A Pairs: Create custom question-answer pairs

### Working with SORNs

[Documentation to be added]

## Security

The plugin implements several security measures:

- WordPress nonce verification for all forms
- Capability checks for administrative actions
- Data sanitization and validation
- Secure API token storage
- XSS prevention through proper escaping
- CSRF protection

## Development

### Directory Structure

```
piper-privacy-sorn/
├── admin/                     # Admin-specific files
│   ├── css/                  # Admin styles
│   ├── js/                   # Admin JavaScript
│   └── partials/             # Admin templates
├── includes/                 # Core plugin files
│   └── Services/             # Service classes
├── languages/               # Translation files
├── public/                  # Public-facing files
└── tests/                   # Test files
```

### Key Files

- `piper-privacy-sorn.php`: Main plugin file
- `includes/PiperPrivacySorn.php`: Core plugin class
- `includes/Services/GptTrainerApi.php`: API integration
- `admin/Admin.php`: Admin interface management

### Hooks and Filters

#### Actions

- `piper_privacy_sorn_init`: Fired when plugin initializes
- `piper_privacy_sorn_api_error`: Fired on API errors
- `piper_privacy_sorn_before_data_source_create`: Before creating a data source
- `piper_privacy_sorn_after_data_source_create`: After creating a data source

#### Filters

- `piper_privacy_sorn_api_headers`: Modify API request headers
- `piper_privacy_sorn_api_response`: Modify API response data
- `piper_privacy_sorn_data_source_types`: Modify available data source types

### Development Setup

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Set up WordPress development environment
4. Configure test environment:
   ```bash
   cp .env.example .env
   ```
5. Run tests:
   ```bash
   composer test
   ```

### Coding Standards

The project follows WordPress coding standards. Run checks with:

```bash
composer run-script phpcs
```

Fix coding standards violations with:

```bash
composer run-script phpcbf
```

## Testing

### Unit Tests

Run PHPUnit tests:

```bash
composer test
```

### Integration Tests

Run integration tests:

```bash
composer test-integration
```

## Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Verify API token is correct
   - Check server can reach API endpoint
   - Verify SSL certificates are valid

2. **Permission Errors**
   - Ensure proper WordPress capabilities are set
   - Check file permissions for uploads

3. **Data Source Creation Failed**
   - Verify file size is within limits
   - Check file type is supported
   - Ensure URL is accessible

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests
5. Submit a pull request

## License

Proprietary - All rights reserved

## Support

For support inquiries:
- Submit issues on GitHub
- Contact support team
- Check documentation
