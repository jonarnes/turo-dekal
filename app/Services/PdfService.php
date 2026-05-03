<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;

final class PdfService
{
    /** Tilbake til original 2-up A4-oppsett (ingen ekstra sidemarg i PDF-en). */
    private const PAGE_MARGIN_MM = 0.0;
    private const CONTENT_WIDTH_MM = 210.0;
    private const CONTENT_HEIGHT_MM = 297.0;
    private const DECAL_HEIGHT_MM = 148.5;
    private const COLUMN_WIDTH_MM = 48.0;
    private const INTER_COLUMN_GAP_MM = 2.0;
    private const IMAGE_WIDTH_MM = 45.0;
    private const IMAGE_MAX_HEIGHT_MM = 42.0;
    private const IMAGE_GAP_MM = 3.0;
    private const SIDE_IMAGE_AVAILABLE_MM = 140.0;
    private const MIDDLE_IMAGE_AVAILABLE_MM = 56.0;
    private const MIN_COLUMN_PADDING_MM = 0.1;

    public function __construct(
        private readonly string $basePath,
        private readonly QrService $qr = new QrService(),
        private readonly LayoutAssetService $layoutAssets = new LayoutAssetService(),
    ) {
    }

    /** @param list<array{tur:string,navn:string,kode:string,poeng:string,qr_url:string,beskrivelse:string}> $posts */
    public function renderPdf(PDO $pdo, int $userId, array $posts): string
    {
        $settings = new SettingsService();
        $columnTexts = [
            1 => $settings->get($pdo, 'column_text_1', '', $userId) ?? '',
            2 => $settings->get($pdo, 'column_text_2', '', $userId) ?? '',
            3 => $settings->get($pdo, 'column_text_3', '', $userId) ?? '',
        ];
        $layoutRows = $this->layoutAssets->allOrdered($pdo, $userId);
        $columnLayouts = $this->columnLayouts($pdo, $userId, $layoutRows);

        $grouped = $this->layoutAssets->groupedByColumn($pdo, $userId);
        $colImageItems = [
            1 => $this->buildColumnImageItems($grouped[1], self::SIDE_IMAGE_AVAILABLE_MM),
            2 => $this->buildColumnImageItems($grouped[2], self::MIDDLE_IMAGE_AVAILABLE_MM),
            3 => $this->buildColumnImageItems($grouped[3], self::SIDE_IMAGE_AVAILABLE_MM),
        ];

        $chroot = realpath($this->basePath);
        if ($chroot === false) {
            $chroot = $this->basePath;
        }

        $qrToken = bin2hex(random_bytes(8));
        $qrDirRel = 'storage/cache/qr/' . $qrToken;
        $qrDirAbs = $this->basePath . '/' . $qrDirRel;
        if (!is_dir($qrDirAbs) && !mkdir($qrDirAbs, 0755, true)) {
            throw new \RuntimeException('Kunne ikke opprette QR-mappe.');
        }

        try {
            $prepared = [];
            foreach ($posts as $i => $post) {
                $rel = $qrDirRel . '/' . $i . '.png';
                $this->qr->savePngToFile($post['qr_url'], $this->basePath . '/' . $rel);
                $prepared[] = array_merge($post, ['_qr_src' => $rel]);
            }

            $chunks = array_chunk($prepared, 2);
            $sheets = '';
            $lastIdx = count($chunks) - 1;
            foreach ($chunks as $i => $pair) {
                $top = $this->decalHtml($pair[0], $colImageItems, $columnTexts, $columnLayouts);
                $bottom = isset($pair[1]) ? $this->decalHtml($pair[1], $colImageItems, $columnTexts, $columnLayouts) : $this->emptyDecalHtml();
                $break = $i < $lastIdx ? 'page-break-after: always;' : '';
                $sheets .= '<div class="a4" style="' . $break . 'width:' . self::CONTENT_WIDTH_MM . 'mm;height:' . self::CONTENT_HEIGHT_MM . 'mm;box-sizing:border-box;overflow:hidden;">'
                    . '<div style="width:' . self::CONTENT_WIDTH_MM . 'mm;height:' . self::DECAL_HEIGHT_MM . 'mm;box-sizing:border-box;overflow:hidden;">' . $top . '</div>'
                    . '<div style="width:' . self::CONTENT_WIDTH_MM . 'mm;height:' . self::DECAL_HEIGHT_MM . 'mm;box-sizing:border-box;overflow:hidden;">' . $bottom . '</div>'
                    . '</div>';
            }

            $pm = self::PAGE_MARGIN_MM;
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                . '<style>
@page { margin: ' . $pm . 'mm; }
body { margin:0; padding:0; font-family: DejaVu Sans, sans-serif; }
* { box-sizing: border-box; }
</style></head><body>' . $sheets . '</body></html>';

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->setChroot($chroot);

            $dompdf = new Dompdf($options);
            // Uten basePath er relative <img src="storage/..."> ugyldig (realpath('') → feil rot).
            $dompdf->setBasePath($chroot);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper([0.0, 0.0, 595.28, 841.89], 'portrait');
            $dompdf->render();
            $binary = $dompdf->output();
            unset($dompdf);

            return $binary;
        } finally {
            $this->removeTree($qrDirAbs);
        }
    }

    /**
     * Bruk korte filstier (ikke base64) slik at HTML ikke multipliserer bildestørrelse × antall dekaler.
     *
     * @param list<array{id:int,filename:string,stored_path:string}> $rows
     */
    private function buildColumnImageItems(array $rows, float $availableHeightMm): array
    {
        if ($rows === []) {
            return [];
        }

        $prepared = [];
        foreach ($rows as $row) {
            $rel = ltrim((string) $row['stored_path'], '/');
            if ($rel === '' || str_contains($rel, '..')) {
                continue;
            }
            $abs = $this->basePath . '/' . $rel;
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
            $prepared[] = ['id' => (int) ($row['id'] ?? 0), 'rel' => $rel, 'height' => $targetHeight];
        }

        if ($prepared === []) {
            return [];
        }

        $count = count($prepared);
        $gapTotal = max(0, $count - 1) * self::IMAGE_GAP_MM;
        $totalHeight = $gapTotal;
        foreach ($prepared as $item) {
            $totalHeight += $item['height'];
        }

        $scale = 1.0;
        if ($totalHeight > $availableHeightMm && $totalHeight > 0.0) {
            $imageOnlySpace = max(1.0, $availableHeightMm - $gapTotal);
            $imageOnlyTotal = max(1.0, $totalHeight - $gapTotal);
            $scale = min(1.0, $imageOnlySpace / $imageOnlyTotal);
        }

        $items = [];
        foreach ($prepared as $idx => $item) {
            $height = max(4.0, round($item['height'] * $scale, 2));
            $items[] = [
                'id' => (int) ($item['id'] ?? 0),
                'html' => '<div style="text-align:center;margin-bottom:' . self::IMAGE_GAP_MM . 'mm;">'
                    . '<img src="' . htmlspecialchars($item['rel'], ENT_QUOTES, 'UTF-8') . '" style="max-width:' . self::IMAGE_WIDTH_MM . 'mm;height:' . $height . 'mm;display:block;margin:0 auto;" alt="" />'
                    . '</div>',
            ];
        }
        return $items;
    }

    /**
     * Tre 50 mm-kolonner, 5 mm mellomrom mellom 1–2 og 2–3, sidefelt (totalt {@see CONTENT_WIDTH_MM} mm).
     *
     * @param array{tur:string,navn:string,kode:string,poeng:string,qr_url:string,beskrivelse:string,_qr_src:string} $post
     * @param array<int, list<array{id:int,html:string}>> $colImageItems
     * @param array{1:string,2:string,3:string} $columnTexts
     * @param array<int, list<string>> $columnLayouts
     */
    private function decalHtml(array $post, array $colImageItems, array $columnTexts, array $columnLayouts): string
    {
        $gutter = (self::CONTENT_WIDTH_MM - 3.0 * self::COLUMN_WIDTH_MM - 2.0 * self::INTER_COLUMN_GAP_MM) / 2.0;
        $gutter = round($gutter, 2);

        $columnPadding = max(2.0, self::MIN_COLUMN_PADDING_MM);
        $qrSrc = htmlspecialchars($post['_qr_src'], ENT_QUOTES, 'UTF-8');
        $kode = htmlspecialchars($post['kode'], ENT_QUOTES, 'UTF-8');
        $navn = htmlspecialchars((string) ($post['navn'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tur = htmlspecialchars($post['tur'], ENT_QUOTES, 'UTF-8');
        $bes = htmlspecialchars($post['beskrivelse'], ENT_QUOTES, 'UTF-8');
        $poeng = htmlspecialchars($post['poeng'], ENT_QUOTES, 'UTF-8');
        $leftText = $this->formatColumnText($columnTexts[1]);
        $midText = $this->formatColumnText($columnTexts[2]);
        $rightText = $this->formatColumnText($columnTexts[3]);

        $qrBlock = '<div style="text-align:center;">'
            . '<div style="font-size:9pt;line-height:1.2;margin-bottom:1.2mm;">Scan med TurO-appen</div>'
            . '<img src="' . $qrSrc . '" style="width:42mm;height:42mm;display:block;margin:0 auto 2mm auto;" alt="QR" />'
            . '<div style="font-size:22pt;font-weight:bold;line-height:1.1;margin:1mm 0;"><span style="font-size:10pt; font-weight:normal;">Kode: </span>' . $kode . '</div>'
            . ($navn !== '' ? '<div style="font-size:9pt;margin-top:1.2mm;line-height:1.2;color:#333;">Postnavn</div><div style="font-size:12pt;font-weight:bold;line-height:1.2;margin-top:0.3mm;">' . $navn . '</div>' : '')
            . ($tur !== '' ? '<div style="font-size:11pt;margin-top:1mm;line-height:1.2;">' . $tur . '</div>' : '')
            . ($bes !== '' ? '<div style="font-size:9pt;margin-top:1.5mm;line-height:1.25;">' . $bes . '</div>' : '')
            . ($poeng !== '' ? '<div style="font-size:8pt;margin-top:1mm;color:#444;">Poeng: ' . $poeng . '</div>' : '')
            . '</div>';
        $textBlocks = [
            1 => $leftText !== '' ? '<div style="font-size:8.5pt;line-height:1.3;white-space:pre-line;margin-top:2mm;">' . $leftText . '</div>' : '',
            2 => $midText !== '' ? '<div style="font-size:8.5pt;line-height:1.3;white-space:pre-line;margin-top:2mm;">' . $midText . '</div>' : '',
            3 => $rightText !== '' ? '<div style="font-size:8.5pt;line-height:1.3;white-space:pre-line;margin-top:2mm;">' . $rightText . '</div>' : '',
        ];

        $imageMap = [1 => [], 2 => [], 3 => []];
        foreach ([1, 2, 3] as $col) {
            foreach ($colImageItems[$col] as $item) {
                $imageMap[$col]['img:' . (int) $item['id']] = (string) $item['html'];
            }
        }

        $leftX = $gutter;
        $midX = $leftX + self::COLUMN_WIDTH_MM + self::INTER_COLUMN_GAP_MM;
        $rightX = $midX + self::COLUMN_WIDTH_MM + self::INTER_COLUMN_GAP_MM;

        return '<div style="position:relative;width:' . self::CONTENT_WIDTH_MM . 'mm;height:' . self::DECAL_HEIGHT_MM . 'mm;overflow:hidden;">'
            . '<div style="position:absolute;left:' . $leftX . 'mm;top:0;width:' . self::COLUMN_WIDTH_MM . 'mm;height:' . self::DECAL_HEIGHT_MM . 'mm;overflow:hidden;vertical-align:middle;">'
            . '<div style="height:100%;padding:' . $columnPadding . 'mm;overflow:hidden;">'
            . $this->renderColumnStack($columnLayouts[1], $imageMap[1], $textBlocks[1], '')
            . '</div></div>'
            . '<div style="position:absolute;left:' . $midX . 'mm;top:0;width:' . self::COLUMN_WIDTH_MM . 'mm;height:' . self::DECAL_HEIGHT_MM . 'mm;overflow:hidden;">'
            . '<div style="height:100%;padding:' . $columnPadding . 'mm;overflow:hidden;">' . $this->renderColumnStack($columnLayouts[2], $imageMap[2], $textBlocks[2], $qrBlock) . '</div>'
            . '</div>'
            . '<div style="position:absolute;left:' . $rightX . 'mm;top:0;width:' . self::COLUMN_WIDTH_MM . 'mm;height:' . self::DECAL_HEIGHT_MM . 'mm;overflow:hidden;">'
            . '<div style="height:100%;padding:' . $columnPadding . 'mm;overflow:hidden;">'
            . $this->renderColumnStack($columnLayouts[3], $imageMap[3], $textBlocks[3], '')
            . '</div></div>'
            . '</div>';
    }

    /**
     * @param list<string> $tokens
     * @param array<string,string> $images
     */
    private function renderColumnStack(array $tokens, array $images, string $textBlock, string $qrBlock): string
    {
        $out = '';
        foreach ($tokens as $token) {
            if ($token === 'text') {
                $out .= $textBlock;
                continue;
            }
            if ($token === 'qr') {
                $out .= $qrBlock;
                continue;
            }
            if (isset($images[$token])) {
                $out .= $images[$token];
            }
        }
        return $out !== '' ? $out : '&nbsp;';
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

    private function emptyDecalHtml(): string
    {
        return '<div style="width:' . self::CONTENT_WIDTH_MM . 'mm;height:' . self::DECAL_HEIGHT_MM . 'mm;"></div>';
    }

    private function formatColumnText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
