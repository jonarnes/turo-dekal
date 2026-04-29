<?php
/** @var string $urlBase */
/** @var list<array{id:int,filename:string,stored_path:string,column_index:int,sort_order:int}> $assets */
/** @var string|null $error */
/** @var string|null $success */
/** @var string $csrf */
/** @var array<int, array{available_mm:float,total_mm:float,overflow:bool,scale:float}> $warnings */
/** @var array<int, string> $columnTexts */
/** @var array<int, list<string>> $columnLayouts */
$ub = $urlBase;
$layoutImgBase = ($ub !== '' ? $ub : '') . '/index.php?route=layout_image&id=';
$assetMap = [];
foreach ($assets as $a) {
    $assetMap[(int) $a['id']] = $a;
}
?>
<p>Dra bilder til kolonne 1, 2 eller 3 (hver kolonne er 5 cm på dekalen). Midtkolonnen viser QR og tekst; bilder i kolonne 2 vises over QR. Oppsettet lagres automatisk når du slipper.</p>
<p class="notice">Hvis total bildehøyde i en kolonne er større enn tilgjengelig høyde, blir alle bilder i kolonnen skalert proporsjonalt ned i PDF.</p>

<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php foreach ([1, 2, 3] as $col): ?>
    <?php if (!empty($warnings[$col]['overflow'])): ?>
        <p class="warning">
            Kolonne <?= $col ?> har <?= htmlspecialchars((string) $warnings[$col]['total_mm'], ENT_QUOTES, 'UTF-8') ?> mm bilder, men kun
            <?= htmlspecialchars((string) $warnings[$col]['available_mm'], ENT_QUOTES, 'UTF-8') ?> mm tilgjengelig høyde.
            PDF vil auto-resize bilder i kolonnen med faktor <?= htmlspecialchars((string) $warnings[$col]['scale'], ENT_QUOTES, 'UTF-8') ?>.
        </p>
    <?php endif; ?>
<?php endforeach; ?>

<div class="config-layout" id="config-root"
     data-upload-url="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=config_upload_image"
     data-delete-url="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=config_delete_image"
     data-save-url="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=config_save_layout"
     data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
     data-url-base="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>">

    <div class="preview-wrap">
        <h3>Design dekalen</h3>
        <p class="muted">Dra bilder mellom kolonnene direkte i forhåndsvisningen. Skriv tekst inline i hver kolonne. Alt lagres automatisk.</p>
        <div class="preview-a5">
            <?php for ($c = 1; $c <= 3; $c++): ?>
                <div class="preview-col <?= $c === 2 ? 'preview-mid' : '' ?>" data-column="<?= $c ?>" aria-label="Kolonne <?= $c ?>">
                    <div class="preview-col-title-row">
                        <div class="preview-col-title">Kolonne <?= $c ?></div>
                        <label class="btn small file inline-upload">
                            <span>Last opp bilde</span>
                            <input type="file" class="inline-image-input" data-upload-column="<?= $c ?>" accept="image/jpeg,image/png,image/gif,image/webp">
                        </label>
                    </div>
                    <div class="preview-images drop-inner" data-preview-images="<?= $c ?>" data-drop-inner>
                        <?php foreach (($columnLayouts[$c] ?? []) as $token): ?>
                            <?php if (preg_match('/^img:(\d+)$/', $token, $m) === 1): ?>
                                <?php $id = (int) $m[1]; $a = $assetMap[$id] ?? null; if ($a === null) {
                                    continue;
                                } ?>
                                <div class="asset-card preview-card" draggable="false" data-id="<?= (int) $a['id'] ?>" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" data-stored-path="<?= htmlspecialchars((string) $a['stored_path'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="preview-card-head">
                                        <button type="button" class="drag-handle" draggable="true" aria-label="Dra for å flytte">⋮⋮</button>
                                    </div>
                                    <img class="preview-image" draggable="false" src="<?= htmlspecialchars($layoutImgBase . (int) $a['id'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                    <div class="asset-card-meta">
                                        <span class="name"><?= htmlspecialchars($a['filename'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <button type="button" class="btn small danger btn-remove" data-id="<?= (int) $a['id'] ?>">Slett</button>
                                    </div>
                                </div>
                            <?php elseif ($token === 'qr' && $c === 2): ?>
                                <div class="asset-card preview-card special-card" draggable="false" data-token="qr">
                                    <div class="special-card-head">
                                        <span class="special-title">QR-blokk</span>
                                        <button type="button" class="drag-handle" draggable="true" aria-label="Dra for å flytte">⋮⋮</button>
                                    </div>
                                    <div class="preview-scan">Scan med TurO-appen</div>
                                    <div class="preview-qr">QR plassholder</div>
                                    <div class="preview-meta">
                                        <div>Kode: <strong>{{kode_fra_excel}}</strong></div>
                                        <div>Tur: {{tur_fra_excel}}</div>
                                        <div>Beskrivelse: {{beskrivelse_fra_excel}}</div>
                                        <div>Poeng: {{poeng_fra_excel}}</div>
                                    </div>
                                </div>
                            <?php elseif ($token === 'text'): ?>
                                <div class="asset-card preview-card special-card" draggable="false" data-token="text">
                                    <div class="special-card-head">
                                        <span class="special-title">Tekst</span>
                                        <button type="button" class="drag-handle" draggable="true" aria-label="Dra for å flytte">⋮⋮</button>
                                    </div>
                                    <label class="muted preview-text-label" for="column-text-<?= $c ?>">Tekst kolonne <?= $c ?></label>
                                    <textarea id="column-text-<?= $c ?>" rows="4" class="column-text-input preview-text-input" draggable="false" data-column-text="<?= $c ?>" placeholder="Skriv tekst for kolonne <?= $c ?>"><?= htmlspecialchars((string) ($columnTexts[$c] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/assets/config.js"></script>
