<?php
declare(strict_types=1);

$isPartialRequest =
    isset($_GET['partial'])
    && (string)$_GET['partial'] === '1';

function dashboard_fetch_data(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            k.id,
            k.name,
            k.zawieszka,
            k.description,
            b.name AS building,
            r.rfid_code,
            CASE
                WHEN kl.id IS NOT NULL THEN 1
                ELSE 0
            END AS is_issued
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
          AND k.show_on_dashboard = 1
        ORDER BY
            k.name
    ");

    return $stmt->fetchAll();
}

function dashboard_shorten_description(
    string $description,
    int $limit = 100
): string {
    $description = trim($description);

    if (
        mb_strlen($description, 'UTF-8')
        <= $limit
    ) {
        return $description;
    }

    return rtrim(
        mb_substr(
            $description,
            0,
            $limit,
            'UTF-8'
        )
    ) . '...';
}

function dashboard_tooltip(array $key): string
{
    $lines = [];

    $name = trim(
        (string)($key['name'] ?? '')
    );

    $building = trim(
        (string)($key['building'] ?? '')
    );

    $description = trim(
        (string)($key['description'] ?? '')
    );

    $hanger = trim(
        (string)($key['zawieszka'] ?? '')
    );

    $rfid = trim(
        (string)($key['rfid_code'] ?? '')
    );

    $isIssued =
        (int)($key['is_issued'] ?? 0) === 1;

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

    $lines[] =
        'RFID: '
        . ($rfid !== '' ? $rfid : 'brak');

    $lines[] =
        'Status: '
        . ($isIssued ? 'wypożyczony' : 'dostępny');

    return implode("\n", $lines);
}

function dashboard_render_content(
    array $keys
): void {
    ?>

    <?php if ($keys === []): ?>
        <div class="alert alert-info">
            Brak kluczy oznaczonych do wyświetlania na dashboardzie.
        </div>

        <?php return; ?>
    <?php endif; ?>

    <div class="dashboard-key-grid">
        <?php foreach ($keys as $key): ?>
            <?php
            $isIssued =
                (int)($key['is_issued'] ?? 0) === 1;

            $description = trim(
                (string)(
                    $key['description']
                    ?? ''
                )
            );

            $shortDescription =
                dashboard_shorten_description(
                    $description
                );

            $hanger = trim(
                (string)(
                    $key['zawieszka']
                    ?? ''
                )
            );
            ?>

            <article
                class="dashboard-key-tile <?= $isIssued ? 'is-issued' : '' ?>"
                title="<?= e(dashboard_tooltip($key)) ?>"
            >
                <div class="dashboard-key-name">
                    <?= e(
                        (string)(
                            $key['name']
                            ?? ''
                        )
                    ) ?>
                </div>

                <div class="dashboard-key-description">
                    <?= e($shortDescription) ?>
                </div>

                <?php if ($hanger !== ''): ?>
                    <div class="dashboard-key-hanger">
                        (<?= e($hanger) ?>)
                    </div>
                <?php endif; ?>

                <div class="dashboard-key-spacer"></div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php
}

$keys = dashboard_fetch_data($pdo);

if ($isPartialRequest) {
    dashboard_render_content($keys);
    exit;
}

$partialUrl =
    'index.php?page=dashboard&partial=1';
?>

<div
    class="d-flex align-items-center justify-content-between gap-3 mb-2"
>
    <div>
        <h1 class="h4 mb-0">
            Dostępność kluczy
        </h1>
    </div>

    <div
        class="text-muted key-inventory-refresh-status"
        id="key-inventory-refresh-state"
    >
        Odświeżanie automatyczne
    </div>
</div>

<div
    id="key-inventory-content"
    data-refresh-url="<?= e($partialUrl) ?>"
>
    <?php dashboard_render_content($keys); ?>
</div>