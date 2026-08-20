<?php
declare(strict_types=1);

$descriptionMaxLength = 200;
$isPartialRequest = isset($_GET['partial']) && (string)$_GET['partial'] === '1';

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$userFilter = trim((string)($_GET['user'] ?? ''));
$keyFilter = trim((string)($_GET['key'] ?? ''));
$buildingFilter = trim((string)($_GET['building'] ?? ''));

function key_logs_truncate(string $value, int $maxLength): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (mb_strlen($value, 'UTF-8') <= $maxLength) {
        return $value;
    }

    return rtrim(mb_substr($value, 0, $maxLength, 'UTF-8')) . '…';
}

function key_logs_fetch_rows(
    PDO $pdo,
    string $dateFrom,
    string $dateTo,
    string $userFilter,
    string $keyFilter,
    string $buildingFilter
): array {
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

    $issueWhereSql = $whereIssue !== []
        ? ' AND ' . implode(' AND ', $whereIssue)
        : '';

    $returnWhereSql = $whereReturn !== []
        ? ' AND ' . implode(' AND ', $whereReturn)
        : '';

    $sql = "
        SELECT *
        FROM (
            SELECT
                kl.issued_at AS event_time,
                'Wydanie' AS event_type,
                kl.issued_to_name AS user_name,
                k.name AS key_name,
                b.name AS building,
                k.description AS key_description
            FROM key_loans kl
            INNER JOIN `keys` k
                ON k.id = kl.key_id
            INNER JOIN buildings b
                ON b.id = k.building_id
            WHERE kl.issued_at IS NOT NULL
            {$issueWhereSql}

            UNION ALL

            SELECT
                kl.returned_at AS event_time,
                'Zwrot' AS event_type,
                kl.returned_by_name AS user_name,
                k.name AS key_name,
                b.name AS building,
                k.description AS key_description
            FROM key_loans kl
            INNER JOIN `keys` k
                ON k.id = kl.key_id
            INNER JOIN buildings b
                ON b.id = k.building_id
            WHERE kl.returned_at IS NOT NULL
            {$returnWhereSql}
        ) report
        ORDER BY event_time DESC
        LIMIT 1000
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function key_logs_render_results(array $rows, int $descriptionMaxLength): void
{
    ?>
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            Wyniki: <?= count($rows) ?>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle key-logs-table">
                    <thead>
                        <tr>
                            <th>Data<br>godzina</th>
                            <th>Typ</th>
                            <th>Użytkownik</th>
                            <th>Klucz</th>
                            <th>Budynek</th>
                            <th>Opis klucza</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr>
                                <td colspan="6" class="text-muted">
                                    Brak wpisów.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($rows as $row): ?>
                            <?php
                            $eventTime = trim((string)($row['event_time'] ?? ''));
                            $datePart = '';
                            $timePart = '';

                            if ($eventTime !== '') {
                                try {
                                    $eventDateTime = new DateTimeImmutable($eventTime);
                                    $datePart = $eventDateTime->format('Y-m-d');
                                    $timePart = $eventDateTime->format('H:i:s');
                                } catch (Throwable) {
                                    $parts = preg_split('/\s+/', $eventTime, 2);
                                    $datePart = (string)($parts[0] ?? '');
                                    $timePart = (string)($parts[1] ?? '');
                                }
                            }

                            $description = key_logs_truncate(
                                (string)($row['key_description'] ?? ''),
                                $descriptionMaxLength
                            );
                            ?>
                            <tr>
                                <td class="key-logs-date">
                                    <?= e($datePart) ?><br><?= e($timePart) ?>
                                </td>

                                <td>
                                    <?php if ((string)$row['event_type'] === 'Wydanie'): ?>
                                        <span class="badge text-bg-danger">Wydanie</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">Zwrot</span>
                                    <?php endif; ?>
                                </td>

                                <td class="key-logs-user">
                                    <?= e((string)($row['user_name'] ?? '')) ?>
                                </td>

                                <td class="fw-semibold">
                                    <?= e((string)($row['key_name'] ?? '')) ?>
                                </td>

                                <td>
                                    <?= e((string)($row['building'] ?? '')) ?>
                                </td>

                                <td>
                                    <div
                                        class="key-logs-description"
                                        title="<?= e((string)($row['key_description'] ?? '')) ?>"
                                    >
                                        <?= e($description) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

$rows = key_logs_fetch_rows(
    $pdo,
    $dateFrom,
    $dateTo,
    $userFilter,
    $keyFilter,
    $buildingFilter
);

if ($isPartialRequest) {
    key_logs_render_results($rows, $descriptionMaxLength);
    exit;
}

$users = $pdo->query("
    SELECT user_name
    FROM (
        SELECT issued_to_name AS user_name
        FROM key_loans
        WHERE issued_to_name IS NOT NULL
          AND issued_to_name <> ''

        UNION

        SELECT returned_by_name AS user_name
        FROM key_loans
        WHERE returned_by_name IS NOT NULL
          AND returned_by_name <> ''
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

$partialQuery = [
    'page' => 'key_logs',
    'partial' => '1',
];

if ($dateFrom !== '') {
    $partialQuery['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $partialQuery['date_to'] = $dateTo;
}
if ($userFilter !== '') {
    $partialQuery['user'] = $userFilter;
}
if ($keyFilter !== '') {
    $partialQuery['key'] = $keyFilter;
}
if ($buildingFilter !== '') {
    $partialQuery['building'] = $buildingFilter;
}

$partialUrl = 'index.php?' . http_build_query($partialQuery);
?>

<div class="d-flex align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Logi</h1>
        <div class="text-muted">
            Historia wydań i zwrotów kluczy
        </div>
    </div>

    <div
        class="text-muted key-logs-refresh-status"
        id="key-logs-refresh-state"
    >
        Odświeżanie automatyczne
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">
        Filtry
    </div>

    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="key_logs">

            <div class="col-12 col-md-3 key-logs-date-range-column">
                <label class="form-label" for="dateRangeInput">
                    Zakres dat
                </label>

                <input
                    type="text"
                    class="form-control date-range-input"
                    id="dateRangeInput"
                    readonly
                    placeholder="Wybierz zakres dat"
                >

                <input
                    type="hidden"
                    name="date_from"
                    id="dateFromHidden"
                    value="<?= e($dateFrom) ?>"
                >

                <input
                    type="hidden"
                    name="date_to"
                    id="dateToHidden"
                    value="<?= e($dateTo) ?>"
                >

                <div
                    id="rangePicker"
                    class="range-picker card mt-2"
                    style="display: none;"
                >
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="rangePickerPrevious"
                            >
                                &lt;
                            </button>

                            <div
                                class="fw-semibold"
                                id="rangePickerTitle"
                            ></div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="rangePickerNext"
                            >
                                &gt;
                            </button>
                        </div>

                        <div
                            class="range-picker-grid"
                            id="rangePickerGrid"
                        ></div>

                        <div class="d-flex gap-2 mt-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="rangePickerClear"
                            >
                                Wyczyść
                            </button>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary ms-auto"
                                id="rangePickerClose"
                            >
                                Zamknij
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label">
                    Pracownik
                </label>

                <select name="user" class="form-select">
                    <option value="">Wszyscy</option>

                    <?php foreach ($users as $user): ?>
                        <?php $value = (string)$user['user_name']; ?>
                        <option
                            value="<?= e($value) ?>"
                            <?= $value === $userFilter ? 'selected' : '' ?>
                        >
                            <?= e($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">
                    Klucz
                </label>

                <select name="key" class="form-select">
                    <option value="">Wszystkie</option>

                    <?php foreach ($keys as $key): ?>
                        <?php $value = (string)$key['name']; ?>
                        <option
                            value="<?= e($value) ?>"
                            <?= $value === $keyFilter ? 'selected' : '' ?>
                        >
                            <?= e($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">
                    Budynek
                </label>

                <select name="building" class="form-select">
                    <option value="">Wszystkie</option>

                    <?php foreach ($buildings as $building): ?>
                        <?php $value = (string)$building['name']; ?>
                        <option
                            value="<?= e($value) ?>"
                            <?= $value === $buildingFilter ? 'selected' : '' ?>
                        >
                            <?= e($value) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >
                    Filtruj
                </button>
            </div>

            <div class="col-12">
                <a
                    href="index.php?page=key_logs"
                    class="btn btn-outline-secondary btn-sm"
                >
                    Wyczyść filtry
                </a>
            </div>
        </form>
    </div>
</div>

<div
    id="key-logs-content"
    data-refresh-url="<?= e($partialUrl) ?>"
>
    <?php key_logs_render_results($rows, $descriptionMaxLength); ?>
</div>
