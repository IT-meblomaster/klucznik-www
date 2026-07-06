<?php
declare(strict_types=1);

if (!has_permission($pdo, 'pages.uprawnienia.view') && !has_permission($pdo, 'pages.uprawnienia.edit')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

$errors = [];
$editingPermissionId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$permissionToEdit = [
    'id' => 0,
    'name' => '',
    'description' => '',
];

$modalTitle = 'Dodaj uprawnienie';
$openModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission($pdo, 'pages.uprawnienia.edit')) {
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        return;
    }

    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_permission') {
        $permissionId = (int) ($_POST['permission_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            $errors[] = 'Nazwa uprawnienia jest wymagana.';
        } elseif (!preg_match('/^[a-z0-9_.-]+$/', $name)) {
            $errors[] = 'Nazwa może zawierać tylko małe litery, cyfry, kropkę, myślnik i podkreślenie.';
        }

        if (mb_strlen($name) > 150) {
            $errors[] = 'Nazwa uprawnienia może mieć maksymalnie 150 znaków.';
        }

        if (mb_strlen($description) > 255) {
            $errors[] = 'Opis może mieć maksymalnie 255 znaków.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('
                SELECT id
                FROM permissions
                WHERE name = :name
                  AND id != :id
                LIMIT 1
            ');
            $stmt->execute([
                'name' => $name,
                'id' => $permissionId,
            ]);

            if ($stmt->fetch()) {
                $errors[] = 'Uprawnienie o takiej nazwie już istnieje.';
            }
        }

        if (!$errors) {
            try {
                if ($permissionId > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE permissions
                        SET name = :name,
                            description = :description
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description,
                        'id' => $permissionId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO permissions (name, description)
                        VALUES (:name, :description)
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description,
                    ]);
                }

                set_flash('success', 'Uprawnienie zostało zapisane.');
                ?>
                <script>
                window.location.replace('index.php?page=permissions');
                </script>
                <noscript>
                    <meta http-equiv="refresh" content="0;url=index.php?page=permissions">
                </noscript>
                <?php
                return;
            } catch (Throwable $e) {
                $errors[] = 'Nie udało się zapisać uprawnienia.';
            }
        }
    }

    if ($action === 'delete_permission') {
        $permissionId = (int) ($_POST['permission_id'] ?? 0);

        $stmt = $pdo->prepare('
            SELECT id, name
            FROM permissions
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $permissionId]);
        $permission = $stmt->fetch();

        if (!$permission) {
            $errors[] = 'Nie znaleziono uprawnienia do usunięcia.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM page_permissions WHERE permission_id = :id');
            $stmt->execute(['id' => $permissionId]);
            $pageUsageCount = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM role_permissions WHERE permission_id = :id');
            $stmt->execute(['id' => $permissionId]);
            $roleUsageCount = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM menu_item_permissions WHERE permission_id = :id');
            $stmt->execute(['id' => $permissionId]);
            $menuUsageCount = (int) $stmt->fetchColumn();

            if ($pageUsageCount > 0 || $roleUsageCount > 0 || $menuUsageCount > 0) {
                $errors[] = 'Nie można usunąć uprawnienia, które jest używane na stronach, w rolach lub w menu.';
            }
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('DELETE FROM permissions WHERE id = :id');
                $stmt->execute(['id' => $permissionId]);

                set_flash('success', 'Uprawnienie zostało usunięte.');
                ?>
                <script>
                window.location.replace('index.php?page=permissions');
                </script>
                <noscript>
                    <meta http-equiv="refresh" content="0;url=index.php?page=permissions">
                </noscript>
                <?php
                return;
            } catch (Throwable $e) {
                $errors[] = 'Nie udało się usunąć uprawnienia.';
            }
        }
    }
}

if ($editingPermissionId > 0) {
    $stmt = $pdo->prepare('
        SELECT id, name, description
        FROM permissions
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $editingPermissionId]);
    $found = $stmt->fetch();

    if ($found) {
        $permissionToEdit = $found;
        $modalTitle = 'Edytuj uprawnienie: ' . $permissionToEdit['name'];
        $openModal = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    $permissionToEdit = [
        'id' => (int) ($_POST['permission_id'] ?? 0),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
    ];
    $modalTitle = $permissionToEdit['id'] > 0 ? 'Edytuj uprawnienie' : 'Dodaj uprawnienie';
    $openModal = true;
}

$permissions = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.description,
        p.created_at,
        p.updated_at,
        COUNT(DISTINCT pp.page_id) AS pages_count,
        COUNT(DISTINCT rp.role_id) AS roles_count,
        COUNT(DISTINCT mip.menu_item_id) AS menu_items_count
    FROM permissions p
    LEFT JOIN page_permissions pp
        ON pp.permission_id = p.id
    LEFT JOIN role_permissions rp
        ON rp.permission_id = p.id
    LEFT JOIN menu_item_permissions mip
        ON mip.permission_id = p.id
    GROUP BY
        p.id,
        p.name,
        p.description,
        p.created_at,
        p.updated_at
    ORDER BY p.name
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Typy uprawnień</h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted mb-3">
            Tutaj zarządzasz słownikiem typów uprawnień. Przypisywanie uprawnień do stron, menu i ról odbywa się w odpowiednich sekcjach systemu.
        </p>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$permissions): ?>
            <p class="mb-0">Brak zdefiniowanych uprawnień.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle permissions-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa</th>
                        <th>Opis</th>
                        <th>Strony</th>
                        <th>Menu</th>
                        <th>Role</th>
                        <th>Utworzono</th>
                        <th>Zmieniono</th>
                        <th class="text-end">Akcje</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($permissions as $permissionRow): ?>
                        <?php
                        $usageCount =
                            (int) $permissionRow['pages_count']
                            + (int) $permissionRow['menu_items_count']
                            + (int) $permissionRow['roles_count'];
                        ?>
                        <tr>
                            <td><?= (int) $permissionRow['id'] ?></td>
                            <td><code><?= e($permissionRow['name']) ?></code></td>
                            <td><?= e($permissionRow['description'] ?: '-') ?></td>
                            <td>
                                <span class="badge <?= (int) $permissionRow['pages_count'] > 0 ? 'text-bg-info' : 'text-bg-secondary' ?>">
                                    <?= (int) $permissionRow['pages_count'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (int) $permissionRow['menu_items_count'] > 0 ? 'text-bg-info' : 'text-bg-secondary' ?>">
                                    <?= (int) $permissionRow['menu_items_count'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (int) $permissionRow['roles_count'] > 0 ? 'text-bg-info' : 'text-bg-secondary' ?>">
                                    <?= (int) $permissionRow['roles_count'] ?>
                                </span>
                            </td>
                            <td><small class="text-muted"><?= e($permissionRow['created_at']) ?></small></td>
                            <td><small class="text-muted"><?= e($permissionRow['updated_at']) ?></small></td>
                            <td class="text-end">
                                <?php if (has_permission($pdo, 'pages.uprawnienia.edit')): ?>
                                    <div class="d-inline-flex gap-1">
                                        <a href="index.php?page=permissions&edit=<?= (int) $permissionRow['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            Edytuj
                                        </a>

                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Usunąć uprawnienie „<?= e(addslashes($permissionRow['name'])) ?>”?');">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_permission">
                                            <input type="hidden" name="permission_id" value="<?= (int) $permissionRow['id'] ?>">
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                <?= $usageCount > 0 ? 'disabled title="Nie można usunąć używanego uprawnienia"' : '' ?>
                                            >
                                                Usuń
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (has_permission($pdo, 'pages.uprawnienia.edit')): ?>
            <div class="mt-3">
                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#permissionModal"
                >
                    Dodaj uprawnienie
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (has_permission($pdo, 'pages.uprawnienia.edit')): ?>
    <div class="modal fade" id="permissionModal" tabindex="-1" aria-labelledby="permissionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable permissions-modal-dialog">
            <div class="modal-content permissions-modal-content">
                <form method="post" class="permissions-modal-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_permission">
                    <input type="hidden" name="permission_id" value="<?= (int) $permissionToEdit['id'] ?>">

                    <div class="modal-header">
                        <h5 class="modal-title" id="permissionModalLabel"><?= e($modalTitle) ?></h5>
                        <a href="index.php?page=permissions" class="btn-close"></a>
                    </div>

                    <div class="modal-body permissions-modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nazwa <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    value="<?= e($permissionToEdit['name']) ?>"
                                    placeholder="np. pages.view"
                                    pattern="[a-z0-9_.-]+"
                                    maxlength="150"
                                    title="Małe litery, cyfry, kropka, myślnik i podkreślenie"
                                    required
                                >
                                <div class="form-text">
                                    Techniczna nazwa uprawnienia, np. <code>pages.view</code>, <code>pages.manage</code>, <code>users.view</code>.
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Opis</label>
                                <textarea
                                    name="description"
                                    class="form-control"
                                    rows="3"
                                    maxlength="255"
                                    placeholder="Krótki opis, do czego służy to uprawnienie"
                                ><?= e($permissionToEdit['description']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer permissions-modal-footer">
                        <a href="index.php?page=permissions" class="btn btn-outline-secondary">Anuluj</a>
                        <button type="submit" class="btn btn-primary">Zapisz uprawnienie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($openModal): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('permissionModal');
                if (modalEl) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    <?php endif; ?>
<?php endif; ?>