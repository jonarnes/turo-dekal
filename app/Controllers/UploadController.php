<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\Database;
use App\Services\ExcelImportService;
use App\Services\WizardService;
use RuntimeException;

final class UploadController
{
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
        $wizard = (new WizardService())->state($pdo, $userId);
        View::render('upload', [
            'title' => 'Excel-opplasting',
            'urlBase' => $this->urlBase,
            'wizard' => $wizard,
            'activeStep' => 1,
            'error' => $_SESSION['flash_error'] ?? null,
            'success' => $_SESSION['flash_success'] ?? null,
            'csrf' => Csrf::token(),
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    }

    public function handlePost(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Ugyldig sikkerhetstoken (CSRF).';
            $this->redirect();
            return;
        }
        if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Ingen fil lastet opp.';
            $this->redirect();
            return;
        }
        $f = $_FILES['excel'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Opplasting feilet (kode ' . (string) $f['error'] . ').';
            $this->redirect();
            return;
        }
        if ($f['size'] > $this->config['upload_max_bytes']) {
            $_SESSION['flash_error'] = 'Filen er for stor.';
            $this->redirect();
            return;
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $_SESSION['flash_error'] = 'Kun .xlsx er støttet.';
            $this->redirect();
            return;
        }

        $base = $this->config['base_path'];
        $name = 'excel_' . bin2hex(random_bytes(8)) . '.xlsx';
        $rel = 'storage/uploads/excel/' . $name;
        $dest = $base . '/' . $rel;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            $_SESSION['flash_error'] = 'Kunne ikke lagre filen.';
            $this->redirect();
            return;
        }

        try {
            $pdo = Database::pdo($base);
            $svc = new ExcelImportService();
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $svc->importFile($pdo, $userId, $base, $rel, $f['name']);
            $_SESSION['flash_success'] = 'Excel er importert.';
        } catch (RuntimeException $e) {
            @unlink($dest);
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            @unlink($dest);
            $_SESSION['flash_error'] = 'Import feilet: ' . $e->getMessage();
        }
        $this->redirect();
    }

    private function redirect(): void
    {
        header('Location: ' . $this->urlBase . '/index.php?route=upload', true, 302);
        exit;
    }
}
