# PiperPrivacy SORN Manager User Guide

## Overview

The PiperPrivacy SORN Manager is a comprehensive WordPress plugin for managing System of Records Notices (SORNs) with Federal Register integration and FedRAMP system catalog support. This guide will help you understand and use all the features of the plugin.

## Getting Started

### Installation

1. Upload the `piper-privacy-sorn` directory to your WordPress plugins directory (`wp-content/plugins/`)
2. Activate the plugin through the WordPress admin interface
3. Navigate to "SORN Manager" in the admin menu to begin configuration

### Initial Configuration

1. Go to "SORN Manager > Settings"
2. Enter your Federal Register API key
3. Enter your GPT Trainer API key
4. Configure notification settings (email, Slack, Teams)
5. Save your settings

## Managing SORNs

### Creating a New SORN

1. Navigate to "SORN Manager > Add New"
2. Fill in the required fields:
   - Title
   - System Number
   - Agency
   - Purpose
   - Categories of Records
   - Routine Uses
3. Use the AI-powered assistant to help draft content
4. Save as draft or publish

### Using AI Features

#### Draft Generation
1. Click "Generate Draft" in the SORN editor
2. Provide basic information about the system
3. Review and edit the generated content

#### Compliance Analysis
1. Click "Analyze" in the SORN editor
2. Review compliance scores and suggestions
3. Address any identified issues

### Federal Register Integration

#### Submitting to Federal Register
1. Open a published SORN
2. Click "Submit to Federal Register"
3. Review submission details
4. Confirm submission

#### Tracking Submissions
1. Go to "SORN Manager > Federal Register"
2. View submission status and history
3. Handle any submission errors

## FedRAMP System Catalog

### Adding Systems
1. Navigate to "SORN Manager > FedRAMP Systems"
2. Click "Add New System"
3. Enter system details:
   - System Name
   - Provider
   - Impact Level
   - Authorization Details

### Linking Systems to SORNs
1. Edit a SORN
2. Go to "Systems" section
3. Click "Add System"
4. Select from FedRAMP catalog or add custom system

## User Management

### Roles and Permissions

#### SORN Editor
- Can create and edit SORNs
- Cannot submit to Federal Register
- Cannot modify settings

#### SORN Reviewer
- Can review and approve SORNs
- Can submit to Federal Register
- Cannot modify settings

#### Administrator
- Full access to all features
- Can manage settings and users
- Can assign roles and permissions

### Managing User Access
1. Go to WordPress Users
2. Edit user profile
3. Assign SORN-specific role
4. Set agency access if applicable

## Notifications

### Email Notifications
- New SORN submissions
- Status changes
- Federal Register updates
- Compliance alerts

### Slack/Teams Integration
1. Configure webhook URL in settings
2. Select notification types
3. Test connection

## Best Practices

### SORN Writing
1. Use clear, concise language
2. Follow agency style guidelines
3. Include all required sections
4. Review AI suggestions

### Security
1. Use strong passwords
2. Enable two-factor authentication
3. Regularly review audit logs
4. Keep API keys secure

### Workflow
1. Create draft
2. Use AI analysis
3. Internal review
4. Submit for publication

## Troubleshooting

### Common Issues

#### Federal Register Submission Fails
1. Check API key validity
2. Verify SORN format
3. Review error message
4. Retry submission

#### AI Features Not Working
1. Verify API key
2. Check internet connection
3. Review error logs
4. Contact support

### Getting Help
- Email: support@piperprivacy.com
- Documentation: https://docs.piperprivacy.com
- Support Portal: https://support.piperprivacy.com

## Updates and Maintenance

### Plugin Updates
1. Back up your data
2. Update through WordPress
3. Test functionality
4. Review changelog

### Data Management
1. Regular backups
2. Archive old SORNs
3. Clean audit logs
4. Optimize database
