<?php

declare(strict_types=1);

use App\Controllers\ConfigController;
use App\Controllers\HomeController;
use App\Controllers\LayoutImageController;
use App\Controllers\PdfController;
use App\Controllers\UploadController;
use App\Controllers\AuthController;
use App\Services\AuthService;
use App\Services\Database;
use App\Services\WizardService;

$config = require dirname(__DIR__) . '/app/bootstrap.php';

$script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$urlBase = rtrim(str_replace('\\', '/', dirname($script)), '/');
if ($urlBase === '/' || $urlBase === '.') {
    $urlBase = '';
}

$route = $_GET['route'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$publicRoutes = ['login', 'login_password', 'register', 'register_submit', 'magic_send', 'magic_login'];
$pdo = Database::pdo($config['base_path']);
if (!isset($_SESSION['user_id'])) {
    $cookieName = (string) ($config['remember_me_cookie'] ?? 'dekal_turo_remember');
    if (!empty($_COOKIE[$cookieName])) {
        $auth = new AuthService($pdo, $config);
        $userId = $auth->consumeRememberMe((string) $_COOKIE[$cookieName]);
        if ($userId !== null) {
            $_SESSION['user_id'] = $userId;
        }
    }
}

if ($route !== null && !in_array($route, $publicRoutes, true) && !isset($_SESSION['user_id'])) {
    header('Location: ' . $urlBase . '/index.php?route=login', true, 302);
    exit;
}

if ($route === null) {
    if (!isset($_SESSION['user_id'])) {
        $route = 'login';
    } else {
        $wizard = (new WizardService())->state($pdo, (int) $_SESSION['user_id']);
        $route = match ((int) $wizard['default_step']) {
            1 => 'upload',
            2 => 'config',
            default => 'home',
        };
    }
}

switch ($route) {
    case 'upload':
        $c = new UploadController($config, $urlBase);
        if ($method === 'POST') {
            $c->handlePost();
        } else {
            $c->form();
        }
        break;
    case 'login':
        (new AuthController($config, $urlBase))->showLogin();
        break;
    case 'login_password':
        if ($method !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        (new AuthController($config, $urlBase))->loginPassword();
        break;
    case 'register':
        (new AuthController($config, $urlBase))->showRegister();
        break;
    case 'register_submit':
        if ($method !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        (new AuthController($config, $urlBase))->register();
        break;
    case 'magic_send':
        if ($method !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        (new AuthController($config, $urlBase))->sendMagicLink();
        break;
    case 'magic_login':
        (new AuthController($config, $urlBase))->magicLogin();
        break;
    case 'logout':
        (new AuthController($config, $urlBase))->logout();
        break;

    case 'config':
        (new ConfigController($config, $urlBase))->form();
        break;

    case 'config_upload_image':
        if ($method !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        (new ConfigController($config, $urlBase))->uploadImage();
        break;

    case 'config_delete_image':
        if ($method !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        (new ConfigController($config, $urlBase))->deleteImage();
        break;

    case 'config_save_layout':
        if ($method !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        (new ConfigController($config, $urlBase))->saveLayout();
        break;

    case 'layout_image':
        if ($method !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        (new LayoutImageController($config))->serve();
        break;

    case 'pdf':
        (new PdfController($config))->download();
        break;

    case 'home':
    default:
        (new HomeController($config, $urlBase))->index();
        break;
}
