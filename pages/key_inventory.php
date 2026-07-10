<?php
declare(strict_types=1);

$isPartialRequest = isset($_GET['partial']) && (string)$_GET['partial'] === '1';

function key_inventory_fetch_data(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            k.id,
            k.name,
            k.zawieszka,
            k.description,
            b.id AS building_id,
            b.name AS building,
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

    $groups = [];
    $buildings = [];
    $totalKeys = 0;
    $issuedKeys = 0;
    $availableKeys = 0;
    $withoutRfid = 0;

    foreach ($keys as $key) {
        $buildingId = (int)($key['building_id'] ?? 0);
        $building = trim((string)($key['building'] ?? ''));
        $building = $building !== '' ? $building : 'Bez budynku';

        $isIssued = (int)($key['is_issued'] ?? 0) === 1;
        $hasRfid = trim((string)($key['rfid_code'] ?? '')) !== '';

        $totalKeys++;

        if ($isIssued) {
            $issuedKeys++;
        } else {
            $availableKeys++;
        }

        if (!$hasRfid) {
            $withoutRfid++;
        }

        if (!isset($groups[$buildingId])) {
            $groups[$buildingId] = [
                'id' => $buildingId,
                'name' => $building,
                'keys' => [],
            ];
        }

        $groups[$buildingId]['keys'][] = $key;

        if ($buildingId > 0) {
            $buildings[$buildingId] = $building;
        }
    }

    asort($buildings, SORT_NATURAL | SORT_FLAG_CASE);

    return [
        'groups' => $groups,
        'buildings' => $buildings,
        'totalKeys' => $totalKeys,
        'issuedKeys' => $issuedKeys,
        'availableKeys' => $availableKeys,
        'withoutRfid' => $withoutRfid,
    ];
}

function key_inventory_tooltip(array $key): string
{
    $lines = [];

    $name = trim((string)($key['name'] ?? ''));
    $building = trim((string)($key['building'] ?? ''));
    $description = trim((string)($key['description'] ?? ''));
    $hanger = trim((string)($key['zawieszka'] ?? ''));
    $rfid = trim((string)($key['rfid_code'] ?? ''));
    $isIssued = (int)($key['is_issued'] ?? 0) === 1;
    $issuedToName = trim((string)($key['issued_to_name'] ?? ''));
    $issuedAt = trim((string)($key['issued_at'] ?? ''));

    if ($name !== '') {
        $lines[] = 'Klucz: ' . $name;
    }

    if ($building !== '') {
        $lines[] = 'Budynek: ' . $building;
    }

    if ($description !== '') {
        $lines[] = 'Opis: ' . $description;
    }

    if ($hanger !== '') {
        $lines[] = 'Zawieszka: ' . $hanger;
    }

    $lines[] = 'RFID: ' . ($rfid !== '' ? $rfid : 'brak');

    if ($isIssued) {
        $lines[] = 'Status: wypożyczony';

        if ($issuedToName !== '') {
            $lines[] = 'Pobrał: ' . $issuedToName;
        }

        if ($issuedAt !== '') {
            $lines[] = 'Data: ' . $issuedAt;
        }
    } else {
        $lines[] = 'Status: dostępny';
    }

    return implode("\n", $lines);
}

function key_inventory_render_content(array $data): void
{
    $groups = $data['groups'];
    $totalKeys = (int)$data['totalKeys'];
    $availableKeys = (int)$data['availableKeys'];
    $issuedKeys = (int)$data['issuedKeys'];
    $withoutRfid = (int)$data['withoutRfid'];
    ?>

    <div class="key-inventory-summary mb-4">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small">Wszystkie</div>
                <div class="h4 mb-0"><?= $totalKeys ?></div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small">Dostępne</div>
                <div class="h4 mb-0 text-success"><?= $availableKeys ?></div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small">Wypożyczone</div>
                <div class="h4 mb-0 text-danger"><?= $issuedKeys ?></div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small">Bez RFID</div>
                <div class="h4 mb-0 text-secondary"><?= $withoutRfid ?></div>
            </div>
        </div>
    </div>

    <?php if ($groups === []): ?>
        <div class="alert alert-info">Brak aktywnych kluczy.</div>
    <?php endif; ?>

    <div id="key-inventory-no-results" class="alert alert-info d-none">
        Brak kluczy spełniających wybrane kryteria.
    </div>

    <?php foreach ($groups as $group): ?>
        <?php
        $buildingId = (int)$group['id'];
        $buildingName = (string)$group['name'];
        $buildingKeys = $group['keys'];
        ?>

        <section
            class="key-inventory-group shadow-sm"
            data-building-id="<?= $buildingId ?>"
        >
            <div class="key-inventory-group-header">
                <?= e($buildingName) ?>
            </div>

            <div class="key-inventory-grid">
                <?php foreach ($buildingKeys as $key): ?>
                    <?php
                    $isIssued = (int)($key['is_issued'] ?? 0) === 1;
                    $description = trim((string)($key['description'] ?? ''));
                    $hanger = trim((string)($key['zawieszka'] ?? ''));
                    $issuedToName = trim((string)($key['issued_to_name'] ?? ''));
                    ?>

                    <article
                        class="key-inventory-tile <?= $isIssued ? 'is-issued' : '' ?>"
                        data-status="<?= $isIssued ? 'issued' : 'available' ?>"
                        title="<?= e(key_inventory_tooltip($key)) ?>"
                    >
                        <div class="key-inventory-name">
                            <?= e((string)($key['name'] ?? '')) ?>
                        </div>

                        <div class="key-inventory-description">
                            <?= e($description) ?>
                        </div>

                        <div class="key-inventory-hanger">
                            <?= $hanger !== '' ? '(' . e($hanger) . ')' : '' ?>
                        </div>

                        <div class="key-inventory-spacer"></div>

                        <div class="key-inventory-status">
                            <?= $isIssued ? 'Wypożyczony' : 'Dostępny' ?>
                        </div>

                        <?php if ($isIssued): ?>
                            <div class="key-inventory-user">
                                <?= e($issuedToName) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <?php
}

$data = key_inventory_fetch_data($pdo);

if ($isPartialRequest) {
    key_inventory_render_content($data);
    exit;
}

$partialUrl = 'index.php?page=key_inventory&partial=1';
$buildings = $data['buildings'];
?>

<div class="d-flex align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Dostępność kluczy</h1>
        <div class="text-muted">Inwentaryzacja według budynków</div>
    </div>

    <div
        class="text-muted key-inventory-refresh-status"
        id="key-inventory-refresh-state"
    >
        Odświeżanie automatyczne
    </div>
</div>

<div class="key-inventory-toolbar mb-4">
    <div class="input-group key-inventory-building-filter">
        <label
            class="input-group-text"
            for="key-inventory-building"
        >
            Budynek
        </label>

        <select
            id="key-inventory-building"
            class="form-select"
        >
            <option value="">Wszystkie</option>

            <?php foreach ($buildings as $buildingId => $buildingName): ?>
                <option value="<?= (int)$buildingId ?>">
                    <?= e((string)$buildingName) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="key-inventory-filter-area">
        <fieldset class="key-inventory-filter-box">
            <legend>Wyświetlone klucze</legend>

            <div class="form-check form-switch">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="key-inventory-available"
                >

                <label
                    class="form-check-label"
                    for="key-inventory-available"
                >
                    Dostępne
                </label>
            </div>

            <div class="form-check form-switch">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="key-inventory-issued"
                >

                <label
                    class="form-check-label"
                    for="key-inventory-issued"
                >
                    Wypożyczone
                </label>
            </div>
        </fieldset>

        <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            id="key-inventory-show-all"
        >
            Pokaż wszystko
        </button>
    </div>
</div>

<div
    id="key-inventory-content"
    data-refresh-url="<?= e($partialUrl) ?>"
>
    <?php key_inventory_render_content($data); ?>
</div>