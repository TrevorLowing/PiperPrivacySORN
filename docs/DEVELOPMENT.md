# Development Guide

This guide provides detailed information for developers working on the SORN Manager WordPress plugin.

## Development Environment Setup

### Prerequisites

1. Local development environment:
   - PHP 7.4+
   - MySQL 5.6+
   - WordPress 5.0+
   - Composer
   - Node.js & npm (for frontend assets)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/piper-privacy-sorn.git
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install JavaScript dependencies:
   ```bash
   cd admin/js
   npm install
   ```

4. Configure environment:
   ```bash
   cp .env.example .env
   ```

## Code Organization

### Directory Structure

```
piper-privacy-sorn/
├── admin/                    # Admin interface files
│   ├── css/                 # Admin styles
│   │   └── piper-privacy-sorn-admin.css
│   ├── js/                  # Admin JavaScript
│   │   └── piper-privacy-sorn-admin.js
│   ├── partials/            # Admin templates
│   │   ├── piper-privacy-sorn-admin-display.php
│   │   └── piper-privacy-sorn-admin-settings.php
│   └── Admin.php            # Admin class
├── includes/                # Core plugin files
│   ├── Services/           # Service classes
│   │   └── GptTrainerApi.php
│   └── PiperPrivacySorn.php
├── languages/              # Translation files
├── public/                 # Public-facing files
├── tests/                  # Test files
└── piper-privacy-sorn.php  # Main plugin file
```

### Key Components

#### 1. Core Plugin Class (`PiperPrivacySorn.php`)

Manages plugin initialization and hooks:

```php
class PiperPrivacySorn {
    public function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
}
```

#### 2. Admin Class (`Admin.php`)

Handles admin interface and AJAX:

```php
class Admin {
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_scripts() {
        // Enqueue admin assets
    }
}
```

#### 3. GPT Trainer API (`GptTrainerApi.php`)

Manages API communication:

```php
class GptTrainerApi {
    public function make_http_request($method, $endpoint, $data = null) {
        // Make API requests
    }
}
```

## Development Workflow

### 1. Feature Development

1. Create feature branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Implement feature following standards:
   - WordPress coding standards
   - PSR-4 autoloading
   - PHPDoc documentation

3. Add tests:
   ```bash
   composer test
   ```

4. Submit pull request

### 2. Testing

#### Unit Tests

```bash
# Run all tests
composer test

# Run specific test
./vendor/bin/phpunit tests/TestCase.php
```

#### Integration Tests

```bash
composer test-integration
```

#### Code Coverage

```bash
composer test-coverage
```

### 3. Code Quality

#### Coding Standards

```bash
# Check coding standards
composer run-script phpcs

# Fix coding standards
composer run-script phpcbf
```

#### Static Analysis

```bash
composer run-script phpstan
```

## WordPress Integration

### 1. Hooks and Filters

#### Actions

```php
// Initialize plugin
do_action('piper_privacy_sorn_init');

// Handle API errors
do_action('piper_privacy_sorn_api_error', $error_code, $message);
```

#### Filters

```php
// Modify API headers
$headers = apply_filters('piper_privacy_sorn_api_headers', $headers);

// Modify API response
$response = apply_filters('piper_privacy_sorn_api_response', $response);
```

### 2. Admin Pages

1. Register admin menu:
   ```php
   add_menu_page(
       'SORN Manager',
       'SORN Manager',
       'manage_options',
       $this->plugin_name
   );
   ```

2. Register settings:
   ```php
   register_setting(
       'piper_privacy_sorn_options',
       'gpt_trainer_api_token'
   );
   ```

### 3. AJAX Handlers

```php
add_action('wp_ajax_create_data_source', [$this, 'ajax_create_data_source']);
```

## Security

### 1. Input Validation

```php
// Sanitize input
$input = sanitize_text_field($_POST['input']);

// Validate nonce
check_ajax_referer('piper_privacy_sorn_nonce', 'nonce');

// Check capabilities
if (!current_user_can('manage_options')) {
    wp_die();
}
```

### 2. Output Escaping

```php
// Escape HTML
echo esc_html($text);

// Escape attributes
echo esc_attr($value);

// Escape URLs
echo esc_url($url);
```

### 3. API Security

```php
// Secure API token storage
update_option('gpt_trainer_api_token', $token);

// Secure API requests
wp_remote_request($url, [
    'headers' => [
        'Authorization' => 'Bearer ' . $token
    ]
]);
```

## Deployment

### 1. Version Management

```php
// Update version in main plugin file
define('PIPER_PRIVACY_SORN_VERSION', '1.0.0');
```

### 2. Release Process

1. Update changelog
2. Update version numbers
3. Run tests
4. Build release package
5. Deploy to WordPress.org

### 3. Database Updates

```php
// Register activation hook
register_activation_hook(__FILE__, [$this, 'activate']);

// Handle database updates
public function update_database() {
    $current_version = get_option('piper_privacy_sorn_db_version');
    if ($current_version < PIPER_PRIVACY_SORN_VERSION) {
        // Perform updates
    }
}
```

## Troubleshooting

### 1. Debug Mode

```php
if (WP_DEBUG) {
    $this->log_debug_message('Debug info');
}
```

### 2. Error Logging

```php
error_log('Error message');
```

### 3. Common Issues

1. API Connection:
   - Check API token
   - Verify SSL certificates
   - Check server connectivity

2. WordPress Integration:
   - Check plugin activation
   - Verify WordPress version
   - Check PHP version

## Support

For development support:

1. Check documentation
2. Submit GitHub issues
3. Contact development team
