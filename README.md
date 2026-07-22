# Microsoft 365 Migration Scoping

A PHP/MariaDB web application for Sales Engineers to conduct customer discovery and produce an SOW-ready requirements summary.

## Current capabilities

- Local username/password authentication
- Each Sales Engineer sees only their own assessments
- Customer-facing Q&A beginning with the existing email environment
- Microsoft 365 Commercial, GCC, and GCC High target discovery
- Email, Teams, SharePoint, OneDrive, and Intune requirements
- Mail archives, routing, third-party filtering, SMTP devices/apps, SSO, quantities, and project deadlines
- Copyable web summary
- Basic Microsoft Word and text exports
- Optional AI-assisted risks, assumptions, dependencies, exclusions, and follow-up questions

Generated findings require Sales Engineer validation before contractual use.

## Requirements

- Apache 2.4 with mod_rewrite
- PHP 8.2+ with PDO MySQL, cURL, JSON, and sessions
- MariaDB 10.6+ recommended
- HTTPS for production

## LAMP deployment

### 1. Clone the development branch

    cd /var/www
    sudo git clone --branch agent/php-migration-scoping-mvp https://github.com/jpwinslow2026/hackathon.git migration-scope
    cd migration-scope

### 2. Create the database

For a new installation:

    sudo mariadb < database/schema.sql

Then create a least-privilege database account:

    sudo mariadb

Run:

    CREATE USER 'scope_app'@'localhost' IDENTIFIED BY 'use-a-long-random-password';
    GRANT SELECT, INSERT, UPDATE, DELETE ON migration_scope.* TO 'scope_app'@'localhost';
    FLUSH PRIVILEGES;
    EXIT;

### 3. Configure the application

    cp .env.example .env
    chmod 600 .env
    nano .env

Set DB_USERNAME, DB_PASSWORD, and APP_KEY. Generate APP_KEY with:

    openssl rand -hex 32

OPENAI_API_KEY can remain blank while testing the core Q&A and exports.

### 4. Create the first local user

    php bin/create-user.php joe "Joe Pilliod"

The command securely prompts for a password of at least 12 characters. Repeat it for each Sales Engineer. There is deliberately no public registration page or default password.

### 5. Configure Apache

Use the public directory as the document root:

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

On Debian/Ubuntu:

    sudo a2enmod rewrite
    sudo a2ensite migration-scope.conf
    sudo apachectl configtest
    sudo systemctl reload apache2

On RHEL-family systems, put the virtual host in /etc/httpd/conf.d/migration-scope.conf and restart httpd.

### 6. Validate

    php -v
    php -m | grep -E 'curl|json|pdo_mysql|session'
    php -l public/index.php
    php -l src/Auth.php
    php -l src/QuestionCatalog.php
    php -l src/OpenAIService.php

Sign in, create a test assessment, complete the Q&A, and test both exports.

## Production security

Use HTTPS before entering customer information. Keep .env outside the Apache document root, restrict server access, back up MariaDB, and establish a retention policy for customer discovery data.
