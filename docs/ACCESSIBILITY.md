# ♿ Accessibility Guidelines

## Overview

The PiperPrivacy SORN Manager is committed to ensuring accessibility for all users, following WCAG 2.1 Level AA standards. This document outlines our accessibility features and implementation guidelines.

## WCAG 2.1 Compliance

### 1. Perceivable

#### Text Alternatives
- All images have meaningful alt text
- Complex images have detailed descriptions
- Icons include aria-labels

#### Time-Based Media
- Video tutorials include captions
- Transcripts available for audio content
- Audio notifications have visual alternatives

#### Adaptable Content
- Content can be presented in different layouts
- Responsive design for all screen sizes
- Maintains meaning in different presentations

#### Distinguishable
- Color is not the only means of conveying information
- Minimum contrast ratio of 4.5:1 for normal text
- Text can be resized up to 200% without loss of functionality

### 2. Operable

#### Keyboard Accessible
- All functionality available via keyboard
- No keyboard traps
- Keyboard shortcuts for common actions

#### Timing
- No time limits for reading or action
- Auto-refresh can be paused
- Session timeout warnings with extension option

#### Navigation
- Skip links for main content
- Clear page titles and headings
- Multiple ways to find content
- Current location indicated

### 3. Understandable

#### Readable
- Language identified programmatically
- Technical terms defined inline
- Abbreviations explained on first use

#### Predictable
- Consistent navigation and layout
- Changes of context are user-initiated
- Error messages are clear and helpful

#### Input Assistance
- Form fields have clear labels
- Required fields clearly marked
- Error prevention on important submissions

### 4. Robust

#### Compatible
- Valid HTML5
- Complete start and end tags
- ARIA roles and properties used correctly

## Implementation

### Forms

```php
// Example of accessible form field
public function render_form_field($field) {
    ?>
    <div class="form-group">
        <label for="<?php echo esc_attr($field['id']); ?>" 
               class="screen-reader-text">
            <?php echo esc_html($field['label']); ?>
            <?php if ($field['required']) : ?>
                <span class="required" aria-label="required">*</span>
            <?php endif; ?>
        </label>
        <input type="<?php echo esc_attr($field['type']); ?>"
               id="<?php echo esc_attr($field['id']); ?>"
               name="<?php echo esc_attr($field['name']); ?>"
               aria-required="<?php echo $field['required'] ? 'true' : 'false'; ?>"
               aria-describedby="<?php echo esc_attr($field['id'] . '-help'); ?>"
               class="regular-text">
        <p id="<?php echo esc_attr($field['id'] . '-help'); ?>" 
           class="description">
            <?php echo esc_html($field['description']); ?>
        </p>
    </div>
    <?php
}
```

### Tables

```php
// Example of accessible data table
public function render_sorns_table($sorns) {
    ?>
    <table role="grid" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" role="columnheader">
                    <?php esc_html_e('Title', 'piper-privacy-sorn'); ?>
                </th>
                <th scope="col" role="columnheader">
                    <?php esc_html_e('Agency', 'piper-privacy-sorn'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sorns as $sorn) : ?>
                <tr>
                    <td role="gridcell">
                        <?php echo esc_html($sorn->title); ?>
                    </td>
                    <td role="gridcell">
                        <?php echo esc_html($sorn->agency); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
```

### Notifications

```php
// Example of accessible notifications
public function show_notification($message, $type = 'info') {
    ?>
    <div class="notice notice-<?php echo esc_attr($type); ?>"
         role="alert"
         aria-live="polite">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}
```

## Testing

### Automated Testing

```bash
# Run accessibility tests
npm run test:a11y

# Test specific component
npm run test:a11y -- --component=SornForm
```

### Manual Testing

1. Keyboard Navigation
   - Tab through all interactive elements
   - Verify focus indicators
   - Test keyboard shortcuts

2. Screen Reader Testing
   - Test with NVDA
   - Test with JAWS
   - Test with VoiceOver

3. Visual Testing
   - Test color contrast
   - Test text resizing
   - Test responsive layouts

## Resources

- [WordPress Accessibility Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/accessibility-coding-standards/)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [ARIA Authoring Practices](https://www.w3.org/WAI/ARIA/apg/)
