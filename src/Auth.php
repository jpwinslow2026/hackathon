<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function attempt(PDO $db, string $username, string $password): bool
    {
        $stmt = $db->prepare('SELECT id, username, display_name, password_hash FROM users WHERE username = ? AND active = 1 LIMIT 1');
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            password_verify($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
        ];
        $db->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (self::id() === 0) {
            redirect('?action=login');
        }
    }
}
