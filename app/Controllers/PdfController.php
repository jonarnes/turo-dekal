<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\PdfService;
use App\Services\SettingsService;

final class PdfController
{
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function download(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /index.php?route=login', true, 302);
            exit;
        }
        $pdo = Database::pdo($this->config['base_path']);
        $settings = new SettingsService();
        $importId = $settings->activeImportId($pdo, $userId);
        if ($importId === null) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Ingen aktiv import. Last opp Excel først.';
            return;
        }

        $koder = [];
        $all = isset($_GET['all']) && $_GET['all'] === '1';
        if (!$all) {
            $raw = $_GET['koder'] ?? null;
            if (is_array($raw)) {
                foreach ($raw as $p) {
                    if (!is_string($p)) {
                        continue;
                    }
                    $p = trim($p);
                    if ($p !== '') {
                        $koder[] = $p;
                    }
                }
            } elseif (is_string($raw) && $raw !== '') {
                foreach (explode(',', $raw) as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $koder[] = $p;
                    }
                }
            }
        }

        if (!$all && $koder === []) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Velg minst én post (Kode), eller bruk «Last ned alle».';
            return;
        }

        $sql = 'SELECT tur, navn, kode, poeng, qr_url, beskrivelse FROM posts WHERE user_id = ? AND import_id = ?';
        $params = [$userId, $importId];
        if (!$all && $koder !== []) {
            $placeholders = implode(',', array_fill(0, count($koder), '?'));
            $sql .= ' AND kode IN (' . $placeholders . ')';
            $params = array_merge($params, $koder);
        }
        $st = $pdo->prepare($sql . ' ORDER BY row_index ASC');
        $st->execute($params);
        $posts = $st->fetchAll(\PDO::FETCH_ASSOC);
        if ($posts === []) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Ingen poster som matcher filteret.';
            return;
        }

        // Dompdf / php-font-lib utløser E_DEPRECATED på PHP 8.5+; det må ikke sendes til body (ødelegger PDF).
        $prevErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        $prevDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        $bufferDepth = ob_get_level();
        ob_start();
        try {
            $pdf = new PdfService($this->config['base_path']);
            $binary = $pdf->renderPdf($pdo, $userId, $posts);
        } finally {
            while (ob_get_level() > $bufferDepth) {
                ob_end_clean();
            }
            ini_set('display_errors', $prevDisplayErrors !== false && $prevDisplayErrors !== '' ? $prevDisplayErrors : '0');
            error_reporting($prevErrorReporting);
        }

        $filename = 'turo-dekaler-' . date('Y-m-d') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($binary));
        echo $binary;
    }
}
