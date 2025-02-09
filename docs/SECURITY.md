# Security Guide

This document outlines the security measures implemented in the SORN Manager WordPress plugin and provides guidelines for maintaining security.

## Overview

The SORN Manager plugin handles sensitive information and interacts with external APIs. Security is a top priority, and multiple layers of protection are implemented.

## Security Measures

### 1. Authentication & Authorization

#### WordPress Integration

```php
// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Verify nonce
check_ajax_referer('piper_privacy_sorn_nonce', 'nonce');
```

#### API Authentication

- API tokens stored securely using WordPress options API
- Tokens encrypted at rest
- No token exposure in logs or errors

### 2. Data Validation & Sanitization

#### Input Validation

```php
// Sanitize text input
$name = sanitize_text_field($_POST['name']);

// Validate URLs
$url = esc_url_raw($_POST['url']);

// Sanitize file uploads
$allowed_types = ['pdf', 'txt', 'json'];
$file_type = wp_check_filetype($file['name']);
```

#### Output Escaping

```php
// Escape HTML content
echo esc_html($content);

// Escape HTML attributes
echo esc_attr($value);

// Escape URLs
echo esc_url($url);
```

### 3. Form Security

#### CSRF Protection

```php
// Add nonce to forms
wp_nonce_field('piper_privacy_sorn_action', 'piper_privacy_sorn_nonce');

// Verify nonce
if (!wp_verify_nonce($_POST['piper_privacy_sorn_nonce'], 'piper_privacy_sorn_action')) {
    wp_die(__('Invalid security token sent.'));
}
```

#### File Upload Security

```php
// Validate file uploads
public function validate_file_upload($file) {
    // Check file type
    $allowed_types = ['pdf', 'txt', 'json'];
    $file_type = wp_check_filetype($file['name']);
    
    if (!in_array($file_type['ext'], $allowed_types)) {
        throw new \InvalidArgumentException('Invalid file type.');
    }
    
    // Check file size
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        throw new \InvalidArgumentException('File too large.');
    }
    
    // Additional security checks
    if (!wp_verify_nonce($_POST['upload_nonce'], 'file_upload')) {
        throw new \InvalidArgumentException('Invalid security token.');
    }
}
```

### 4. API Security

#### Request Security

```php
// Secure API requests
private function make_api_request($endpoint, $data) {
    $headers = [
        'Authorization' => 'Bearer ' . $this->get_api_token(),
        'User-Agent' => 'SORN Manager WordPress Plugin/' . PIPER_PRIVACY_SORN_VERSION,
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ];
    
    return wp_remote_post($endpoint, [
        'headers' => $headers,
        'body' => $data,
        'timeout' => 30,
        'sslverify' => true
    ]);
}
```

#### Token Management

```php
// Secure token storage
private function store_api_token($token) {
    if (empty($token)) {
        return false;
    }
    
    return update_option('gpt_trainer_api_token', $this->encrypt_token($token));
}

// Secure token retrieval
private function get_api_token() {
    $token = get_option('gpt_trainer_api_token');
    return $this->decrypt_token($token);
}
```

### 5. Error Handling & Logging

```php
// Secure error logging
private function log_error($message, $context = []) {
    // Remove sensitive data
    $context = $this->sanitize_log_data($context);
    
    // Log error
    error_log(sprintf(
        '[SORN Manager] %s: %s',
        $message,
        wp_json_encode($context)
    ));
}

// Sanitize sensitive data
private function sanitize_log_data($data) {
    $sensitive_keys = ['api_token', 'password', 'key'];
    
    array_walk_recursive($data, function(&$value, $key) use ($sensitive_keys) {
        if (in_array($key, $sensitive_keys)) {
            $value = '***REDACTED***';
        }
    });
    
    return $data;
}
```

## Security Best Practices

### 1. Plugin Updates

- Keep plugin updated to latest version
- Monitor WordPress security announcements
- Test updates in staging environment

### 2. Server Security

- Use HTTPS for all connections
- Keep PHP updated
- Configure proper file permissions
- Enable WordPress security features

### 3. API Token Security

- Rotate tokens periodically
- Use environment-specific tokens
- Monitor API usage for unusual patterns

### 4. User Access Control

- Implement principle of least privilege
- Regular audit of user permissions
- Strong password policies

### 5. Data Protection

- Minimize data collection
- Encrypt sensitive data
- Regular security audits
- Proper data disposal

## Security Checklist

### Development

- [ ] Input validation implemented
- [ ] Output escaping in place
- [ ] CSRF protection added
- [ ] File upload validation
- [ ] API security measures
- [ ] Error handling secure
- [ ] Logging sanitized

### Deployment

- [ ] SSL/TLS enabled
- [ ] File permissions set
- [ ] Debug mode disabled
- [ ] Error reporting configured
- [ ] API tokens secured
- [ ] Backups configured
- [ ] Updates planned

### Monitoring

- [ ] Error logging enabled
- [ ] API usage monitored
- [ ] File integrity checks
- [ ] User activity logged
- [ ] Security scans scheduled

## Incident Response

### 1. Detection

- Monitor logs for suspicious activity
- Watch for unusual API usage
- Check file integrity

### 2. Response

1. Assess impact
2. Contain breach
3. Notify affected parties
4. Document incident
5. Implement fixes

### 3. Recovery

1. Restore from backup if needed
2. Reset security credentials
3. Update security measures
4. Test systems
5. Document lessons learned

## Security Contacts

For security issues:

1. Submit security issues privately
2. Contact security team
3. Follow responsible disclosure

## Compliance

- GDPR compliance measures
- HIPAA considerations
- FedRAMP requirements
- Privacy regulations

## Additional Resources

1. WordPress Security Guide
2. OWASP Top 10
3. API Security Best Practices
4. PHP Security Manual

## 🔒 Security Policy

### Overview

The Varry LLC DBA PiperPrivacy SORN Manager prioritizes security in handling sensitive government records. This document outlines our security measures and best practices.

### Security Features

#### Role-Based Access Control
```php
// Example of role capability checks
public function check_sorn_access($sorn_id): bool {
    if (!current_user_can('edit_sorns')) {
        return false;
    }

    // Check agency-specific permissions
    $user_agency = get_user_meta(get_current_user_id(), 'agency', true);
    $sorn_agency = $this->get_sorn_agency($sorn_id);
    
    return $user_agency === $sorn_agency || current_user_can('manage_options');
}
```

#### Custom Capabilities
- `edit_sorns`: Basic SORN editing
- `publish_sorns`: Submit SORNs for publication
- `manage_sorn_settings`: Configure plugin settings
- `review_sorns`: Review and approve SORNs

### Data Protection

#### Encryption
```php
// Example of data encryption
public function encrypt_sensitive_data(string $data): string {
    if (empty($data)) {
        return '';
    }

    $key = $this->get_encryption_key();
    $method = 'aes-256-gcm';
    $iv = random_bytes(12);
    $tag = '';

    $encrypted = openssl_encrypt(
        $data,
        $method,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        16
    );

    return base64_encode($iv . $tag . $encrypted);
}
```

#### Database Security
- Prepared statements for all queries
- Input validation and sanitization
- Regular security audits
- Encrypted sensitive fields

### API Security

#### Request Authentication
```php
// Example of API request validation
public function validate_api_request(WP_REST_Request $request): bool {
    // Verify nonce
    $nonce = $request->get_header('X-WP-Nonce');
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return false;
    }

    // Verify user capabilities
    if (!current_user_can('edit_sorns')) {
        return false;
    }

    return true;
}
```

#### Rate Limiting
```php
// Example of rate limiting
public function check_rate_limit(): bool {
    $user_id = get_current_user_id();
    $key = "rate_limit_$user_id";
    $limit = 1000; // requests per hour
    
    $count = get_transient($key) ?: 0;
    if ($count >= $limit) {
        return false;
    }
    
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    return true;
}
```

### Audit Logging

#### Event Logging
```php
// Example of audit logging
public function log_security_event(
    string $action,
    int $user_id,
    array $data = []
): void {
    global $wpdb;
    
    $wpdb->insert(
        $wpdb->prefix . 'piper_privacy_audit_log',
        [
            'action' => $action,
            'user_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'data' => json_encode($data),
            'created_at' => current_time('mysql')
        ]
    );
}
```

#### Monitored Events
- Login attempts
- SORN modifications
- Settings changes
- API access
- Federal Register submissions

### Form Security

#### CSRF Protection
```php
// Example of form security
public function render_secure_form(): void {
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('sorn_action', 'sorn_nonce'); ?>
        <input type="hidden" name="action" value="save_sorn">
        <!-- Form fields -->
    </form>
    <?php
}

public function validate_form(): bool {
    if (!isset($_POST['sorn_nonce']) || 
        !wp_verify_nonce($_POST['sorn_nonce'], 'sorn_action')) {
        wp_die('Invalid nonce');
    }
    return true;
}
```

#### Input Validation
```php
// Example of input validation
public function sanitize_sorn_input(array $data): array {
    return [
        'title' => sanitize_text_field($data['title']),
        'content' => wp_kses_post($data['content']),
        'agency' => sanitize_text_field($data['agency']),
        'system_number' => sanitize_text_field($data['system_number'])
    ];
}
```

## Security Best Practices

### 1. Password Policy
- Minimum 12 characters
- Require complexity
- Regular password changes
- MFA requirement for admin users

### 2. File Security
- Validate file uploads
- Restrict file types
- Scan for malware
- Secure file permissions

### 3. Error Handling
- Custom error pages
- Log security errors
- Sanitize error messages
- Prevent information disclosure

### 4. Network Security
- Force HTTPS
- Secure headers
- CORS policy
- API rate limiting

## Security Monitoring

### 1. Automated Scans
```bash
# Run security scan
composer security-check

# Scan dependencies
composer audit

# Check WordPress core
wp security check
```

### 2. Manual Reviews
- Code reviews
- Penetration testing
- Security assessments
- Vulnerability scanning

## Incident Response

### 1. Response Plan
1. Identify breach
2. Contain impact
3. Investigate cause
4. Implement fixes
5. Document incident
6. Notify affected parties

### 2. Recovery Steps
1. Reset credentials
2. Patch vulnerabilities
3. Restore from backup
4. Update security measures
5. Review and improve

## Reporting Security Issues

### Contact Information
- **Company**: Varry LLC DBA PiperPrivacy
- **Security Team Lead**: Trevor Lowing, CIO
- **Email**: security@piperprivacy.com

### Responsible Disclosure
1. Email your findings to security@piperprivacy.com
2. Include detailed steps to reproduce
3. Allow up to 48 hours for initial response
4. Maintain confidentiality until resolution
5. We will keep you updated on the fix progress

## Compliance

### Standards
- NIST 800-53
- FISMA
- FedRAMP
- Privacy Act of 1974

### Certifications
- Annual security audits
- Penetration testing
- Vulnerability assessments
- Compliance reviews

## Updates & Patches

### Update Policy
1. Security patches within 24 hours
2. Regular updates monthly
3. Emergency updates as needed
4. Tested before deployment

## Resources

- [WordPress Security Team](https://wordpress.org/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [NIST Guidelines](https://www.nist.gov/cyberframework)
- [FedRAMP Security](https://www.fedramp.gov/documents/)
