<?php
declare(strict_types=1);

if (!has_permission($pdo, 'pages.role.view') && !has_permission($pdo, 'pages.role.edit')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

$errors = [];
$editingRoleId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission($pdo, 'pages.role.edit')) {
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        return;
    }

    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_role') {
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $permissionIds = array_values(array_unique(array_map('intval', $_POST['permissions'] ?? [])));

        if ($name === '') {
            $errors[] = 'Nazwa roli jest wymagana.';
        }

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name AND id != :id LIMIT 1');
        $stmt->execute([
            'name' => $name,
            'id' => $roleId,
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Rola o takiej nazwie już istnieje.';
        }

        if (!$errors) {
            $pdo->beginTransaction();

            try {
                if ($roleId > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE roles
                        SET name = :name,
                            description = :description
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description,
                        'id' => $roleId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO roles (name, description)
                        VALUES (:name, :description)
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description,
                    ]);
                    $roleId = (int) $pdo->lastInsertId();
                }

                $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')
                    ->execute(['role_id' => $roleId]);

                if ($permissionIds !== []) {
                    $stmt = $pdo->prepare('
                        INSERT INTO role_permissions (role_id, permission_id)
                        VALUES (:role_id, :permission_id)
                    ');

                    foreach ($permissionIds as $permissionId) {
                        $stmt->execute([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Rola została zapisana.');
                ?>
                <script>
                window.location.replace('index.php?page=roles');
                </script>
                <noscript>
                    <meta http-equiv="refresh" content="0;url=index.php?page=roles">
                </noscript>
                <?php
                return;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Nie udało się zapisać roli.';
            }
        }
    }
}

$roleToEdit = [
    'id' => 0,
    'name' => '',
    'description' => '',
];

$rolePermissionIds = [];
$modalTitle = 'Dodaj nową rolę';
$openModal = false;

if ($editingRoleId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editingRoleId]);
    $found = $stmt->fetch();

    if ($found) {
        $roleToEdit = $found;
        $modalTitle = 'Edytuj rolę: ' . ($roleToEdit['name'] ?: ('#' . $roleToEdit['id']));
        $openModal = true;

        $stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id');
        $stmt->execute(['role_id' => $editingRoleId]);
        $rolePermissionIds = array_map('intval', array_column($stmt->fetchAll(), 'permission_id'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    $roleToEdit = [
        'id' => (int) ($_POST['role_id'] ?? 0),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
    ];
    $rolePermissionIds = array_map('intval', $_POST['permissions'] ?? []);
    $modalTitle = $roleToEdit['id'] > 0 ? 'Edytuj rolę' : 'Dodaj nową rolę';
    $openModal = true;
}

$roles = $pdo->query("
    SELECT
        r.id,
        r.name,
        r.description
    FROM roles r
    ORDER BY
        r.name
")->fetchAll();

$permissionsByRole = [];

$rolePermissionsRows = $pdo->query("
    SELECT
        rp.role_id,
        p.name,
        p.description
    FROM role_permissions rp
    INNER JOIN permissions p ON p.id = rp.permission_id
    ORDER BY
        rp.role_id,
        p.name
")->fetchAll();

foreach ($rolePermissionsRows as $permissionRow) {
    $roleId = (int) $permissionRow['role_id'];
    $permissionName = trim((string) ($permissionRow['name'] ?? ''));
    $permissionDescription = trim((string) ($permissionRow['description'] ?? ''));

    if ($permissionName === '') {
        continue;
    }

    $permissionsByRole[$roleId][] = [
        'name' => $permissionName,
        'description' => $permissionDescription,
    ];
}

$permissions = $pdo->query("
    SELECT id, name, description
    FROM permissions
    ORDER BY name
")->fetchAll();

$formatPermissionLabel = static function (array $permission): string {
    $permissionName = trim((string) ($permission['name'] ?? ''));
    $permissionDescription = trim((string) ($permission['description'] ?? ''));

    if ($permissionDescription !== '') {
        return $permissionName . ' (' . $permissionDescription . ')';
    }

    return $permissionName;
};
?>

<style>
    .roles-table {
        table-layout: fixed;
        width: 100%;
    }

    .roles-name-col {
        width: 30%;
        min-width: 220px;
    }

    .roles-description-col {
        width: 55%;
        min-width: 300px;
    }


    .roles-actions-col {
        width: 15%;
        min-width: 90px;
    }

    .roles-table td,
    .roles-table th {
        vertical-align: top;
    }

    .roles-cell-name,
    .roles-cell-description {
        overflow-wrap: anywhere;
        word-break: normal;
    }



    .roles-permission-check {
        margin-bottom: 0.25rem;
    }

    .roles-permission-check .form-check-label {
        overflow-wrap: anywhere;
        word-break: normal;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Role</h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (!$roles): ?>
            <p class="mb-0">Brak zdefiniowanych ról.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle roles-table">
                    <colgroup>
                        <col class="roles-name-col">
                        <col class="roles-description-col">
                        <col class="roles-actions-col">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Opis</th>
                        <th class="text-end">Akcje</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($roles as $roleRow): ?>
                        <tr>
                            <td class="roles-cell-name"><?= e($roleRow['name']) ?></td>
                            <td class="roles-cell-description"><?= e($roleRow['description'] ?: '-') ?></td>
                            <td class="text-end">
                                <?php if (has_permission($pdo, 'pages.role.edit')): ?>
                                    <a href="index.php?page=roles&edit=<?= (int) $roleRow['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Edytuj
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (has_permission($pdo, 'pages.role.edit')): ?>
                <div class="mt-3">
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#roleModal"
                    >
                        Dodaj nową rolę
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (has_permission($pdo, 'pages.role.edit')): ?>
    <div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable permissions-modal-dialog">
            <div class="modal-content permissions-modal-content">
                <form method="post" class="permissions-modal-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_role">
                    <input type="hidden" name="role_id" value="<?= (int) $roleToEdit['id'] ?>">

                    <div class="modal-header">
                        <h5 class="modal-title" id="roleModalLabel"><?= e($modalTitle) ?></h5>
                        <a href="index.php?page=roles" class="btn-close"></a>
                    </div>

                    <div class="modal-body permissions-modal-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nazwa</label>
                                <input type="text" name="name" class="form-control" value="<?= e($roleToEdit['name']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Opis</label>
                                <input type="text" name="description" class="form-control" value="<?= e($roleToEdit['description']) ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Uprawnienia</label>
                                <div class="border rounded p-3 permissions-list-box">
                                    <?php foreach ($permissions as $permission): ?>
                                        <div class="form-check roles-permission-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="permissions[]"
                                                value="<?= (int) $permission['id'] ?>"
                                                id="role_perm_<?= (int) $permission['id'] ?>"
                                                <?= in_array((int) $permission['id'], $rolePermissionIds, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label" for="role_perm_<?= (int) $permission['id'] ?>">
                                                <?= e($formatPermissionLabel($permission)) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer permissions-modal-footer">
                        <a href="index.php?page=roles" class="btn btn-outline-secondary">Anuluj</a>
                        <button type="submit" class="btn btn-primary">Zapisz rolę</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($openModal): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('roleModal');
                if (modalEl) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    <?php endif; ?>
<?php endif; ?>