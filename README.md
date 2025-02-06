# PiperPrivacy SORN Manager

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://www.mysql.com/)

Transform your agency's Privacy Program with our AI-powered SORN (System of Records Notice) management system. Seamlessly integrate with the Federal Register, maintain FedRAMP compliance, and streamline your privacy documentation workflow.

![SORN Manager Dashboard](assets/images/dashboard-preview.png)

## 🚀 Key Features

- **Federal Register Integration**
  - Automatic SORN archival and synchronization
  - Real-time submission tracking
  - Historical SORN analysis

- **AI-Powered Assistance**
  - Intelligent SORN drafting suggestions
  - Automated compliance checking
  - Privacy impact analysis
  - Writing style recommendations

- **FedRAMP System Integration**
  - Complete system catalog
  - Authorization tracking
  - System-SORN relationship mapping
  - Impact level management

- **Advanced Search & Analytics**
  - Full-text search capabilities
  - Agency-wide SORN analytics
  - Custom reporting tools
  - Compliance metrics dashboard

- **Workflow Management**
  - Customizable approval processes
  - Role-based access control
  - Audit logging
  - Version control

## 🎯 Why PiperPrivacy SORN Manager?

- **Save Time**: Reduce SORN drafting time by up to 75% with AI assistance
- **Ensure Compliance**: Automated checks against latest privacy requirements
- **Improve Quality**: AI-powered suggestions for clarity and completeness
- **Streamline Workflow**: End-to-end management of the SORN lifecycle
- **Stay Current**: Automatic synchronization with Federal Register updates

## 🔧 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer for dependency management

## 📦 Installation

1. Clone this repository to your WordPress plugins directory:
```bash
cd wp-content/plugins
git clone https://github.com/PiperPrivacy/sorn-manager.git piper-privacy-sorn
```

2. Install dependencies using Composer:
```bash
cd piper-privacy-sorn
composer install
```

3. Activate the plugin through the WordPress admin interface.

4. Configure the plugin settings:
   - Set up Federal Register API credentials
   - Configure GPT Trainer API access
   - Set up database connections
   - Configure email notifications

## ⚙️ Configuration

### Required API Keys

The following API keys need to be configured in the plugin settings:

1. Federal Register API access
2. GPT Trainer API credentials
3. FedRAMP Marketplace API access

### Environment Variables

Create a `.env` file in the plugin root directory:

```env
# API Keys
FEDERAL_REGISTER_API_KEY=your_key_here
GPT_TRAINER_API_KEY=your_key_here
FEDRAMP_API_KEY=your_key_here

# Database Configuration
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_username
DB_PASSWORD=your_password

# Email Configuration
SMTP_HOST=your_smtp_host
SMTP_PORT=587
SMTP_USERNAME=your_username
SMTP_PASSWORD=your_password
```

## 🎮 Usage

### Quick Start

1. Access SORN Manager through WordPress admin menu
2. Import existing SORNs or create new ones
3. Use AI assistant for drafting and improvements
4. Monitor status in the dashboard
5. Generate compliance reports

### Advanced Features

- **Batch Operations**
  - Mass import/export
  - Bulk updates
  - Agency-wide changes

- **Custom Workflows**
  - Define approval chains
  - Set up notifications
  - Configure auto-actions

- **Analytics & Reporting**
  - Compliance metrics
  - Agency statistics
  - Audit reports

## 🛠️ Development

### Directory Structure

```
piper-privacy-sorn/
├── admin/                 # Admin interface files
├── includes/             # Core plugin classes
│   ├── Api/             # REST API endpoints
│   ├── Services/        # Business logic
│   └── Database/        # Database operations
├── public/               # Public-facing functionality
├── languages/           # Translation files
├── templates/           # Template files
├── assets/              # CSS, JS, and images
└── vendor/              # Composer dependencies
```

### Running Tests

```bash
composer test
```

### Coding Standards

We follow WordPress coding standards:

```bash
# Check coding standards
composer phpcs

# Auto-fix coding standards
composer phpcbf
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📝 License

MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- Documentation: [docs.piperprivacy.com](https://docs.piperprivacy.com)
- Issues: [GitHub Issues](https://github.com/PiperPrivacy/sorn-manager/issues)
- Email: support@piperprivacy.com
- Community: [Join our Slack](https://piperprivacy.slack.com)

## 🌟 Acknowledgments

- [Federal Register API](https://www.federalregister.gov/developers/api/v1)
- [FedRAMP Program](https://www.fedramp.gov/)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)

## About PiperPrivacy

PiperPrivacy is a product of Varry LLC, specializing in privacy compliance and automation solutions for government agencies. Our SORN Manager plugin streamlines the process of creating, managing, and publishing System of Records Notices (SORNs) in compliance with the Privacy Act of 1974.

### Company Information

- **Company Name**: Varry LLC DBA PiperPrivacy
- **Leadership**: Trevor Lowing, Chief Information Officer
- **Focus**: Privacy Compliance Automation
- **Target Market**: Federal Agencies and Government Contractors

---

Made with ❤️ by [PiperPrivacy](https://piperprivacy.com)
