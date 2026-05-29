<?php
/** @var string $___viewFile */
/** @var string $urlBase */
/** @var array{
 * has_import?:bool,
 * design_done?:bool,
 * step1_done?:bool,
 * step2_done?:bool,
 * step3_ready?:bool
 * } $wizard
 */
/** @var int $activeStep */
$title = $title ?? 'Turo-dekal';
$ub = $urlBase ?? '';
$wizard = $wizard ?? [];
$activeStep = (int) ($activeStep ?? 0);
$step1Done = !empty($wizard['step1_done']);
$step2Done = !empty($wizard['step2_done']);
$step3Ready = !empty($wizard['step3_ready']);
$loggedIn = !empty($_SESSION['user_id']);

$nextActionStep = 1;
if ($step1Done) {
    $nextActionStep = $step2Done ? 3 : 2;
}

$wizardTabClass = static function (int $step, int $activeStep, bool $done, bool $locked, int $nextActionStep): string {
    $c = ['wizard-tab'];
    if ($activeStep === $step) {
        $c[] = 'active';
    }
    if ($done) {
        $c[] = 'complete';
    }
    if (!$locked && $nextActionStep === $step && !$done && $activeStep !== $step) {
        $c[] = 'action-needed';
    }
    if ($locked) {
        $c[] = 'locked';
    }
    return implode(' ', $c);
};
?>
<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/assets/style.css">
</head>
<body>
<header class="site-header">
    <h1 class="brand"><a href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php">Turo-dekal</a></h1>
    <?php if ($loggedIn): ?>
        <?php if (!empty($isAdmin)): ?>
            <a class="btn small" href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=admin">Admin</a>
        <?php endif; ?>
        <a class="btn small" href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=logout">Logg ut</a>
    <?php endif; ?>
</header>
<main class="main">
<?php if ($loggedIn): ?>
    <nav class="wizard-tabs" aria-label="Wizard steg">
        <a class="<?= $wizardTabClass(1, $activeStep, $step1Done, false, $nextActionStep) ?>"
           data-step-num="1"
           href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=upload">
            <span class="step">Steg 1</span>
            <span class="label">Last opp Excel</span>
            <span class="status"><?= $step1Done ? '✓ Ferdig' : ($activeStep === 1 ? '→ Gjør dette nå' : 'Påkrevd') ?></span>
        </a>
        <a class="<?= $wizardTabClass(2, $activeStep, $step2Done, !$step1Done, $nextActionStep) ?>"
           data-step-num="2"
           href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=config">
            <span class="step">Steg 2</span>
            <span class="label">Design dekaler</span>
            <span class="status"><?= $step2Done ? '✓ Ferdig' : (!$step1Done ? 'Venter på steg 1' : ($activeStep === 2 ? '→ Gjør dette nå' : 'Påkrevd')) ?></span>
        </a>
        <a class="<?= $wizardTabClass(3, $activeStep, false, !$step3Ready, $nextActionStep) ?>"
           data-step-num="3"
           href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=home">
            <span class="step">Steg 3</span>
            <span class="label">Generer PDF</span>
            <span class="status"><?= $step3Ready ? ($activeStep === 3 ? '→ Gjør dette nå' : 'Klar — klikk her') : 'Venter på steg 1–2' ?></span>
        </a>
    </nav>

    <details class="wizard-guide">
        <summary>
            Fremgangsmåte
            <span class="wizard-guide-hint">klikk for detaljer</span>
        </summary>
        <div class="wizard-guide-body">
            <div class="wizard-guide-step">
                <h3>Steg 1 — Excel</h3>
                <p>Gå til idrettenonline og last ned excel-filen med posterene. Filen må inneholde kolonnene Tur, Navn, Kode, Poeng, QR og Beskrivelse.</p>
            </div>
            <div class="wizard-guide-step">
                <h3>Steg 2 — Design</h3>
                <p>Design dekalen. Last opp logoer og legg til evt. tekst.</p>
            </div>
            <div class="wizard-guide-step">
                <h3>Steg 3 — PDF</h3>
                <p>Velg dekaler og generer PDF. Skriv ut på <a href="https://www.nortea.no/products/outdoor-selvklebende-folie-10-ark-210x148-hvit-20-stk" target="_blank" rel="noopener">vannfast papir</a>. Dekalene passer 48×48 lekter — tre sider med bilder, bakerste side tom.</p>
            </div>
        </div>
    </details>
<?php endif; ?>
<?php include $___viewFile; ?>
</main>
</body>
</html>
