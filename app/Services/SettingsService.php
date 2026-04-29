<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SettingsService
{
    public function get(PDO $pdo, string $key, ?string $default = null, ?int $userId = null): ?string
    {
        $scoped = $this->scopedKey($key, $userId);
        $st = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $st->execute([$scoped]);
        $v = $st->fetchColumn();
        return $v === false ? $default : (string) $v;
    }

    public function set(PDO $pdo, string $key, string $value, ?int $userId = null): void
    {
        $scoped = $this->scopedKey($key, $userId);
        $pdo->prepare('INSERT INTO settings (key, value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
            ->execute([$scoped, $value]);
    }

    public function activeImportId(PDO $pdo, ?int $userId = null): ?int
    {
        $v = $this->get($pdo, 'active_import_id', null, $userId);
        return $v !== null && $v !== '' ? (int) $v : null;
    }

    private function scopedKey(string $key, ?int $userId): string
    {
        if ($userId === null) {
            return $key;
        }
        return 'u' . $userId . ':' . $key;
    }
}

