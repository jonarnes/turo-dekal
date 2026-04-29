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
        <a class="btn small" href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=logout">Logg ut</a>
    <?php endif; ?>
</header>
<main class="main">
<?php if ($loggedIn): ?>
    <section class="wizard-intro">
        <h1>Fremgangsmåte</h1>
        <div>
            <div class="wizard-step">
                <h3>Steg 1</h3>
                <p>Gå til idrettenonline og last ned excel-filen med posterene. Filen må inneholde kolonnener for Tur, Navn, Kode, Poeng, QR og Beskrivelse.</p>
            </div>
            <div class="wizard-step">
                <h3>Steg 2</h3>
                <p>Design dekalen. Last opp logoer og legg til evt. tekst.</p>
            </div>
            <div class="wizard-step">
                <h3>Steg 3</h3>
                <p>Generer PDF. Velg dekaler og generer PDF for valgte poster. Skriv ut <a style="color: var(--accent);" href="https://www.nortea.no/products/outdoor-selvklebende-folie-10-ark-210x148-hvit-20-stk" target="_blank">dekalene på vannfast papir.</a></p>
                    <p> Dekalene passer til 48X48 lekter/stolper. Tre sider med bilder, og den bakerste siden er tom.</p> 
            </div>
        </div>
    </section>
    <nav class="wizard-tabs" aria-label="Wizard steg">
        <a class="wizard-tab<?= $activeStep === 1 ? ' active' : '' ?>" href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=upload">
            <span class="step">Steg 1</span>
            <span class="label">Last opp Excel</span>
            <span class="status"><?= $step1Done ? 'Ferdig' : 'Påkrevd' ?></span>
        </a>
        <a class="wizard-tab<?= $activeStep === 2 ? ' active' : '' ?>" href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=config">
            <span class="step">Steg 2</span>
            <span class="label">Design dekaler</span>
            <span class="status"><?= $step2Done ? 'Ferdig' : 'Påkrevd' ?></span>
        </a>
        <a class="wizard-tab<?= $activeStep === 3 ? ' active' : '' ?><?= $step3Ready ? '' : ' pending' ?>" href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=home">
            <span class="step">Steg 3</span>
            <span class="label">Generer PDF</span>
            <span class="status"><?= $step3Ready ? 'Klar' : 'Venter på steg 1-2' ?></span>
        </a>
    </nav>
<?php endif; ?>
<?php include $___viewFile; ?>
</main>
</body>
</html>
