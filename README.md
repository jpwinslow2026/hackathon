# Microsoft 365 Migration Scoping

A PHP/MariaDB web application for Sales Engineers to conduct customer discovery and produce an SOW-ready requirements summary.

## Current capabilities

- Local username/password authentication
- Each Sales Engineer sees only their own assessments
- Customer-facing Q&A beginning with the existing email environment
- Microsoft 365 Commercial, GCC, and GCC High discovery
- Email, Teams, SharePoint, OneDrive, and Intune requirements
- Mail archives, routing, filtering, SMTP devices/apps, SSO, quantities, and deadlines
- Copyable web summary with basic Word and text exports
- Optional AI-assisted risks, assumptions, dependencies, exclusions, and follow-up questions

Generated findings require Sales Engineer validation before contractual use.

## Requirements

- Apache 2.4 with AllowOverride enabled for the account web root
- PHP 8.2+ with PDO MySQL, cURL, JSON, and sessions
- MariaDB 10.6+
- HTTPS for production

## Repository layout

The repository supports both hosting models:

- A dedicated server can set its Apache DocumentRoot to public/.
- Shared hosting or Virtualmin can place the complete repository directly in public_html.

For public_html, the root index.php loads the application from public/index.php. The root .htaccess serves assets from public/assets and blocks browser access to .env, src/, database/, bin/, storage/, Git metadata, and other dotfiles.

## No-sudo deployment directly to the web root

These instructions assume this application will be the website served by your account's public_html directory.

### 1. Check the web root before installing

    cd "$HOME/public_html"
    pwd
    find . -mindepth 1 -maxdepth 1 -not -name '.well-known' -print

If existing website files are listed, stop and back them up or use a separate Virtualmin virtual server/subdomain. Do not overwrite an existing site.

If public_html already contains a .git directory, stop and inspect its existing repository rather than replacing it.

### 2. Check out the application into public_html

From an empty or application-dedicated public_html directory:

    cd "$HOME/public_html"
    git init
    git remote add origin https://github.com/jpwinslow2026/hackathon.git
    git fetch --depth=1 origin agent/php-migration-scoping-mvp
    git checkout -B production FETCH_HEAD
    git branch --set-upstream-to=origin/agent/php-migration-scoping-mvp production

The application should now have index.php, .htaccess, public/, src/, database/, bin/, and storage/ directly beneath public_html.

For later updates:

    cd "$HOME/public_html"
    git pull --ff-only

### 3. Validate PHP

    cd "$HOME/public_html"
    php -m | grep -E 'curl|json|PDO|pdo_mysql|session'
    find src public bin -name '*.php' -print0 | xargs -0 -n1 php -l
    php -l index.php

Every lint command must report no syntax errors.

### 4. Create tables in the assigned MariaDB database

Create a database and database user through Virtualmin or obtain them from the server administrator. The application does not require permission to create databases.

Import only the application tables into the assigned database:

    mariadb -h localhost -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < database/tables.sql

Do not use database/schema.sql on restricted hosting; that file is for administrators provisioning a completely new database.

Confirm the tables:

    mariadb -h localhost -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME -e "SHOW TABLES;"

### 5. Configure the application

    cd "$HOME/public_html"
    cp .env.example .env
    nano .env

Set:

    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://your-real-domain.example
    APP_KEY=replace-with-output-from-openssl
    DB_HOST=localhost
    DB_PORT=3306
    DB_DATABASE=YOUR_DATABASE_NAME
    DB_USERNAME=YOUR_DATABASE_USER
    DB_PASSWORD=YOUR_DATABASE_PASSWORD
    OPENAI_API_KEY=
    OPENAI_MODEL=gpt-5.6-luna

Generate APP_KEY in a second SSH window:

    openssl rand -hex 32

Protect the configuration:

    chmod 600 "$HOME/public_html/.env"

OPENAI_API_KEY can remain blank while testing the Q&A and exports.

### 6. Create the first local Sales Engineer

    cd "$HOME/public_html"
    php bin/create-user.php joe "Joe Pilliod"

The command prompts for a password of at least 12 characters. Repeat it for each Sales Engineer. There is no public registration page or default password.

### 7. Test from the web root

Browse to the domain itself, not a subdirectory:

    https://your-real-domain.example/

Confirm:

1. The sign-in page loads.
2. The local account can sign in.
3. A customer assessment can be created and saved.
4. The scope-output page can be copied.
5. Word and text exports download.

Verify that sensitive paths are blocked:

    curl -I https://your-real-domain.example/.env
    curl -I https://your-real-domain.example/src/Env.php
    curl -I https://your-real-domain.example/database/tables.sql

Each sensitive request must return 403 or 404. Do not enter customer data if any returns 200.

## Production security

Use HTTPS before entering customer information. Keep .env mode 600, restrict SSH access, back up MariaDB, and establish a retention policy for customer discovery data.
