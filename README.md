# Microsoft Migration Scoping

A guided PHP application for collecting Microsoft migration requirements and producing an SOW-ready scoping summary.

## MVP capabilities

- Create customer migration assessments
- Select Microsoft workloads and dynamically load relevant discovery questions
- Save and resume answers in MariaDB
- Generate AI-assisted risks, assumptions, dependencies, exclusions, and follow-up questions
- Review source answers separately from generated findings
- Export a Microsoft Word-compatible scoping summary
- Keep the OpenAI API key entirely on the server

Generated findings require consultant validation before contractual use.

## Requirements

- Apache 2.4 with mod_rewrite
- PHP 8.2+ with PDO MySQL, cURL, JSON, and sessions
- MariaDB 10.6+ recommended
- HTTPS for production
- OpenAI API key is optional for initial testing

## LAMP deployment

### 1. Clone the development branch

    cd /var/www
    sudo git clone --branch agent/php-migration-scoping-mvp https://github.com/jpwinslow2026/hackathon.git migration-scope
    cd migration-scope

### 2. Create the database

    sudo mariadb < database/schema.sql

Create a dedicated database user rather than using MariaDB root:

    CREATE USER 'scope_app'@'localhost' IDENTIFIED BY 'use-a-long-random-password';
    GRANT SELECT, INSERT, UPDATE, DELETE ON migration_scope.* TO 'scope_app'@'localhost';
    FLUSH PRIVILEGES;

### 3. Configure the application

    cp .env.example .env
    chmod 600 .env
    nano .env

Set the MariaDB credentials and, when ready, OPENAI_API_KEY. Generate APP_KEY with:

    openssl rand -hex 32

### 4. Configure Apache

Use the repository public directory as the web root:

    <VirtualHost *:80>
        ServerName scope.example.com
        DocumentRoot /var/www/migration-scope/public

        <Directory /var/www/migration-scope/public>
            AllowOverride All
            Require all granted
        </Directory>

        ErrorLog /var/log/apache2/migration-scope-error.log
        CustomLog /var/log/apache2/migration-scope-access.log combined
    </VirtualHost>

For Debian/Ubuntu:

    sudo a2enmod rewrite
    sudo a2ensite migration-scope.conf
    sudo apachectl configtest
    sudo systemctl reload apache2

On RHEL-family systems, put the virtual host in /etc/httpd/conf.d/migration-scope.conf and restart httpd.

### 5. Validate PHP modules

    php -v
    php -m | grep -E 'curl|json|pdo_mysql|session'

Open the configured URL, create a test assessment, save answers, and download the Word document.

## Security before production

Initially restrict this MVP by VPN, firewall, or Apache authentication. Entra ID single sign-on, roles, audit history, approval controls, true DOCX generation, and production logging are planned next.
