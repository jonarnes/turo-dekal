<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config,
    ) {
    }

    public function register(string $username, string $email, string $password): int
    {
        $username = trim($username);
        $email = trim(strtolower($email));
        if ($username === '' || $email === '' || mb_strlen($password) < 8) {
            throw new \RuntimeException('Ugyldig input. Passord må være minst 8 tegn.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $this->pdo->prepare('INSERT INTO users (username, email, password_hash, created_at) VALUES (?,?,?,datetime("now"))');
        $st->execute([$username, $email, $hash]);
        return (int) $this->pdo->lastInsertId();
    }

    public function loginWithPassword(string $usernameOrEmail, string $password): ?int
    {
        $id = trim($usernameOrEmail);
        $st = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ?');
        $st->execute([$id, strtolower($id)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return null;
        }
        return (int) $row['id'];
    }

    public function issueRememberMe(int $userId): string
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($this->config['remember_me_lifetime_seconds'] ?? 1209600));
        $st = $this->pdo->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at) VALUES (?,?,?,?,datetime("now"))');
        $st->execute([$userId, $selector, $tokenHash, $expiresAt]);
        return $selector . ':' . $validator;
    }

    public function consumeRememberMe(string $cookieValue): ?int
    {
        if (!str_contains($cookieValue, ':')) {
            return null;
        }
        [$selector, $validator] = explode(':', $cookieValue, 2);
        $st = $this->pdo->prepare('SELECT id, user_id, token_hash, expires_at FROM remember_tokens WHERE selector = ?');
        $st->execute([$selector]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            $this->pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([(int) $row['id']]);
            return null;
        }
        if (!hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            return null;
        }
        return (int) $row['user_id'];
    }

    public function clearRememberTokensForUser(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
    }

    public function createMagicToken(string $email): ?string
    {
        $email = trim(strtolower($email));
        $st = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        $userId = $st->fetchColumn();
        if ($userId === false) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($this->config['magic_link_lifetime_seconds'] ?? 1200));
        $this->pdo->prepare('INSERT INTO magic_login_tokens (user_id, token_hash, expires_at, created_at) VALUES (?,?,?,datetime("now"))')
            ->execute([(int) $userId, $hash, $expiresAt]);
        return $token;
    }

    public function loginWithMagicToken(string $token): ?int
    {
        $hash = hash('sha256', $token);
        $st = $this->pdo->prepare('SELECT id, user_id, expires_at, used_at FROM magic_login_tokens WHERE token_hash = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['used_at']) || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        $this->pdo->prepare('UPDATE magic_login_tokens SET used_at = datetime("now") WHERE id = ?')->execute([(int) $row['id']]);
        return (int) $row['user_id'];
    }
}

