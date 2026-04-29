<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\Database;
use App\Services\SettingsService;
use App\Services\WizardService;
use PDO;

final class HomeController
{
    public function __construct(
        private readonly array $config,
        private readonly string $urlBase,
    ) {
    }

    public function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            header('Location: ' . $this->urlBase . '/index.php?route=login', true, 302);
            exit;
        }
        $pdo = Database::pdo($this->config['base_path']);
        $settings = new SettingsService();
        $wizard = (new WizardService())->state($pdo, $userId);
        $importId = $settings->activeImportId($pdo, $userId);
        $count = 0;
        $posts = [];
        if ($importId !== null) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ? AND import_id = ?');
            $st->execute([$userId, $importId]);
            $count = (int) $st->fetchColumn();
            $st = $pdo->prepare('SELECT kode, tur, navn, beskrivelse FROM posts WHERE user_id = ? AND import_id = ? ORDER BY row_index ASC');
            $st->execute([$userId, $importId]);
            $posts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        View::render('home', [
            'title' => 'Turo-dekal',
            'urlBase' => $this->urlBase,
            'wizard' => $wizard,
            'activeStep' => 3,
            'postCount' => $count,
            'hasImport' => $importId !== null,
            'posts' => $posts,
        ]);
    }
}
