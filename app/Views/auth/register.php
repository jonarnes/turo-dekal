<?php
/** @var string $urlBase */
/** @var string|null $error */
/** @var string|null $success */
/** @var string $csrf */
$ub = $urlBase;
?>
<h2>Opprett bruker</h2>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (!empty($success)): ?><p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<form method="post" action="<?= htmlspecialchars($ub) ?>/index.php?route=register_submit" class="stack auth-card">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
  <label>Brukernavn <input type="text" name="username" required></label>
  <label>E-post <input type="email" name="email" required></label>
  <label>Passord (minst 8 tegn) <input type="password" name="password" required></label>
  <button class="btn primary" type="submit">Opprett bruker</button>
  <a href="<?= htmlspecialchars($ub) ?>/index.php?route=login">Tilbake til innlogging</a>
</form>

