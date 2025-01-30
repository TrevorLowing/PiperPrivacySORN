# PiperPrivacy SORN Manager

AI-powered SORN (System of Records Notice) management system with Federal Register integration and FedRAMP system catalog.

## Features

- Federal Register SORN archival and search
- FedRAMP authorized system catalog
- AI-powered SORN drafting assistant
- Automated compliance checking
- Full lifecycle SORN management
- Agency website publication tools
- Advanced search and filtering capabilities

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer for dependency management

## Installation

1. Clone this repository to your WordPress plugins directory:
```bash
cd wp-content/plugins
git clone [repository-url] piper-privacy-sorn
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

## Configuration

### Required API Keys

The following API keys need to be configured in the plugin settings:

1. Federal Register API access
2. GPT Trainer API credentials
3. FedRAMP Marketplace API access
4. Pinecone API key (for vector embeddings)

### Environment Variables

Create a `.env` file in the plugin root directory with the following variables:

```env
# API Keys
FEDERAL_REGISTER_API_KEY=your_key_here
GPT_TRAINER_API_KEY=your_key_here
FEDRAMP_API_KEY=your_key_here
PINECONE_API_KEY=your_key_here

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

## Usage

1. Access the SORN Manager through the WordPress admin menu
2. Use the dashboard to:
   - Browse archived SORNs
   - Search FedRAMP systems
   - Create new SORN drafts
   - Monitor SORN lifecycle status
   - Generate compliance reports

## Development

### Directory Structure

```
piper-privacy-sorn/
├── admin/                 # Admin interface files
├── includes/             # Core plugin classes
├── public/               # Public-facing functionality
├── languages/           # Translation files
├── templates/           # Template files
├── assets/              # CSS, JS, and image files
└── vendor/              # Composer dependencies
```

### Running Tests

```bash
composer test
```

### Coding Standards

The project follows WordPress coding standards. To check compliance:

```bash
composer phpcs
```

To automatically fix coding standards:

```bash
composer phpcbf
```

## License

Proprietary - All rights reserved

## Support

For support, please contact support@piperprivacy.com
