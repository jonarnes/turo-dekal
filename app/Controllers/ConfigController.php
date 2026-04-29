<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Database;
use App\Services\LayoutAssetService;
use App\Services\SettingsService;
use App\Services\WizardService;
use PDO;

final class ConfigController
{
    private const IMAGE_WIDTH_MM = 46.0;
    private const IMAGE_MAX_HEIGHT_MM = 42.0;
    private const IMAGE_GAP_MM = 3.0;
    private const SIDE_IMAGE_AVAILABLE_MM = 140.0;
    private const MIDDLE_IMAGE_AVAILABLE_MM = 56.0;

    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly array $config,
        private readonly string $urlBase,
    ) {
    }

    public function form(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            header('Location: ' . $this->urlBase . '/index.php?route=login', true, 302);
            exit;
        }
        $pdo = Database::pdo($this->config['base_path']);
        $assets = (new LayoutAssetService())->allOrdered($pdo, $userId);
        $warnings = $this->columnWarnings($assets, $this->config['base_path']);
        $settings = new SettingsService();
        $columnTexts = [
            1 => $settings->get($pdo, 'column_text_1', '', $userId) ?? '',
            2 => $settings->get($pdo, 'column_text_2', '', $userId) ?? '',
            3 => $settings->get($pdo, 'column_text_3', '', $userId) ?? '',
        ];
        $columnLayouts = $this->columnLayouts($pdo, $userId, $assets);
        View::render('config', [
            'title' => 'Konfigurasjon',
            'urlBase' => $this->urlBase,
            'wizard' => (new WizardService())->state($pdo, $userId),
            'activeStep' => 2,
            'assets' => $assets,
            'warnings' => $warnings,
            'columnTexts' => $columnTexts,
            'columnLayouts' => $columnLayouts,
            'error' => $_SESSION['flash_error'] ?? null,
            'success' => $_SESSION['flash_success'] ?? null,
            'csrf' => Csrf::token(),
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    }

    public function uploadImage(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF']);
            return;
        }
        if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ingen bilde']);
            return;
        }
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > $this->config['upload_max_bytes']) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Opplasting feilet']);
            return;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($f['tmp_name']) ?: '';
        if (!isset(self::ALLOWED_MIME[$mime])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ugyldig bildeformat']);
            return;
        }
        $ext = self::ALLOWED_MIME[$mime];
        $base = $this->config['base_path'];
        $filename = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $rel = 'storage/uploads/images/' . $filename;
        $dest = $base . '/' . $rel;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Kunne ikke lagre']);
            return;
        }

        $col = (int) ($_POST['column_index'] ?? 1);
        if ($col < 1 || $col > 3) {
            $col = 1;
        }
        $pdo = Database::pdo($base);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $mst = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM layout_assets WHERE user_id = ? AND column_index = ?');
        $mst->execute([$userId, $col]);
        $max = (int) $mst->fetchColumn();
        $orig = $f['name'] ?? $filename;
        $stmt = $pdo->prepare(
            'INSERT INTO layout_assets (user_id, filename, stored_path, column_index, sort_order, created_at)
             VALUES (?,?,?,?,?, datetime("now"))'
        );
        $stmt->execute([$userId, $orig, $rel, $col, $max + 1]);
        $id = (int) $pdo->lastInsertId();
        echo json_encode([
            'ok' => true,
            'asset' => [
                'id' => $id,
                'filename' => $orig,
                'stored_path' => $rel,
                'column_index' => $col,
                'sort_order' => $max + 1,
            ],
        ]);
    }

    public function deleteImage(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF']);
            return;
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Mangler id']);
            return;
        }
        $base = $this->config['base_path'];
        $pdo = Database::pdo($base);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $st = $pdo->prepare('SELECT stored_path FROM layout_assets WHERE id = ? AND user_id = ?');
        $st->execute([$id, $userId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Finnes ikke']);
            return;
        }
        $path = $base . '/' . ltrim($row['stored_path'], '/');
        $pdo->prepare('DELETE FROM layout_assets WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        if (is_file($path)) {
            @unlink($path);
        }
        echo json_encode(['ok' => true]);
    }

    public function saveLayout(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'JSON']);
            return;
        }
        $token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!Csrf::validate(is_string($token) ? $token : null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF']);
            return;
        }
        $items = $data['items'] ?? null;
        if (!is_array($items)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'items']);
            return;
        }
        $pdo = Database::pdo($this->config['base_path']);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $upd = $pdo->prepare('UPDATE layout_assets SET column_index = ?, sort_order = ? WHERE id = ? AND user_id = ?');
        $settings = new SettingsService();
        $pdo->beginTransaction();
        try {
            foreach ($items as $i => $it) {
                if (!is_array($it)) {
                    continue;
                }
                $id = (int) ($it['id'] ?? 0);
                $col = (int) ($it['column_index'] ?? 1);
                if ($id <= 0) {
                    continue;
                }
                if ($col < 1) {
                    $col = 1;
                }
                if ($col > 3) {
                    $col = 3;
                }
                $sort = (int) ($it['sort_order'] ?? $i);
                $upd->execute([$col, $sort, $id, $userId]);
            }
            $texts = $data['column_texts'] ?? [];
            if (is_array($texts)) {
                for ($col = 1; $col <= 3; $col++) {
                    $value = $texts[(string) $col] ?? $texts[$col] ?? '';
                    if (!is_string($value)) {
                        $value = '';
                    }
                    $settings->set($pdo, 'column_text_' . $col, trim($value), $userId);
                }
            }
            $layouts = $data['column_layouts'] ?? [];
            if (is_array($layouts)) {
                for ($col = 1; $col <= 3; $col++) {
                    $raw = $layouts[(string) $col] ?? $layouts[$col] ?? [];
                    $tokens = [];
                    if (is_array($raw)) {
                        foreach ($raw as $token) {
                            if (!is_string($token)) {
                                continue;
                            }
                            $token = trim($token);
                            if ($token === 'text' || ($col === 2 && $token === 'qr') || preg_match('/^img:\d+$/', $token) === 1) {
                                $tokens[] = $token;
                            }
                        }
                    }
                    $settings->set($pdo, 'column_layout_' . $col, json_encode($tokens, JSON_UNESCAPED_UNICODE), $userId);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            return;
        }
        echo json_encode(['ok' => true]);
    }

    /**
     * @param list<array{id:int,filename:string,stored_path:string,column_index:int,sort_order:int}> $assets
     * @return array<int, list<string>>
     */
    private function columnLayouts(PDO $pdo, int $userId, array $assets): array
    {
        $settings = new SettingsService();
        $byColumn = [1 => [], 2 => [], 3 => []];
        foreach ($assets as $asset) {
            $col = (int) ($asset['column_index'] ?? 0);
            if ($col < 1 || $col > 3) {
                continue;
            }
            $byColumn[$col][] = 'img:' . (int) $asset['id'];
        }

        $out = [];
        for ($col = 1; $col <= 3; $col++) {
            $fallback = $byColumn[$col];
            if ($col === 2) {
                $fallback[] = 'qr';
            }
            $fallback[] = 'text';

            $raw = (string) ($settings->get($pdo, 'column_layout_' . $col, '', $userId) ?? '');
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
            $saved = is_array($decoded) ? $decoded : [];

            $valid = [];
            foreach ($saved as $token) {
                if (!is_string($token)) {
                    continue;
                }
                if ($token === 'text' || ($col === 2 && $token === 'qr') || preg_match('/^img:\d+$/', $token) === 1) {
                    $valid[] = $token;
                }
            }

            $allowed = array_fill_keys($fallback, true);
            $merged = [];
            foreach ($valid as $token) {
                if (isset($allowed[$token]) && !in_array($token, $merged, true)) {
                    $merged[] = $token;
                }
            }
            foreach ($fallback as $token) {
                if (!in_array($token, $merged, true)) {
                    $merged[] = $token;
                }
            }
            $out[$col] = $merged;
        }

        return $out;
    }

    /**
     * @param list<array{id:int,filename:string,stored_path:string,column_index:int,sort_order:int}> $assets
     * @return array<int, array{available_mm:float,total_mm:float,overflow:bool,scale:float}>
     */
    private function columnWarnings(array $assets, string $basePath): array
    {
        $perColumn = [1 => [], 2 => [], 3 => []];
        foreach ($assets as $asset) {
            $col = (int) ($asset['column_index'] ?? 0);
            if ($col < 1 || $col > 3) {
                continue;
            }
            $rel = ltrim((string) ($asset['stored_path'] ?? ''), '/');
            if ($rel === '' || str_contains($rel, '..')) {
                continue;
            }
            $abs = $basePath . '/' . $rel;
            if (!is_readable($abs)) {
                continue;
            }

            $size = @getimagesize($abs);
            $w = is_array($size) ? (float) ($size[0] ?? 0.0) : 0.0;
            $h = is_array($size) ? (float) ($size[1] ?? 0.0) : 0.0;
            if ($w > 0.0 && $h > 0.0) {
                $fitHeight = self::IMAGE_WIDTH_MM * ($h / $w);
                $targetHeight = min(self::IMAGE_MAX_HEIGHT_MM, $fitHeight);
            } else {
                $targetHeight = self::IMAGE_MAX_HEIGHT_MM;
            }
            $perColumn[$col][] = $targetHeight;
        }

        $out = [];
        for ($col = 1; $col <= 3; $col++) {
            $available = $col === 2 ? self::MIDDLE_IMAGE_AVAILABLE_MM : self::SIDE_IMAGE_AVAILABLE_MM;
            $count = count($perColumn[$col]);
            $gapTotal = max(0, $count - 1) * self::IMAGE_GAP_MM;
            $total = $gapTotal;
            foreach ($perColumn[$col] as $h) {
                $total += $h;
            }
            $overflow = $total > $available;
            $scale = 1.0;
            if ($overflow && $total > 0.0) {
                $imageOnlySpace = max(1.0, $available - $gapTotal);
                $imageOnlyTotal = max(1.0, $total - $gapTotal);
                $scale = min(1.0, $imageOnlySpace / $imageOnlyTotal);
            }
            $out[$col] = [
                'available_mm' => $available,
                'total_mm' => round($total, 1),
                'overflow' => $overflow,
                'scale' => round($scale, 3),
            ];
        }

        return $out;
    }
}
