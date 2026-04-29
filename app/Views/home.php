<?php
/** @var string $urlBase */
/** @var int $postCount */
/** @var bool $hasImport */
/** @var list<array{kode:string,tur:string,navn:string,beskrivelse:string}> $posts */
$ub = $urlBase;
$turNames = [];
foreach ($posts as $p) {
    $tur = trim((string) ($p['tur'] ?? ''));
    if ($tur !== '') {
        $turNames[$tur] = true;
    }
}
$turOptions = array_keys($turNames);
sort($turOptions, SORT_NATURAL | SORT_FLAG_CASE);
?>
<p>Last opp en Excel-fil med kolonnene Tur, Navn, Kode, Poeng, QR og Beskrivelse. Generer deretter PDF med to liggende A5-dekaler per A4-ark.</p>

<?php if (!$hasImport): ?>
    <p class="notice">Ingen import ennå. Gå til <a href="<?= htmlspecialchars($ub) ?>/index.php?route=upload">Excel-opplasting</a>.</p>
<?php else: ?>
    <p><strong><?= (int) $postCount ?></strong> poster i aktiv import.</p>

    <h3>Valgte dekaler (filter på Kode)</h3>
    <form method="get" action="<?= htmlspecialchars($ub) ?>/index.php" class="post-pick">
        <input type="hidden" name="route" value="pdf">
        <div class="post-toolbar">
            <label for="tur-filter">Filtrer på Tur:</label>
            <select id="tur-filter">
                <option value="">Alle turer</option>
                <?php foreach ($turOptions as $tur): ?>
                    <option value="<?= htmlspecialchars($tur, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tur, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn small" id="select-all-btn">Velg alle</button>
            <button type="button" class="btn small" id="select-none-btn">Velg ingen</button>
            <span class="muted" id="post-visible-count"></span>
        </div>
        <div class="post-grid">
            <?php foreach ($posts as $p): ?>
                <label class="post-row" data-tur="<?= htmlspecialchars((string) ($p['tur'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="checkbox" name="koder[]" value="<?= htmlspecialchars($p['kode'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="mono"><?= htmlspecialchars($p['kode'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="muted"><?= htmlspecialchars($p['tur'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <span><?= htmlspecialchars($p['navn'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="muted clamp-2"><?= htmlspecialchars($p['beskrivelse'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <p><button type="submit" class="btn">Last ned valgte (PDF)</button></p>
    </form>
    <script>
      (function () {
        const filter = document.getElementById('tur-filter');
        const selectAllBtn = document.getElementById('select-all-btn');
        const selectNoneBtn = document.getElementById('select-none-btn');
        const countEl = document.getElementById('post-visible-count');
        const rows = Array.from(document.querySelectorAll('.post-row[data-tur]'));

        function isVisible(el) {
          return el.style.display !== 'none';
        }

        function updateCount() {
          const visible = rows.filter(isVisible).length;
          countEl.textContent = visible + ' synlige poster';
        }

        function applyFilter() {
          const selected = filter.value;
          rows.forEach(function (row) {
            const tur = row.getAttribute('data-tur') || '';
            row.style.display = selected === '' || tur === selected ? '' : 'none';
          });
          updateCount();
        }

        selectAllBtn.addEventListener('click', function () {
          rows.forEach(function (row) {
            if (!isVisible(row)) return;
            const cb = row.querySelector('input[type="checkbox"]');
            if (cb) cb.checked = true;
          });
        });

        selectNoneBtn.addEventListener('click', function () {
          rows.forEach(function (row) {
            if (!isVisible(row)) return;
            const cb = row.querySelector('input[type="checkbox"]');
            if (cb) cb.checked = false;
          });
        });

        filter.addEventListener('change', applyFilter);
        applyFilter();
      })();
    </script>
<?php endif; ?>
