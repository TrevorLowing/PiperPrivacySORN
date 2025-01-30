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
