<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';

if (!is_dir($config['base_path'] . '/storage')) {
    mkdir($config['base_path'] . '/storage', 0755, true);
}
foreach (['uploads/excel', 'uploads/images', 'cache/qr'] as $sub) {
    $d = $config['base_path'] . '/storage/' . $sub;
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
    }
}

require_once $config['base_path'] . '/vendor/autoload.php';

session_name($config['session_name']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

return $config;
