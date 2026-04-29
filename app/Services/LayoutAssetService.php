<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class LayoutAssetService
{
    /** @return list<array{id:int,filename:string,stored_path:string,column_index:int,sort_order:int}> */
    public function allOrdered(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, filename, stored_path, column_index, sort_order FROM layout_assets WHERE user_id = ? ORDER BY column_index ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, list<array{id:int,filename:string,stored_path:string}>> */
    public function groupedByColumn(PDO $pdo, int $userId): array
    {
        $out = [1 => [], 2 => [], 3 => []];
        foreach ($this->allOrdered($pdo, $userId) as $row) {
            $c = (int) $row['column_index'];
            if ($c >= 1 && $c <= 3) {
                $out[$c][] = $row;
            }
        }
        return $out;
    }

    public function imageDataUri(string $absolutePath): ?string
    {
        if (!is_readable($absolutePath)) {
            return null;
        }
        $bin = @file_get_contents($absolutePath);
        if ($bin === false) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($bin) ?: 'image/png';
        if (!str_starts_with($mime, 'image/')) {
            return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }
}
