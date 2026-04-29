<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class WizardService
{
    /**
     * @return array{
     *   has_import:bool,
     *   design_done:bool,
     *   step1_done:bool,
     *   step2_done:bool,
     *   step3_ready:bool,
     *   default_step:int
     * }
     */
    public function state(PDO $pdo, int $userId): array
    {
        $settings = new SettingsService();
        $importId = $settings->activeImportId($pdo, $userId);
        $hasImport = $importId !== null;

        $st = $pdo->prepare('SELECT COUNT(*) FROM layout_assets WHERE user_id = ?');
        $st->execute([$userId]);
        $assetCount = (int) $st->fetchColumn();
        $hasColumnText = false;
        for ($i = 1; $i <= 3; $i++) {
            $txt = trim((string) ($settings->get($pdo, 'column_text_' . $i, '', $userId) ?? ''));
            if ($txt !== '') {
                $hasColumnText = true;
                break;
            }
        }
        $designDone = $assetCount > 0 || $hasColumnText;

        $defaultStep = 1;
        if ($hasImport && !$designDone) {
            $defaultStep = 2;
        } elseif ($hasImport && $designDone) {
            $defaultStep = 3;
        }

        return [
            'has_import' => $hasImport,
            'design_done' => $designDone,
            'step1_done' => $hasImport,
            'step2_done' => $designDone,
            'step3_ready' => $hasImport && $designDone,
            'default_step' => $defaultStep,
        ];
    }
}

