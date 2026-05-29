<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\Database;
use PDO;

final class AdminController
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
        
        // Sjekk om brukeren er admin
        $st = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
        $st->execute([$userId]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        
        if (empty($user['is_admin'])) {
            header('Location: ' . $this->urlBase . '/index.php?route=home', true, 302);
            exit;
        }

        // Hent alle brukere med dekal-tall
        $st = $pdo->query('SELECT id, username, email, created_at FROM users ORDER BY created_at DESC');
        $users = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $userStats = [];
        foreach ($users as $user) {
            $userId = (int) $user['id'];
            
            // Tell antall dekaler (posts) for denne brukeren
            $st = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
            $st->execute([$userId]);
            $dekalCount = (int) $st->fetchColumn();
            
            // Tell antall importer for denne brukeren
            $st = $pdo->prepare('SELECT COUNT(*) FROM imports WHERE user_id = ?');
            $st->execute([$userId]);
            $importCount = (int) $st->fetchColumn();
            
            $userStats[] = [
                'id' => $userId,
                'username' => $user['username'],
                'email' => $user['email'],
                'created_at' => $user['created_at'],
                'dekal_count' => $dekalCount,
                'import_count' => $importCount,
                'is_admin' => (bool) $user['is_admin'],
            ];
        }
        
        // Totalt antall dekaler i systemet
        $st = $pdo->query('SELECT COUNT(*) FROM posts');
        $totalDekaler = (int) $st->fetchColumn();

        View::render('admin', [
            'title' => 'Admin - Turo-dekal',
            'urlBase' => $this->urlBase,
            'users' => $userStats,
            'totalDekaler' => $totalDekaler,
            'activeStep' => 0,
            'wizard' => [],
            'isAdmin' => true,
        ]);
    }
}
