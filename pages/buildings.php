<?php
declare(strict_types=1);

function buildings_normalize_name(?string $value): string
{
    return trim((string)$value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Nieprawidłowy token formularza.');
        redirect('index.php?page=buildings');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save_building') {
            $id = (int)($_POST['id'] ?? 0);
            $name = buildings_normalize_name($_POST['name'] ?? '');

            if ($name === '') {
                throw new RuntimeException('Podaj nazwę budynku.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE buildings
                    SET name = :name
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                ]);

                set_flash('success', 'Zaktualizowano budynek.');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO buildings (name, is_active)
                    VALUES (:name, 1)
                ");
                $stmt->execute([
                    ':name' => $name,
                ]);

                set_flash('success', 'Dodano budynek.');
            }

            redirect('index.php?page=buildings');
        }

        if ($action === 'delete_building') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('Nieprawidłowy budynek.');
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM `keys`
                WHERE building_id = :id
                  AND is_active = 1
            ");
            $stmt->execute([':id' => $id]);
            $keysCount = (int)$stmt->fetchColumn();

            if ($keysCount > 0) {
                throw new RuntimeException('Nie można usunąć budynku, do którego są przypisane aktywne klucze.');
            }

            $stmt = $pdo->prepare("
                UPDATE buildings
                SET is_active = 0
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);

            set_flash('success', 'Usunięto budynek.');
            redirect('index.php?page=buildings');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect('index.php?page=buildings');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editBuilding = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, name
        FROM buildings
        WHERE id = :id
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':id' => $editId]);
    $editBuilding = $stmt->fetch() ?: null;

    if (!$editBuilding) {
        set_flash('danger', 'Nie znaleziono budynku.');
        redirect('index.php?page=buildings');
    }
}

$showModal = isset($_GET['new']) || $editBuilding !== null;

$stmt = $pdo->query("
    SELECT
        b.id,
        b.name,
        b.created_at,
        b.updated_at,
        COUNT(k.id) AS keys_count
    FROM buildings b
    LEFT JOIN `keys` k
        ON k.building_id = b.id
       AND k.is_active = 1
    WHERE b.is_active = 1
    GROUP BY b.id, b.name, b.created_at, b.updated_at
    ORDER BY b.name
");
$buildings = $stmt->fetchAll();

$modalId = $editBuilding ? (int)$editBuilding['id'] : 0;
$modalName = $editBuilding ? (string)$editBuilding['name'] : '';
?>

<div class="d-flex align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Budynki</h1>
        <div class="text-muted">Zarządzanie budynkami używanymi przez klucze</div>
    </div>

    <a href="index.php?page=buildings&new=1" class="btn btn-primary">
        Nowy
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Lista budynków</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle buildings-table">
                <thead>
                    <tr>
                        <th style="width: 90px;">ID</th>
                        <th>Nazwa</th>
                        <th style="width: 160px;">Aktywne klucze</th>
                        <th style="width: 180px;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($buildings === []): ?>
                        <tr>
                            <td colspan="4" class="text-muted">Brak budynków.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($buildings as $building): ?>
                        <?php
                        $id = (int)$building['id'];
                        $name = (string)$building['name'];
                        $keysCount = (int)$building['keys_count'];
                        ?>
                        <tr>
                            <td><?= $id ?></td>
                            <td class="fw-semibold"><?= e($name) ?></td>
                            <td><?= $keysCount ?></td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="index.php?page=buildings&edit=<?= $id ?>" class="btn btn-outline-primary btn-sm">
                                        Edytuj
                                    </a>

                                    <form method="post" class="d-inline" onsubmit="return confirm('Usunąć budynek: <?= e($name) ?>?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="delete_building">
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" <?= $keysCount > 0 ? 'disabled' : '' ?>>
                                            Usuń
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-muted small mt-2">
            Budynku nie można usunąć, jeśli są do niego przypisane aktywne klucze.
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="buildingModal"
    tabindex="-1"
    aria-hidden="true"
    data-show-on-load="<?= $showModal ? '1' : '0' ?>"
>
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_building">
                <input type="hidden" name="id" value="<?= $modalId ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><?= $modalId > 0 ? 'Edytuj budynek' : 'Nowy budynek' ?></h5>
                    <a href="index.php?page=buildings" class="btn-close" aria-label="Zamknij"></a>
                </div>

                <div class="modal-body">
                    <label class="form-label">Nazwa</label>
                    <input type="text" name="name" class="form-control" value="<?= e($modalName) ?>" maxlength="100" required>
                </div>

                <div class="modal-footer">
                    <a href="index.php?page=buildings" class="btn btn-outline-secondary">Anuluj</a>
                    <button type="submit" class="btn btn-primary">Zapisz</button>
                </div>
            </form>
        </div>
    </div>
</div>
