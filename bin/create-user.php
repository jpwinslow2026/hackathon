<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;

$username = trim((string) ($argv[1] ?? ''));
$displayName = trim((string) ($argv[2] ?? ''));
if ($username === '' || $displayName === '') {
    fwrite(STDERR, "Usage: php bin/create-user.php <username> \"Display Name\"\n");
    exit(1);
}

fwrite(STDOUT, 'Password: ');
if (function_exists('shell_exec')) {
    shell_exec('stty -echo');
}
$password = trim((string) fgets(STDIN));
if (function_exists('shell_exec')) {
    shell_exec('stty echo');
}
fwrite(STDOUT, "\n");

if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$stmt = Database::connection()->prepare('INSERT INTO users (username, display_name, password_hash) VALUES (?, ?, ?)');
$stmt->execute([$username, $displayName, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Local user created successfully.\n");
