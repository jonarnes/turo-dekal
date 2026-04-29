<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\AuthService;
use App\Services\Database;
use App\Services\MailService;

final class AuthController
{
    public function __construct(
        private readonly array $config,
        private readonly string $urlBase,
    ) {
    }

    public function showLogin(): void
    {
        View::render('auth/login', [
            'title' => 'Logg inn',
            'urlBase' => $this->urlBase,
            'csrf' => Csrf::token(),
            'error' => $_SESSION['flash_error'] ?? null,
            'success' => $_SESSION['flash_success'] ?? null,
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    }

    public function loginPassword(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Ugyldig CSRF-token.';
            $this->go('login');
            return;
        }
        $pdo = Database::pdo($this->config['base_path']);
        $auth = new AuthService($pdo, $this->config);
        $userId = $auth->loginWithPassword((string) ($_POST['username_or_email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($userId === null) {
            $_SESSION['flash_error'] = 'Feil brukernavn/e-post eller passord.';
            $this->go('login');
            return;
        }
        $this->setLogin($userId, !empty($_POST['remember_me']));
        $this->go(null);
    }

    public function showRegister(): void
    {
        View::render('auth/register', [
            'title' => 'Opprett bruker',
            'urlBase' => $this->urlBase,
            'csrf' => Csrf::token(),
            'error' => $_SESSION['flash_error'] ?? null,
            'success' => $_SESSION['flash_success'] ?? null,
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    }

    public function register(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Ugyldig CSRF-token.';
            $this->go('register');
            return;
        }
        try {
            $pdo = Database::pdo($this->config['base_path']);
            $auth = new AuthService($pdo, $this->config);
            $userId = $auth->register((string) ($_POST['username'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
            $this->setLogin($userId, true);
            $this->go(null);
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Kunne ikke opprette bruker: ' . $e->getMessage();
            $this->go('register');
        }
    }

    public function sendMagicLink(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Ugyldig CSRF-token.';
            $this->go('login');
            return;
        }
        $email = (string) ($_POST['email'] ?? '');
        try {
            $pdo = Database::pdo($this->config['base_path']);
            $auth = new AuthService($pdo, $this->config);
            $token = $auth->createMagicToken($email);
            if ($token !== null) {
                $link = $this->fullUrl('/index.php?route=magic_login&token=' . urlencode($token));
                (new MailService($this->config))->sendMagicLink($email, $link);
            }
            $_SESSION['flash_success'] = 'Hvis e-posten finnes i systemet er magisk lenke sendt.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Kunne ikke sende magisk lenke: ' . $e->getMessage();
        }
        $this->go('login');
    }

    public function magicLogin(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        if ($token === '') {
            $_SESSION['flash_error'] = 'Manglende token.';
            $this->go('login');
            return;
        }
        $pdo = Database::pdo($this->config['base_path']);
        $auth = new AuthService($pdo, $this->config);
        $userId = $auth->loginWithMagicToken($token);
        if ($userId === null) {
            $_SESSION['flash_error'] = 'Lenken er ugyldig eller utløpt.';
            $this->go('login');
            return;
        }
        $this->setLogin($userId, true);
        $this->go(null);
    }

    public function logout(): void
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        if ($userId !== null) {
            $pdo = Database::pdo($this->config['base_path']);
            (new AuthService($pdo, $this->config))->clearRememberTokensForUser($userId);
        }
        unset($_SESSION['user_id']);
        $this->clearRememberCookie();
        $this->go('login');
    }

    private function setLogin(int $userId, bool $remember): void
    {
        $_SESSION['user_id'] = $userId;
        if ($remember) {
            $pdo = Database::pdo($this->config['base_path']);
            $auth = new AuthService($pdo, $this->config);
            $cookieVal = $auth->issueRememberMe($userId);
            $this->setRememberCookie($cookieVal);
        }
    }

    private function go(?string $route): void
    {
        $url = $this->urlBase . '/index.php';
        if ($route !== null) {
            $url .= '?route=' . urlencode($route);
        }
        header('Location: ' . $url, true, 302);
        exit;
    }

    private function setRememberCookie(string $value): void
    {
        $name = (string) ($this->config['remember_me_cookie'] ?? 'dekal_turo_remember');
        $ttl = (int) ($this->config['remember_me_lifetime_seconds'] ?? 1209600);
        setcookie($name, $value, [
            'expires' => time() + $ttl,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }

    private function clearRememberCookie(): void
    {
        $name = (string) ($this->config['remember_me_cookie'] ?? 'dekal_turo_remember');
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }

    private function fullUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->urlBase . $path;
    }
}

