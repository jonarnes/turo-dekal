<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function path(string $basePath): string
    {
        return $basePath . '/storage/app.sqlite';
    }

    public static function pdo(string $basePath): PDO
    {
        if (self::$pdo === null) {
            $path = self::path($basePath);
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::migrate(self::$pdo);
        }
        return self::$pdo;
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS imports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  original_filename TEXT NOT NULL,
  stored_path TEXT NOT NULL,
  imported_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  import_id INTEGER NOT NULL,
  row_index INTEGER NOT NULL,
  tur TEXT,
  navn TEXT,
  kode TEXT NOT NULL,
  poeng TEXT,
  qr_url TEXT NOT NULL,
  beskrivelse TEXT,
  FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS layout_assets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  filename TEXT NOT NULL,
  stored_path TEXT NOT NULL,
  column_index INTEGER NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);
CREATE TABLE IF NOT EXISTS remember_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  selector TEXT NOT NULL UNIQUE,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS magic_login_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL);
        self::ensureColumn($pdo, 'imports', 'user_id INTEGER');
        self::ensureColumn($pdo, 'posts', 'user_id INTEGER');
        self::ensureColumn($pdo, 'layout_assets', 'user_id INTEGER');
        try {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_import ON posts(import_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_kode ON posts(kode)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_user ON posts(user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_imports_user ON imports(user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_layout_assets_user ON layout_assets(user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_magic_user ON magic_login_tokens(user_id)');
        } catch (PDOException) {
            // ignore
        }
    }

    private static function ensureColumn(PDO $pdo, string $table, string $definition): void
    {
        $col = strtolower(strtok($definition, ' '));
        $cols = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($cols as $row) {
            if (strtolower((string) ($row['name'] ?? '')) === $col) {
                return;
            }
        }
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $definition);
    }
}
