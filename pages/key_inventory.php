<?php
declare(strict_types=1);

$stmt = $pdo->query("
    SELECT
        k.id,
        k.name,
        k.budynek,
        k.zawieszka,
        k.description,
        r.rfid_code,
        k.is_active,
        CASE WHEN kl.id IS NOT NULL THEN 1 ELSE 0 END AS is_issued,
        kl.issued_to_name,
        kl.issued_at
    FROM `keys` k
    LEFT JOIN key_rfid_assignments a
        ON a.key_id = k.id
       AND a.assigned_to IS NULL
    LEFT JOIN rfid_tags r
        ON r.id = a.rfid_tag_id
    LEFT JOIN key_loans kl
        ON kl.key_id = k.id
       AND kl.returned_at IS NULL
    WHERE k.is_active = 1
    ORDER BY k.budynek, k.name
");

$keys = $stmt->fetchAll();

$groups = [];
$totalKeys = 0;
$issuedKeys = 0;
$availableKeys = 0;
$withoutRfid = 0;

foreach ($keys as $key) {
    $building = trim((string)($key['budynek'] ?? ''));
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

    if (!isset($groups[$building])) {
        $groups[$building] = [];
    }

    $groups[$building][] = $key;
}

function key_inventory_tooltip(array $key): string
{
    $lines = [];

    $name = trim((string)($key['name'] ?? ''));
    $building = trim((string)($key['budynek'] ?? ''));
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
?>

<style>
.key-inventory-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.key-inventory-summary .card {
    min-width: 180px;
}

.key-inventory-group {
    border: 1px solid rgba(0, 0, 0, 0.175);
    border-radius: 0.75rem;
    background: #fff;
    margin-bottom: 1.25rem;
    overflow: hidden;
}

.key-inventory-group-header {
    padding: 0.75rem 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    font-weight: 700;
}

.key-inventory-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 1rem;
}

.key-inventory-tile {
    width: 170px;
    height: 130px;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid rgba(144, 144, 144, 0.5);
    background: #d9f2e6;
    display: flex;
    flex-direction: column;
    text-align: center;
    color: #212529;
}

.key-inventory-tile.is-issued {
    background: #f4d6d6;
}

.key-inventory-name {
    font-size: 16px;
    font-weight: 600;
    line-height: 1.15;
    margin-bottom: 2px;
    overflow-wrap: anywhere;
}

.key-inventory-description {
    font-size: 14px;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

.key-inventory-hanger {
    font-size: 14px;
    line-height: 1.15;
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.key-inventory-spacer {
    flex: 1 1 auto;
}

.key-inventory-status {
    font-size: 12px;
    font-weight: 600;
    line-height: 1.15;
    margin-bottom: 2px;
}

.key-inventory-user {
    font-size: 11px;
    line-height: 1.15;
    overflow-wrap: anywhere;
}

@media (max-width: 575.98px) {
    .key-inventory-grid {
        justify-content: center;
    }
}
</style>

<div class="d-flex align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Dostępność kluczy</h1>
        <div class="text-muted">Inwentaryzacja według budynków</div>
    </div>
</div>

<div class="key-inventory-summary mb-4">
    <div class="card shadow-sm">
        <div class="card-body py-3">
            <div class="text-muted small">Wszystkie</div>
            <div class="h4 mb-0"><?= (int)$totalKeys ?></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body py-3">
            <div class="text-muted small">Dostępne</div>
            <div class="h4 mb-0 text-success"><?= (int)$availableKeys ?></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body py-3">
            <div class="text-muted small">Wypożyczone</div>
            <div class="h4 mb-0 text-danger"><?= (int)$issuedKeys ?></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body py-3">
            <div class="text-muted small">Bez RFID</div>
            <div class="h4 mb-0 text-secondary"><?= (int)$withoutRfid ?></div>
        </div>
    </div>
</div>

<?php if ($groups === []): ?>
    <div class="alert alert-info">Brak aktywnych kluczy.</div>
<?php endif; ?>

<?php foreach ($groups as $buildingName => $buildingKeys): ?>
    <section class="key-inventory-group shadow-sm">
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