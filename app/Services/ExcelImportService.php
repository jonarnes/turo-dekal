<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

final class ExcelImportService
{
    public function importFile(PDO $pdo, int $userId, string $basePath, string $relativeStoredPath, string $originalFilename): int
    {
        $absolutePath = $basePath . '/' . ltrim($relativeStoredPath, '/');
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $map = $this->detectHeaderMap($sheet);
        foreach (['qr', 'kode'] as $req) {
            if ($map[$req] === null) {
                throw new RuntimeException('Mangler påkrevd kolonne i Excel: ' . strtoupper($req));
            }
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO imports (user_id, original_filename, stored_path, imported_at) VALUES (?,?,?,datetime("now"))'
            );
            $stmt->execute([$userId, $originalFilename, $relativeStoredPath]);
            $importId = (int) $pdo->lastInsertId();

            $ins = $pdo->prepare(
                'INSERT INTO posts (user_id, import_id, row_index, tur, navn, kode, poeng, qr_url, beskrivelse)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );

            $maxRow = $sheet->getHighestDataRow();
            $rowIndex = 0;
            for ($r = $map['header_row'] + 1; $r <= $maxRow; $r++) {
                $qr = $this->cellString($sheet, $r, $map['qr']);
                if ($qr === '') {
                    continue;
                }
                $kode = $this->cellString($sheet, $r, $map['kode']);
                $tur = $map['tur'] ? $this->cellString($sheet, $r, $map['tur']) : '';
                $navn = $map['navn'] ? $this->cellString($sheet, $r, $map['navn']) : '';
                $poeng = $map['poeng'] ? $this->cellString($sheet, $r, $map['poeng']) : '';
                $bes = $map['beskrivelse'] ? $this->cellString($sheet, $r, $map['beskrivelse']) : '';

                $ins->execute([$userId, $importId, $rowIndex, $tur, $navn, $kode, $poeng, $qr, $bes]);
                $rowIndex++;
            }

            (new SettingsService())->set($pdo, 'active_import_id', (string) $importId, $userId);
            $pdo->commit();
            return $importId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array{header_row:int,tur:?string,navn:?string,kode:?string,poeng:?string,qr:?string,beskrivelse:?string} */
    private function detectHeaderMap(Worksheet $sheet): array
    {
        $maxScan = min(40, $sheet->getHighestDataRow());
        for ($r = 1; $r <= $maxScan; $r++) {
            $headers = [];
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn($r));
            for ($c = 1; $c <= $maxCol; $c++) {
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $raw = $this->cellString($sheet, $r, $letter);
                $key = $this->normalizeHeader($raw);
                if ($key !== '') {
                    $headers[$key] = $letter;
                }
            }
            $map = [
                'header_row' => $r,
                'tur' => $headers['tur'] ?? null,
                'navn' => $headers['navn'] ?? null,
                'kode' => $headers['kode'] ?? null,
                'poeng' => $headers['poeng'] ?? null,
                'qr' => $headers['qr'] ?? null,
                'beskrivelse' => $headers['beskrivelse'] ?? null,
            ];
            if ($map['qr'] !== null && $map['kode'] !== null) {
                return $map;
            }
        }
        throw new RuntimeException('Fant ikke overskriftsrad med Kode og QR.');
    }

    private function normalizeHeader(string $s): string
    {
        $s = strtolower(trim($s));
        return match ($s) {
            'tur' => 'tur',
            'navn' => 'navn',
            'kode' => 'kode',
            'poeng' => 'poeng',
            'qr' => 'qr',
            'beskrivelse' => 'beskrivelse',
            default => '',
        };
    }

    private function cellString(Worksheet $sheet, int $row, string $colLetter): string
    {
        $val = $sheet->getCell($colLetter . $row)->getValue();
        if ($val === null) {
            return '';
        }
        if (is_scalar($val)) {
            return trim((string) $val);
        }
        return trim((string) $val);
    }

}
