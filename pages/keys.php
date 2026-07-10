<?php
declare(strict_types=1);

function keys_null_if_empty(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function keys_required_text(?string $value): string
{
    return trim((string)$value);
}

function keys_insert_log(PDO $pdo, int $keyId, ?int $rfidTagId, string $actionType, ?string $details): void
{
    $stmt = $pdo->prepare("
        INSERT INTO key_logs (key_id, rfid_tag_id, action_type, action_details)
        VALUES (:key_id, :rfid_tag_id, :action_type, :action_details)
    ");
    $stmt->execute([
        ':key_id' => $keyId,
        ':rfid_tag_id' => $rfidTagId,
        ':action_type' => $actionType,
        ':action_details' => keys_null_if_empty($details),
    ]);
}

function keys_get_key_name(PDO $pdo, int $keyId): string
{
    $stmt = $pdo->prepare("SELECT name FROM `keys` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $keyId]);

    return (string)($stmt->fetchColumn() ?: ('ID ' . $keyId));
}

function keys_sync_rfid(PDO $pdo, int $keyId, ?string $rfidCode): void
{
    $rfidCode = trim((string)$rfidCode);
    $keyName = keys_get_key_name($pdo, $keyId);

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.rfid_tag_id,
            r.rfid_code
        FROM key_rfid_assignments a
        INNER JOIN rfid_tags r
            ON r.id = a.rfid_tag_id
        WHERE a.key_id = :key_id
          AND a.assigned_to IS NULL
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([':key_id' => $keyId]);
    $currentAssignment = $stmt->fetch() ?: null;

    if ($rfidCode === '') {
        if (!$currentAssignment) {
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE key_rfid_assignments
            SET assigned_to = NOW(),
                unassigned_reason = 'MANUAL_REMOVE'
            WHERE id = :id
        ");
        $stmt->execute([':id' => (int)$currentAssignment['id']]);

        keys_insert_log(
            $pdo,
            $keyId,
            (int)$currentAssignment['rfid_tag_id'],
            'REMOVE_RFID',
            'Usunięto aktywne przypisanie RFID z klucza ' . $keyName
        );

        return;
    }

    if ($currentAssignment && (string)$currentAssignment['rfid_code'] === $rfidCode) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM rfid_tags
        WHERE rfid_code = :rfid_code
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([':rfid_code' => $rfidCode]);
    $rfidTagId = $stmt->fetchColumn();

    if (!$rfidTagId) {
        $stmt = $pdo->prepare("
            INSERT INTO rfid_tags (rfid_code, status)
            VALUES (:rfid_code, 'ACTIVE')
        ");
        $stmt->execute([':rfid_code' => $rfidCode]);
        $rfidTagId = (int)$pdo->lastInsertId();
    } else {
        $rfidTagId = (int)$rfidTagId;

        $stmt = $pdo->prepare("
            UPDATE rfid_tags
            SET status = 'ACTIVE',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute([':id' => $rfidTagId]);
    }

    $stmt = $pdo->prepare("
        SELECT key_id
        FROM key_rfid_assignments
        WHERE rfid_tag_id = :rfid_tag_id
          AND assigned_to IS NULL
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([':rfid_tag_id' => $rfidTagId]);
    $existingKeyId = $stmt->fetchColumn();

    if ($existingKeyId && (int)$existingKeyId !== $keyId) {
        throw new RuntimeException('To RFID jest już aktywnie przypisane do innego klucza.');
    }

    if ($currentAssignment) {
        $stmt = $pdo->prepare("
            UPDATE key_rfid_assignments
            SET assigned_to = NOW(),
                unassigned_reason = 'REASSIGN'
            WHERE id = :id
        ");
        $stmt->execute([':id' => (int)$currentAssignment['id']]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO key_rfid_assignments (key_id, rfid_tag_id, assigned_from, assigned_by, notes)
        VALUES (:key_id, :rfid_tag_id, NOW(), 'WWW', NULL)
    ");
    $stmt->execute([
        ':key_id' => $keyId,
        ':rfid_tag_id' => $rfidTagId,
    ]);

    keys_insert_log(
        $pdo,
        $keyId,
        $rfidTagId,
        'ASSIGN_RFID',
        'Przypisano RFID ' . $rfidCode . ' do klucza ' . $keyName
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Nieprawidłowy token formularza.');
        redirect('index.php?page=keys');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save_key') {
            $id = (int)($_POST['id'] ?? 0);
            $name = keys_required_text($_POST['name'] ?? '');
            $buildingId = (int)($_POST['building_id'] ?? 0);
            $hanger = keys_required_text($_POST['hanger'] ?? '');
            $description = keys_null_if_empty($_POST['description'] ?? null);
            $rfidCode = keys_null_if_empty($_POST['rfid_code'] ?? null);

            if ($name === '') {
                throw new RuntimeException('Podaj nazwę klucza.');
            }

            if ($buildingId <= 0) {
                throw new RuntimeException('Wybierz budynek.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM buildings
                WHERE id = :id
                  AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $buildingId]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Wybrany budynek nie istnieje albo jest nieaktywny.');
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE `keys`
                    SET name = :name,
                        building_id = :building_id,
                        zawieszka = :hanger,
                        description = :description
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':building_id' => $buildingId,
                    ':hanger' => $hanger,
                    ':description' => $description,
                ]);

                keys_insert_log($pdo, $id, null, 'UPDATE', 'Zaktualizowano klucz: ' . $name);
                keys_sync_rfid($pdo, $id, $rfidCode);

                set_flash('success', 'Zaktualizowano klucz.');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO `keys` (name, building_id, zawieszka, description, is_active)
                    VALUES (:name, :building_id, :hanger, :description, 1)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':building_id' => $buildingId,
                    ':hanger' => $hanger,
                    ':description' => $description,
                ]);

                $id = (int)$pdo->lastInsertId();

                keys_insert_log($pdo, $id, null, 'CREATE', 'Dodano klucz: ' . $name);
                keys_sync_rfid($pdo, $id, $rfidCode);

                set_flash('success', 'Dodano klucz.');
            }

            $pdo->commit();
            redirect('index.php?page=keys');
        }

        if ($action === 'delete_key') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('Nieprawidłowy klucz.');
            }

            $pdo->beginTransaction();

            $keyName = keys_get_key_name($pdo, $id);

            $stmt = $pdo->prepare("
                SELECT id, rfid_tag_id
                FROM key_rfid_assignments
                WHERE key_id = :key_id
                  AND assigned_to IS NULL
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':key_id' => $id]);
            $assignment = $stmt->fetch() ?: null;

            if ($assignment) {
                $stmt = $pdo->prepare("
                    UPDATE key_rfid_assignments
                    SET assigned_to = NOW(),
                        unassigned_reason = 'KEY_DELETE'
                    WHERE id = :id
                ");
                $stmt->execute([':id' => (int)$assignment['id']]);
            }

            $stmt = $pdo->prepare("UPDATE `keys` SET is_active = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);

            keys_insert_log($pdo, $id, null, 'DELETE', 'Usunięto klucz: ' . $keyName);

            $pdo->commit();
            set_flash('success', 'Usunięto klucz.');
            redirect('index.php?page=keys');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        set_flash('danger', $e->getMessage());
        redirect('index.php?page=keys');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editKey = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("
        SELECT
            k.id,
            k.name,
            k.building_id,
            b.name AS building_name,
            k.zawieszka,
            k.description,
            r.rfid_code
        FROM `keys` k
        INNER JOIN buildings b
            ON b.id = k.building_id
        LEFT JOIN key_rfid_assignments a
            ON a.key_id = k.id
           AND a.assigned_to IS NULL
        LEFT JOIN rfid_tags r
            ON r.id = a.rfid_tag_id
        WHERE k.id = :id
          AND k.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':id' => $editId]);
    $editKey = $stmt->fetch() ?: null;

    if (!$editKey) {
        set_flash('danger', 'Nie znaleziono klucza.');
        redirect('index.php?page=keys');
    }
}

$showModal = isset($_GET['new']) || $editKey !== null;

$buildings = $pdo->query("
    SELECT id, name
    FROM buildings
    WHERE is_active = 1
    ORDER BY name
")->fetchAll();

$stmt = $pdo->query("
    SELECT
        k.id,
        k.name,
        k.building_id,
        b.name AS building_name,
        k.zawieszka,
        k.description,
        r.rfid_code,
        CASE WHEN kl.id IS NOT NULL THEN 1 ELSE 0 END AS is_issued,
        kl.issued_to_name,
        kl.issued_at
    FROM `keys` k
    INNER JOIN buildings b
        ON b.id = k.building_id
    LEFT JOIN key_rfid_assignments a
        ON a.key_id = k.id
       AND a.assigned_to IS NULL
    LEFT JOIN rfid_tags r
        ON r.id = a.rfid_tag_id
    LEFT JOIN key_loans kl
        ON kl.key_id = k.id
       AND kl.returned_at IS NULL
    WHERE k.is_active = 1
    ORDER BY b.name, k.name
");
$keys = $stmt->fetchAll();

$modalId = $editKey ? (int)$editKey['id'] : 0;
$modalName = $editKey ? (string)$editKey['name'] : '';
$modalBuildingId = $editKey ? (int)$editKey['building_id'] : 0;
$modalHanger = $editKey ? (string)($editKey['zawieszka'] ?? '') : '';
$modalDescription = $editKey ? (string)($editKey['description'] ?? '') : '';
$modalRfidCode = $editKey ? (string)($editKey['rfid_code'] ?? '') : '';
?>

<div class="d-flex align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Klucze</h1>
        <div class="text-muted">Zarządzanie kluczami i przypisaniami RFID</div>
    </div>

    <a href="index.php?page=keys&new=1" class="btn btn-primary keys-floating-add" aria-label="Nowy klucz">+</a>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Lista kluczy</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle keys-table">
                <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Budynek</th>
                        <th>Zawieszka</th>
                        <th>Opis</th>
                        <th>RFID</th>
                        <th>Status</th>
                        <th style="width: 180px;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($keys === []): ?>
                        <tr>
                            <td colspan="7" class="text-muted">Brak kluczy.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($keys as $key): ?>
                        <?php
                        $id = (int)$key['id'];
                        $name = (string)$key['name'];
                        $isIssued = (int)($key['is_issued'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e($name) ?></td>
                            <td><?= e((string)$key['building_name']) ?></td>
                            <td><?= e((string)($key['zawieszka'] ?? '')) ?></td>
                            <td><?= e((string)($key['description'] ?? '')) ?></td>
                            <td><?= e((string)($key['rfid_code'] ?? '')) ?></td>
                            <td>
                                <?php if ($isIssued): ?>
                                    <span class="badge text-bg-danger">Wypożyczony</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Dostępny</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="index.php?page=keys&edit=<?= $id ?>" class="btn btn-outline-primary btn-sm">
                                        Edytuj
                                    </a>

                                    <form method="post" class="d-inline" onsubmit="return confirm('Usunąć klucz: <?= e($name) ?>?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="delete_key">
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
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
    </div>
</div>

<div
    class="modal fade"
    id="keyModal"
    tabindex="-1"
    aria-hidden="true"
    data-show-on-load="<?= $showModal ? '1' : '0' ?>"
>
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_key">
                <input type="hidden" name="id" value="<?= $modalId ?>">

                <div class="modal-header">
                    <h5 class="modal-title"><?= $modalId > 0 ? 'Edytuj klucz' : 'Nowy klucz' ?></h5>
                    <a href="index.php?page=keys" class="btn-close" aria-label="Zamknij"></a>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nazwa</label>
                        <input type="text" name="name" class="form-control" value="<?= e($modalName) ?>" maxlength="150" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Budynek</label>
                        <select name="building_id" class="form-select" required>
                            <option value="">Wybierz budynek</option>
                            <?php foreach ($buildings as $building): ?>
                                <?php
                                $buildingId = (int)$building['id'];
                                $buildingName = (string)$building['name'];
                                ?>
                                <option value="<?= $buildingId ?>" <?= $buildingId === $modalBuildingId ? 'selected' : '' ?>>
                                    <?= e($buildingName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Zawieszka</label>
                        <input type="text" name="hanger" class="form-control" value="<?= e($modalHanger) ?>" maxlength="25">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Opis</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="500"><?= e($modalDescription) ?></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">RFID</label>
                        <input type="text" name="rfid_code" class="form-control js-rfid-no-enter" value="<?= e($modalRfidCode) ?>">
                        <div class="form-text">Wyczyść pole i zapisz, żeby usunąć przypisanie RFID.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <a href="index.php?page=keys" class="btn btn-outline-secondary">Anuluj</a>
                    <button type="submit" class="btn btn-primary">Zapisz</button>
                </div>
            </form>
        </div>
    </div>
</div>
