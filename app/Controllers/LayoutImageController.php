<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;

final class LayoutImageController
{
    public function __construct(private readonly array $config)
    {
    }

    public function serve(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            return;
        }
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            return;
        }
        $pdo = Database::pdo($this->config['base_path']);
        $st = $pdo->prepare('SELECT stored_path FROM layout_assets WHERE id = ? AND user_id = ?');
        $st->execute([$id, $userId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            http_response_code(404);
            return;
        }
        $rel = ltrim((string) $row['stored_path'], '/');
        if ($rel === '' || str_contains($rel, '..') || !str_starts_with($rel, 'storage/uploads/images/')) {
            http_response_code(403);
            return;
        }
        $path = $this->config['base_path'] . '/' . $rel;
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            return;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        if (!str_starts_with($mime, 'image/')) {
            http_response_code(403);
            return;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
    }
}

