<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $basePath = dirname(__DIR__, 2);
        $viewFile = $basePath . '/app/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }
        $data['___viewFile'] = $viewFile;
        extract($data, EXTR_SKIP);
        include $basePath . '/app/Views/layout.php';
    }
}
