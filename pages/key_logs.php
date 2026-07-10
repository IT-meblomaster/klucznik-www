<?php
declare(strict_types=1);

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$userFilter = trim((string)($_GET['user'] ?? ''));
$keyFilter = trim((string)($_GET['key'] ?? ''));
$buildingFilter = trim((string)($_GET['building'] ?? ''));

$params = [];

$whereIssue = [];
$whereReturn = [];

if ($dateFrom !== '') {
    $whereIssue[] = 'kl.issued_at >= :date_from_issue';
    $whereReturn[] = 'kl.returned_at >= :date_from_return';
    $params[':date_from_issue'] = $dateFrom . ' 00:00:00';
    $params[':date_from_return'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $whereIssue[] = 'kl.issued_at <= :date_to_issue';
    $whereReturn[] = 'kl.returned_at <= :date_to_return';
    $params[':date_to_issue'] = $dateTo . ' 23:59:59';
    $params[':date_to_return'] = $dateTo . ' 23:59:59';
}

if ($userFilter !== '') {
    $whereIssue[] = 'kl.issued_to_name = :user_issue';
    $whereReturn[] = 'kl.returned_by_name = :user_return';
    $params[':user_issue'] = $userFilter;
    $params[':user_return'] = $userFilter;
}

if ($keyFilter !== '') {
    $whereIssue[] = 'k.name = :key_issue';
    $whereReturn[] = 'k.name = :key_return';
    $params[':key_issue'] = $keyFilter;
    $params[':key_return'] = $keyFilter;
}

if ($buildingFilter !== '') {
    $whereIssue[] = 'b.name = :building_issue';
    $whereReturn[] = 'b.name = :building_return';
    $params[':building_issue'] = $buildingFilter;
    $params[':building_return'] = $buildingFilter;
}

$issueWhereSql = $whereIssue !== [] ? ' AND ' . implode(' AND ', $whereIssue) : '';
$returnWhereSql = $whereReturn !== [] ? ' AND ' . implode(' AND ', $whereReturn) : '';

$sql = "
    SELECT *
    FROM (
        SELECT
            kl.issued_at AS event_time,
            'Wydanie' AS event_type,
            k.name AS key_name,
            b.name AS building,
            kl.issued_to_name AS user_name,
            kl.issued_to_card AS user_card,
            r.rfid_code AS rfid_code
        FROM key_loans kl
        INNER JOIN `keys` k
            ON k.id = kl.key_id
        INNER JOIN buildings b
            ON b.id = k.building_id
        LEFT JOIN rfid_tags r
            ON r.id = kl.rfid_tag_id
        WHERE kl.issued_at IS NOT NULL
        {$issueWhereSql}

        UNION ALL

        SELECT
            kl.returned_at AS event_time,
            'Zwrot' AS event_type,
            k.name AS key_name,
            b.name AS building,
            kl.returned_by_name AS user_name,
            kl.returned_by_card AS user_card,
            r.rfid_code AS rfid_code
        FROM key_loans kl
        INNER JOIN `keys` k
            ON k.id = kl.key_id
        INNER JOIN buildings b
            ON b.id = k.building_id
        LEFT JOIN rfid_tags r
            ON r.id = kl.rfid_tag_id
        WHERE kl.returned_at IS NOT NULL
        {$returnWhereSql}
    ) report
    ORDER BY event_time DESC
    LIMIT 1000
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$users = $pdo->query("
    SELECT user_name
    FROM (
        SELECT issued_to_name AS user_name FROM key_loans WHERE issued_to_name IS NOT NULL AND issued_to_name <> ''
        UNION
        SELECT returned_by_name AS user_name FROM key_loans WHERE returned_by_name IS NOT NULL AND returned_by_name <> ''
    ) u
    ORDER BY user_name
")->fetchAll();

$keys = $pdo->query("
    SELECT name
    FROM `keys`
    WHERE is_active = 1
    ORDER BY name
")->fetchAll();

$buildings = $pdo->query("
    SELECT name
    FROM buildings
    WHERE is_active = 1
    ORDER BY name
")->fetchAll();
?>

<div class="d-flex align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Logi</h1>
        <div class="text-muted">Historia wydań i zwrotów kluczy</div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Filtry</div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="key_logs">

            <div class="col-12 col-md-2">
                <label class="form-label">Od</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">Do</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label">Pracownik</label>
                <select name="user" class="form-select">
                    <option value="">Wszyscy</option>
                    <?php foreach ($users as $user): ?>
                        <?php $value = (string)$user['user_name']; ?>
                        <option value="<?= e($value) ?>" <?= $value === $userFilter ? 'selected' : '' ?>>
                            <?= e($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">Klucz</label>
                <select name="key" class="form-select">
                    <option value="">Wszystkie</option>
                    <?php foreach ($keys as $key): ?>
                        <?php $value = (string)$key['name']; ?>
                        <option value="<?= e($value) ?>" <?= $value === $keyFilter ? 'selected' : '' ?>>
                            <?= e($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">Budynek</label>
                <select name="building" class="form-select">
                    <option value="">Wszystkie</option>
                    <?php foreach ($buildings as $building): ?>
                        <?php $value = (string)$building['name']; ?>
                        <option value="<?= e($value) ?>" <?= $value === $buildingFilter ? 'selected' : '' ?>>
                            <?= e($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filtruj</button>
            </div>

            <div class="col-12">
                <a href="index.php?page=key_logs" class="btn btn-outline-secondary btn-sm">Wyczyść filtry</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">
        Wyniki: <?= count($rows) ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle key-logs-table">
                <thead>
                    <tr>
                        <th>Data i godzina</th>
                        <th>Typ</th>
                        <th>Klucz</th>
                        <th>Budynek</th>
                        <th>Użytkownik</th>
                        <th>Karta</th>
                        <th>RFID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="7" class="text-muted">Brak wpisów.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e((string)$row['event_time']) ?></td>
                            <td>
                                <?php if ((string)$row['event_type'] === 'Wydanie'): ?>
                                    <span class="badge text-bg-danger">Wydanie</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Zwrot</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?= e((string)$row['key_name']) ?></td>
                            <td><?= e((string)($row['building'] ?? '')) ?></td>
                            <td><?= e((string)($row['user_name'] ?? '')) ?></td>
                            <td><?= e((string)($row['user_card'] ?? '')) ?></td>
                            <td><?= e((string)($row['rfid_code'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.key-logs-table {
    table-layout: auto;
}

.key-logs-table th,
.key-logs-table td {
    white-space: nowrap;
    vertical-align: middle;
}

.key-logs-table td:nth-child(5) {
    white-space: normal;
    word-break: break-word;
    overflow-wrap: anywhere;
}
</style>
