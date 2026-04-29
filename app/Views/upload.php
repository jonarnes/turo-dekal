<?php
/** @var string $urlBase */
/** @var string|null $error */
/** @var string|null $success */
/** @var string $csrf */
$ub = $urlBase;
?>
<p>Last ned excel-filen fra idrettenonline.no. Kun <code>.xlsx</code>. Overskriftsrad må inneholde kolonnene <strong>Kode</strong> og <strong>QR</strong> (øvrige felt valgfrie men anbefalt).</p>

<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($ub) ?>/index.php?route=upload" enctype="multipart/form-data" class="stack">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label class="file">
        <span>Velg fil</span>
        <input type="file" name="excel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    </label>
    <button type="submit" class="btn primary">Importer</button>
</form>
