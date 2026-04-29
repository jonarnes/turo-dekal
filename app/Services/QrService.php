<?php

declare(strict_types=1);

namespace App\Services;

use Endroid\QrCode\Builder\Builder;

final class QrService
{
    /** Mindre rutenett = lavere minnebruk når Dompdf rasteriserer mange sider. */
    private const QR_PIXEL_SIZE = 200;

    public function dataUriForText(string $text): string
    {
        return Builder::create()
            ->data($text)
            ->size(self::QR_PIXEL_SIZE)
            ->margin(4)
            ->build()
            ->getDataUri();
    }

    public function savePngToFile(string $text, string $absolutePath): void
    {
        Builder::create()
            ->data($text)
            ->size(self::QR_PIXEL_SIZE)
            ->margin(4)
            ->build()
            ->saveToFile($absolutePath);
    }
}
