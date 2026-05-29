<?php
/** @var string $urlBase */
/** @var list<array{id:int,username:string,email:string,created_at:string,dekal_count:int,import_count:int,is_admin:bool}> $users */
/** @var int $totalDekaler */
$ub = $urlBase;
?>

<h2>Admin - Brukeroversikt</h2>

<div class="admin-stats">
    <p><strong>Totalt antall dekaler i systemet:</strong> <?= (int) $totalDekaler ?></p>
    <p><strong>Totalt antall brukere:</strong> <?= count($users) ?></p>
</div>

<?php if (empty($users)): ?>
    <p>Ingen brukere registrert ennå.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Bruker</th>
                <th>E-post</th>
                <th>Registrert</th>
                <th>Dekaler</th>
                <th>Importer</th>
                <th>Admin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $user['dekal_count'] ?></td>
                    <td><?= (int) $user['import_count'] ?></td>
                    <td><?= $user['is_admin'] ? '✓' : '' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="<?= htmlspecialchars($ub, ENT_QUOTES, 'UTF-8') ?>/index.php?route=home" class="btn">Tilbake til hovedside</a></p>

<style>
    .admin-stats {
        background: #f0f0f0;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .admin-table th,
    .admin-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .admin-table th {
        background: #f8f8f8;
        font-weight: bold;
    }
    .admin-table tr:hover {
        background: #f5f5f5;
    }
</style>
