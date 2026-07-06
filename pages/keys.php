<?php
declare(strict_types=1);

function keys_null_if_empty(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Nieprawidłowy token formularza.');
        redirect('index.php?page=keys');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save_key') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $building = keys_null_if_empty($_POST['building'] ?? null);
            $hanger = keys_null_if_empty($_POST['hanger'] ?? null);
            $description = keys_null_if_empty($_POST['description'] ?? null);

            if ($name === '') {
                throw new RuntimeException('Podaj nazwę klucza.');
            }

            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE `keys`
                    SET name = :name,
                        budynek = :building,
                        zawieszka = :hanger,
                        description = :description
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':name' => $name,
                    ':building' => $building,
                    ':hanger' => $hanger,
                    ':description' => $description,
                ]);

                keys_insert_log($pdo, $id, null, 'UPDATE', 'Zaktualizowano klucz: ' . $name);
                set_flash('success', 'Zaktualizowano klucz.');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO `keys` (name, budynek, zawieszka, description, is_active)
                    VALUES (:name, :building, :hanger, :description, 1)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':building' => $building,
                    ':hanger' => $hanger,
                    ':description' => $description,
                ]);

                $id = (int)$pdo->lastInsertId();
                keys_insert_log($pdo, $id, null, 'CREATE', 'Dodano klucz: ' . $name);
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

            $stmt = $pdo->prepare("SELECT name FROM `keys` WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $keyName = (string)($stmt->fetchColumn() ?: ('ID ' . $id));

            $stmt = $pdo->prepare("UPDATE `keys` SET is_active = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);

            keys_insert_log($pdo, $id, null, 'DELETE', 'Usunięto klucz: ' . $keyName);

            $pdo->commit();
            set_flash('success', 'Usunięto klucz.');
            redirect('index.php?page=keys');
        }

        if ($action === 'assign_rfid') {
            $keyId = (int)($_POST['id'] ?? 0);
            $rfidCode = trim((string)($_POST['rfid_code'] ?? ''));

            if ($keyId <= 0) {
                throw new RuntimeException('Nieprawidłowy klucz.');
            }

            if ($rfidCode === '') {
                throw new RuntimeException('Podaj RFID.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, rfid_tag_id
                FROM key_rfid_assignments
                WHERE key_id = :key_id
                  AND assigned_to IS NULL
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':key_id' => $keyId]);
            $currentAssignment = $stmt->fetch();

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

            if ($currentAssignment && (int)$currentAssignment['rfid_tag_id'] === $rfidTagId) {
                $pdo->commit();
                set_flash('info', 'Ten RFID jest już przypisany do wybranego klucza.');
                redirect('index.php?page=keys');
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

            $stmt = $pdo->prepare("SELECT name FROM `keys` WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $keyId]);
            $keyName = (string)($stmt->fetchColumn() ?: ('ID ' . $keyId));

            keys_insert_log($pdo, $keyId, $rfidTagId, 'ASSIGN_RFID', 'Przypisano RFID ' . $rfidCode . ' do klucza ' . $keyName);

            $pdo->commit();
            set_flash('success', 'Przypisano RFID.');
            redirect('index.php?page=keys');
        }

        if ($action === 'remove_rfid') {
            $keyId = (int)($_POST['id'] ?? 0);

            if ($keyId <= 0) {
                throw new RuntimeException('Nieprawidłowy klucz.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, rfid_tag_id
                FROM key_rfid_assignments
                WHERE key_id = :key_id
                  AND assigned_to IS NULL
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':key_id' => $keyId]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                throw new RuntimeException('Wybrany klucz nie ma przypisanego RFID.');
            }

            $stmt = $pdo->prepare("
                UPDATE key_rfid_assignments
                SET assigned_to = NOW(),
                    unassigned_reason = 'MANUAL_REMOVE'
                WHERE id = :id
            ");
            $stmt->execute([':id' => (int)$assignment['id']]);

            $stmt = $pdo->prepare("SELECT name FROM `keys` WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $keyId]);
            $keyName = (string)($stmt->fetchColumn() ?: ('ID ' . $keyId));

            keys_insert_log($pdo, $keyId, (int)$assignment['rfid_tag_id'], 'REMOVE_RFID', 'Usunięto aktywne przypisanie RFID z klucza ' . $keyName);

            $pdo->commit();
            set_flash('success', 'Usunięto RFID.');
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
        SELECT id, name, budynek, zawieszka, description
        FROM `keys`
        WHERE id = :id
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':id' => $editId]);
    $editKey = $stmt->fetch() ?: null;
}

$stmt = $pdo->query("
    SELECT
        k.id,
        k.name,
        k.budynek,
        k.zawieszka,
        k.description,
        r.rfid_code,
        CASE WHEN r.rfid_code IS NULL OR r.rfid_code = '' THEN 'Brak RFID' ELSE 'Przypisany' END AS rfid_status
    FROM `keys` k
    LEFT JOIN key_rfid_assignments a
        ON a.key_id = k.id
       AND a.assigned_to IS NULL
    LEFT JOIN rfid_tags r
        ON r.id = a.rfid_tag_id
    WHERE k.is_active = 1
    ORDER BY k.budynek, k.name
");
$keys = $stmt->fetchAll();
?>

<div class="d-flex align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Klucze</h1>
        <div class="text-muted">Lista kluczy i przypisania RFID</div>
    </div>
    <?php if ($editKey): ?>
        <a href="index.php?page=keys" class="btn btn-outline-secondary btn-sm">Nowy klucz</a>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">
                <?= $editKey ? 'Edytuj klucz' : 'Nowy klucz' ?>
            </div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_key">
                    <input type="hidden" name="id" value="<?= e((string)($editKey['id'] ?? 0)) ?>">

                    <div class="mb-3">
                        <label class="form-label">Nazwa</label>
                        <input type="text" name="name" class="form-control" required value="<?= e((string)($editKey['name'] ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Budynek</label>
                        <input type="text" name="building" class="form-control" value="<?= e((string)($editKey['budynek'] ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Zawieszka</label>
                        <input type="text" name="hanger" class="form-control" value="<?= e((string)($editKey['zawieszka'] ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Opis</label>
                        <textarea name="description" class="form-control" rows="3"><?= e((string)($editKey['description'] ?? '')) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $editKey ? 'Zapisz' : 'Dodaj' ?>
                    </button>

                    <?php if ($editKey): ?>
                        <a href="index.php?page=keys" class="btn btn-outline-secondary">Anuluj</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Lista</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle keys-table">
                        <thead>
                            <tr>
                                <th>Nazwa</th>
                                <th>Budynek</th>
                                <th>Zawieszka</th>
                                <th>Opis</th>
                                <th>Status RFID</th>
                                <th>RFID</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($keys === []): ?>
                                <tr>
                                    <td colspan="7" class="text-muted">Brak kluczy.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($keys as $key): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string)$key['name']) ?></td>
                                    <td><?= e((string)($key['budynek'] ?? '')) ?></td>
                                    <td><?= e((string)($key['zawieszka'] ?? '')) ?></td>
                                    <td><?= e((string)($key['description'] ?? '')) ?></td>
                                    <td>
                                        <?php if ((string)$key['rfid_status'] === 'Przypisany'): ?>
                                            <span class="badge text-bg-success">Przypisany</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Brak RFID</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" class="d-flex gap-1 justify-content-center">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="assign_rfid">
                                            <input type="hidden" name="id" value="<?= (int)$key['id'] ?>">
                                            <input type="text" name="rfid_code" class="form-control form-control-sm keys-rfid-input" value="<?= e((string)($key['rfid_code'] ?? '')) ?>" placeholder="RFID">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Zapisz</button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1 justify-content-center">
                                            <a href="index.php?page=keys&amp;edit=<?= (int)$key['id'] ?>" class="btn btn-outline-primary btn-sm">Edytuj</a>

                                            <form method="post">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="remove_rfid">
                                                <input type="hidden" name="id" value="<?= (int)$key['id'] ?>">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">Usuń RFID</button>
                                            </form>

                                            <form method="post" onsubmit="return confirm('Usunąć klucz?');">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="delete_key">
                                                <input type="hidden" name="id" value="<?= (int)$key['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Usuń</button>
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
    </div>
</div>

<style>
.keys-table {
    table-layout: auto;
}

.keys-table th,
.keys-table td {
    white-space: normal;
    word-break: break-word;
    overflow-wrap: anywhere;
    vertical-align: middle;
}

.keys-rfid-input {
    min-width: 140px;
}
</style>