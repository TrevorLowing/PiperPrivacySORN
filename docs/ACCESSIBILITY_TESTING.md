# Accessibility Testing Guide
## PiperPrivacy Plugin

### Overview
This guide outlines the procedures for testing the accessibility features of the PiperPrivacy plugin. It includes both automated and manual testing procedures to ensure WCAG 2.1 Level AA compliance.

## Automated Testing

### 1. PHPUnit Tests
Run the automated accessibility tests:
```bash
composer test tests/accessibility/test-accessibility.php
```

### 2. Pa11y Testing
Install and run Pa11y for automated accessibility testing:
```bash
npm install -g pa11y
pa11y http://your-test-site.local/privacy-forms/
```

### 3. Axe Core Testing
Run axe-core tests in Chrome DevTools:
1. Open Chrome DevTools
2. Navigate to the Axe tab
3. Click "Analyze" to scan the page

## Manual Testing

### 1. Screen Reader Testing

#### NVDA (Windows)
1. Download and install NVDA
2. Test the following scenarios:
   - [ ] Form navigation
   - [ ] Error messages
   - [ ] Progress updates
   - [ ] Modal dialogs
   - [ ] File uploads
   - [ ] Form submission feedback

#### VoiceOver (macOS)
1. Enable VoiceOver (⌘ + F5)
2. Test the same scenarios as NVDA

### 2. Keyboard Navigation

#### Tab Order
Test the following using only the keyboard:
- [ ] Navigate through all form fields
- [ ] Access all buttons and controls
- [ ] Navigate multi-step forms
- [ ] Access error messages
- [ ] Submit forms
- [ ] Navigate modal dialogs

#### Keyboard Shortcuts
Verify these keyboard interactions:
- Tab: Next focusable element
- Shift + Tab: Previous focusable element
- Space/Enter: Activate buttons
- Arrow keys: Navigate radio buttons
- Escape: Close modals

### 3. Visual Testing

#### Color Contrast
Use the WAVE tool to check:
- [ ] Text contrast meets 4.5:1 ratio
- [ ] Form field contrast
- [ ] Button contrast
- [ ] Error message contrast
- [ ] Link contrast

#### Focus Indicators
Verify focus visibility:
- [ ] All interactive elements have visible focus
- [ ] Focus indicator is high contrast
- [ ] Focus moves logically
- [ ] No focus traps

### 4. Content Testing

#### Alternative Text
Check all images and icons:
- [ ] Meaningful alt text present
- [ ] Decorative images marked appropriately
- [ ] Icon buttons have labels
- [ ] SVG elements have titles

#### Form Labels
Verify form accessibility:
- [ ] All fields have visible labels
- [ ] Required fields clearly marked
- [ ] Error messages are descriptive
- [ ] Help text is associated with fields

### 5. Responsive Testing

#### Zoom Testing
Test at different zoom levels:
- [ ] 100% baseline
- [ ] 200% zoom
- [ ] Text-only zoom
- [ ] Mobile responsive layout

#### Touch Targets
Verify on touch devices:
- [ ] Buttons are at least 44x44px
- [ ] Adequate spacing between targets
- [ ] Touch gestures work as expected

## Continuous Integration Testing

For a detailed visual representation of the CI/CD workflow, please refer to [CI_WORKFLOW.md](./CI_WORKFLOW.md). This document includes comprehensive flow diagrams of:
- Complete CI/CD pipeline
- Test suite components
- Report generation process
- Error handling procedures

### GitHub Actions Workflow

The plugin uses GitHub Actions to automate accessibility testing. The workflow is defined in `.github/workflows/accessibility.yml` and includes:

1. **Unit Tests**
   - PHPUnit accessibility tests
   - Form structure validation
   - ARIA attribute testing
   - Screen reader compatibility

2. **Automated Scans**
   - Pa11y for WCAG compliance
   - Axe Core for accessibility rules
   - Lighthouse for performance impact

3. **Test Matrix**
   - Multiple PHP versions
   - Multiple WordPress versions
   - Cross-browser testing

### Running CI Tests

1. **Automatic Triggers**
   ```bash
   # Tests run automatically on:
   git push origin main     # Push to main
   git push origin develop  # Push to develop
   # Pull request creation/update
   ```

2. **Manual Triggers**
   - Go to GitHub Actions tab
   - Select "Accessibility Tests"
   - Click "Run workflow"
   - Choose branch and options

### Test Reports

1. **Accessing Reports**
   - Go to GitHub Actions
   - Select completed workflow run
   - Download artifacts
   - Reports available in JSON and HTML

2. **Report Contents**
   ```
   test-results/
   ├── axe-results.json
   ├── axe-results.html
   ├── lighthouse-results/
   │   └── accessibility.json
   └── pa11y-results/
       └── summary.json
   ```

3. **Failure Notifications**
   - GitHub Issues created automatically
   - Tagged with "accessibility" label
   - Contains failure details and fixes

### Local CI Testing

1. **Prerequisites**
   ```bash
   # Install dependencies
   npm install
   composer install

   # Setup test environment
   bash bin/install-wp-tests.sh wordpress_test root root localhost
   ```

2. **Run Tests**
   ```bash
   # Full test suite
   npm run test:a11y

   # Individual tests
   npm run test:axe
   npm run audit:a11y
   composer test tests/accessibility/test-accessibility.php
   ```

3. **View Results**
   ```bash
   # Open test reports
   open test-results/axe-results.html
   cat test-results/pa11y-results/summary.json
   ```

### Customizing CI Tests

1. **Pa11y Configuration** (`.pa11yci`)
   ```json
   {
     "defaults": {
       "standard": "WCAG2AA",
       "timeout": 10000
     }
   }
   ```

2. **Axe Rules** (`.axe.json`)
   ```json
   {
     "rules": {
       "color-contrast": { "enabled": true },
       "html-has-lang": { "enabled": true }
     }
   }
   ```

3. **GitHub Workflow**
   ```yaml
   # Customize test matrix
   strategy:
     matrix:
       php: [7.4, 8.0, 8.1, 8.2]
       wordpress: [latest, '6.3', '6.2']
   ```

### Best Practices

1. **Pre-Commit Testing**
   - Run local tests before pushing
   - Check previous failure patterns
   - Review accessibility guidelines

2. **Report Analysis**
   - Review all test artifacts
   - Check for patterns in failures
   - Prioritize critical issues

3. **Maintenance**
   - Keep dependencies updated
   - Monitor test performance
   - Update test configurations

### Troubleshooting CI

1. **Common Issues**
   - Database connection failures
   - Timeout errors
   - Missing dependencies

2. **Solutions**
   - Check environment variables
   - Increase timeout values
   - Verify dependency versions

3. **Support**
   - Check GitHub Actions logs
   - Review error messages
   - Consult documentation

## Tools and Resources

### Testing Tools
1. Screen Readers
   - NVDA (Windows)
   - VoiceOver (macOS)
   - JAWS (Windows)

2. Automated Tools
   - WAVE Browser Extension
   - axe DevTools
   - Pa11y
   - Lighthouse

3. Color Tools
   - WebAIM Contrast Checker
   - Color Oracle (Color blindness simulator)

### Browser Testing
Test in major browsers:
- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge

### Reporting Issues

When reporting accessibility issues:

1. Issue Description
   - What is the issue?
   - Which WCAG criterion it violates
   - Steps to reproduce

2. Environment Details
   - Browser and version
   - Screen reader (if applicable)
   - Device/OS

3. Impact
   - Who is affected?
   - How severe is the issue?
   - Proposed solution

### Testing Checklist

#### Forms
- [ ] All form controls have labels
- [ ] Required fields are marked
- [ ] Error messages are clear
- [ ] Form can be submitted with keyboard
- [ ] ARIA attributes are correct

#### Navigation
- [ ] Skip links work
- [ ] Keyboard focus is visible
- [ ] Focus order is logical
- [ ] No keyboard traps
- [ ] Landmarks are properly marked

#### Content
- [ ] Proper heading structure
- [ ] Lists are marked up correctly
- [ ] Tables have headers
- [ ] Images have alt text
- [ ] Icons have labels

#### Interaction
- [ ] Custom controls are accessible
- [ ] Modal dialogs trap focus
- [ ] Status messages are announced
- [ ] Progress is communicated
- [ ] Timeouts are handled

## Compliance Documentation

### WCAG 2.1 Level AA Checklist
- [ ] 1.1.1 Non-text Content
- [ ] 1.2.4 Captions
- [ ] 1.2.5 Audio Description
- [ ] 1.3.1 Info and Relationships
- [ ] 1.3.2 Meaningful Sequence
- [ ] 1.3.3 Sensory Characteristics
- [ ] 1.3.4 Orientation
- [ ] 1.3.5 Identify Input Purpose
- [ ] 1.4.3 Contrast
- [ ] 1.4.4 Resize Text
- [ ] 1.4.5 Images of Text
- [ ] 1.4.10 Reflow
- [ ] 1.4.11 Non-text Contrast
- [ ] 1.4.12 Text Spacing
- [ ] 1.4.13 Content on Hover or Focus
- [ ] 2.4.5 Multiple Ways
- [ ] 2.4.6 Headings and Labels
- [ ] 2.4.7 Focus Visible
- [ ] 3.1.2 Language of Parts
- [ ] 3.2.3 Consistent Navigation
- [ ] 3.2.4 Consistent Identification
- [ ] 3.3.3 Error Suggestion
- [ ] 3.3.4 Error Prevention
- [ ] 4.1.3 Status Messages

## Regular Testing Schedule

1. Daily Testing
   - Quick keyboard navigation check
   - Screen reader announcement check
   - Error message verification

2. Weekly Testing
   - Full form submission testing
   - Color contrast verification
   - Mobile responsiveness check

3. Monthly Testing
   - Comprehensive WCAG audit
   - Browser compatibility check
   - Third-party tool testing

4. Quarterly Testing
   - User testing with assistive technologies
   - Performance impact assessment
   - Documentation update

## Training Resources

1. Developer Resources
   - [WAI-ARIA Practices](https://www.w3.org/WAI/ARIA/apg/)
   - [WordPress Accessibility Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/)
   - [MDN Accessibility Guide](https://developer.mozilla.org/en-US/docs/Web/Accessibility)

2. Testing Resources
   - [WebAIM Articles](https://webaim.org/articles/)
   - [Deque University](https://dequeuniversity.com/)
   - [A11Y Project Checklist](https://www.a11yproject.com/checklist/)
