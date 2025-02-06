# 🛠️ Development Guide

## Overview

This guide provides detailed information for developers working on the PiperPrivacy SORN Manager WordPress plugin. Our goal is to maintain high code quality while making it easy for new contributors to get started.

## 🚀 Quick Start

### Prerequisites

- PHP 7.4+
- MySQL 5.7+
- WordPress 5.8+
- Composer
- Node.js & npm (for frontend assets)

### Initial Setup

1. Clone the repository:
```bash
git clone https://github.com/PiperPrivacy/sorn-manager.git
cd sorn-manager
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Configure environment:
```bash
cp .env.example .env
# Edit .env with your local settings
```

## 📁 Project Structure

```
piper-privacy-sorn/
├── admin/                    # Admin interface
│   ├── css/                 # Admin styles
│   ├── js/                  # Admin scripts
│   ├── partials/            # Admin templates
│   └── class-admin.php      # Admin controller
├── includes/                # Core functionality
│   ├── Api/                # REST API endpoints
│   │   └── RestController.php
│   ├── Database/           # Database operations
│   │   ├── AuditTables.php
│   │   ├── FederalRegisterTables.php
│   │   └── SornTables.php
│   ├── Services/           # Business logic
│   │   ├── AiService.php
│   │   ├── FederalRegisterService.php
│   │   ├── SecurityService.php
│   │   └── SornDownloadService.php
│   └── Models/             # Data models
├── tests/                  # Test suite
│   ├── bootstrap.php
│   ├── test-ai-service.php
│   ├── test-federal-register-service.php
│   ├── test-rest-controller.php
│   └── test-security-service.php
└── piper-privacy-sorn.php  # Plugin entry point
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
composer test -- --testsuite=unit
composer test -- --testsuite=integration

# Generate coverage report
composer test-coverage
```

### Writing Tests

- Place tests in the `tests/` directory
- Name test files `test-*.php`
- Extend `WP_UnitTestCase` for WordPress integration
- Use data providers for multiple test cases

Example test:
```php
class Test_Security_Service extends WP_UnitTestCase {
    public function test_encrypt_decrypt_data() {
        $service = new SecurityService();
        $data = 'sensitive data';
        $encrypted = $service->encrypt_data($data);
        $decrypted = $service->decrypt_data($encrypted);
        $this->assertEquals($data, $decrypted);
    }
}
```

## 🔍 Code Quality

### Coding Standards

We follow WordPress coding standards with some modern PHP additions:

```bash
# Check coding standards
composer phpcs

# Auto-fix coding standards
composer phpcbf

# Run static analysis
composer phpstan
```

### Key Principles

1. **Type Safety**
   - Use strict types: `declare(strict_types=1);`
   - Add type hints and return types
   - Use PHPDoc for complex types

2. **Object-Oriented Design**
   - Follow SOLID principles
   - Use dependency injection
   - Keep classes focused and small

3. **Error Handling**
   - Use exceptions for exceptional cases
   - Return WP_Error for WordPress integration
   - Proper logging and monitoring

## 🔌 WordPress Integration

### Actions & Filters

```php
// Initialize plugin
do_action('piper_privacy_sorn_init');

// Before SORN submission
do_action('piper_privacy_sorn_before_submit', $sorn_id);

// After SORN submission
do_action('piper_privacy_sorn_after_submit', $sorn_id, $result);

// Filter SORN content
$content = apply_filters('piper_privacy_sorn_content', $content, $sorn_id);
```

### Database Operations

Use our custom table classes:

```php
// Create tables
$audit_tables = new AuditTables();
$audit_tables->init();

// Use WordPress's $wpdb
global $wpdb;
$wpdb->insert(
    $wpdb->prefix . 'piper_privacy_sorns',
    [
        'title' => $title,
        'content' => $content,
        'status' => 'draft'
    ]
);
```

## 🔒 Security

### Best Practices

1. **Data Validation**
   - Sanitize inputs using WordPress functions
   - Validate data types and ranges
   - Escape output appropriately

2. **Authentication & Authorization**
   - Use WordPress capabilities system
   - Implement nonce checks
   - Verify user permissions

3. **Sensitive Data**
   - Use encryption for sensitive data
   - Implement audit logging
   - Follow privacy regulations

## 📦 Deployment

### Version Management

1. Update version numbers:
   - `piper-privacy-sorn.php`
   - `readme.txt`
   - `package.json`

2. Update changelog:
   - Add version section
   - List all changes
   - Credit contributors

### Release Process

1. Create release branch:
```bash
git checkout -b release/1.0.0
```

2. Run final checks:
```bash
composer test
composer phpcs
composer phpstan
```

3. Build assets:
```bash
npm run build
```

4. Create GitHub release:
   - Tag version
   - Upload build
   - Update documentation

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Make changes
4. Add/update tests
5. Submit pull request

See [CONTRIBUTING.md](../CONTRIBUTING.md) for detailed guidelines.

## 📚 Additional Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Federal Register API Documentation](https://www.federalregister.gov/developers/api/v1)
- [Project Documentation](https://docs.piperprivacy.com)
