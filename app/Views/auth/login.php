<?php
/** @var string $urlBase */
/** @var string|null $error */
/** @var string|null $success */
/** @var string $csrf */
$ub = $urlBase;
?>
<h2>Logg inn</h2>
<p>Logg inn med brukernavn/e-post og passord, eller motta magisk lenke på e-post.</p>

<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if (!empty($success)): ?><p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<div class="auth-grid">
  <form method="post" action="<?= htmlspecialchars($ub) ?>/index.php?route=login_password" class="stack auth-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Brukernavn eller e-post <input type="text" name="username_or_email" required></label>
    <label>Passord <input type="password" name="password" required></label>
    <label><input type="checkbox" name="remember_me" value="1" checked> Husk meg i 2 uker</label>
    <button class="btn primary" type="submit">Logg inn</button>
    <a href="<?= htmlspecialchars($ub) ?>/index.php?route=register">Opprett ny bruker</a>
  </form>

  <form method="post" action="<?= htmlspecialchars($ub) ?>/index.php?route=magic_send" class="stack auth-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <h3>Magisk lenke</h3>
    <label>E-post <input type="email" name="email" required></label>
    <button class="btn" type="submit">Send magisk lenke</button>
  </form>
</div>

