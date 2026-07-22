<?php
declare(strict_types=1);

/*
 * Web-root front controller for shared hosting and public_html deployments.
 * Application code remains in src/ and the main web implementation remains
 * in public/ so the same repository also supports a dedicated virtual host.
 */
require __DIR__ . '/public/index.php';
