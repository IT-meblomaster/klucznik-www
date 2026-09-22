<?php
declare(strict_types=1);

// Publiczna strona, ale formularz, dane osób i zapis wymagają osobnego uprawnienia.
$loanOperator = is_logged_in() ? current_user($pdo) : null;
$canManageLoans = $loanOperator !== null && has_permission($pdo, 'pages.key_inventory.edit');

function ki_json(array $data, int $status = 200): never
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function ki_people_db(array $config): PDO
{
    if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PHP wymaga rozszerzenia pdo_pgsql.');
    }
    foreach (['host', 'port', 'name', 'user', 'pass'] as $field) {
        if (!isset($config['pgsql'][$field])) {
            throw new RuntimeException('Uzupełnij sekcję pgsql w config/config.php.');
        }
    }
    return db_pgsql($config);
}

if (isset($_GET['loan_api']) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!$canManageLoans) {
        ki_json(['error' => 'Brak uprawnienia do wypożyczania i przyjmowania zwrotów.'], 403);
    }
    try {
        $api = $_GET['loan_api'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($api === 'options' && $method === 'GET') {
            $pg = ki_people_db($config);
            $people = $pg->query("SELECT DISTINCT TRIM(nr_karty::text) AS card,
                TRIM(COALESCE(imie, '')) AS first_name,
                TRIM(COALESCE(nazwisko, '')) AS last_name
                FROM public.users_saik
                WHERE NULLIF(TRIM(nr_karty::text), '') IS NOT NULL
                  AND (NULLIF(TRIM(imie), '') IS NOT NULL OR NULLIF(TRIM(nazwisko), '') IS NOT NULL)
                ORDER BY last_name, first_name, card")->fetchAll();
            // Karty przypisane do różnych osób nie mogą identyfikować wyboru.
            $counts = array_count_values(array_column($people, 'card'));
            $people = array_values(array_filter($people, static fn(array $p): bool => $counts[$p['card']] === 1));
            $keys = $pdo->query("SELECT k.id, k.name, k.zawieszka, b.name AS building,
                CAST(kl.id AS CHAR) AS loan_id, kl.issued_to_name, kl.issued_at
                FROM `keys` k JOIN buildings b ON b.id = k.building_id
                LEFT JOIN key_loans kl ON kl.key_id = k.id AND kl.returned_at IS NULL
                WHERE k.is_active = 1 ORDER BY k.name")->fetchAll();
            ki_json(['people' => $people, 'keys' => $keys, 'csrf' => csrf_token()]);
        }
        if ($api !== 'save' || $method !== 'POST') {
            ki_json(['error' => 'Nieprawidłowe żądanie.'], 405);
        }
        if (!verify_csrf()) {
            ki_json(['error' => 'Sesja formularza wygasła. Odśwież stronę.'], 403);
        }
        $action = $_POST['operation'] ?? '';
        $keyId = filter_var($_POST['key_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $card = $_POST['person_card'] ?? '';
        $expectedLoan = $_POST['loan_id'] ?? '';
        if (!in_array($action, ['issue', 'return'], true) || !$keyId || !is_string($card)
            || $card === '' || strlen($card) > 64 || !is_string($expectedLoan)
            || ($action === 'return' && !preg_match('/^[1-9][0-9]*$/D', $expectedLoan))) {
            ki_json(['error' => 'Wybierz operację, klucz i osobę z listy.'], 422);
        }
        $stmt = ki_people_db($config)->prepare("SELECT DISTINCT TRIM(COALESCE(imie, '')) AS first_name,
            TRIM(COALESCE(nazwisko, '')) AS last_name FROM public.users_saik
            WHERE TRIM(nr_karty::text) = :card LIMIT 2");
        $stmt->execute(['card' => $card]);
        $people = $stmt->fetchAll();
        if (count($people) !== 1) {
            ki_json(['error' => 'Osoba nie istnieje lub numer karty nie jest jednoznaczny. Otwórz formularz ponownie.'], 422);
        }
        $personName = trim($people[0]['first_name'] . ' ' . $people[0]['last_name']);
        if ($personName === '' || mb_strlen($personName) > 255) {
            ki_json(['error' => 'Nieprawidłowe dane osoby w bazie pracowników.'], 422);
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, name FROM `keys` WHERE id = ? AND is_active = 1 FOR UPDATE');
        $stmt->execute([$keyId]);
        $key = $stmt->fetch();
        if (!$key) { throw new DomainException('Klucz jest nieaktywny lub już nie istnieje.'); }
        $stmt = $pdo->prepare('SELECT id, issued_to_name FROM key_loans WHERE key_id = ? AND returned_at IS NULL FOR UPDATE');
        $stmt->execute([$keyId]);
        $loans = $stmt->fetchAll();
        if (count($loans) > 1) { throw new DomainException('Klucz ma kilka otwartych wypożyczeń. Najpierw popraw jego historię.'); }
        $loan = $loans[0] ?? null;
        if ($action === 'issue' && $loan) { throw new DomainException('Ten klucz został już wypożyczony. Otwórz formularz ponownie.'); }
        if ($action === 'return' && (!$loan || (string)$loan['id'] !== $expectedLoan)) {
            throw new DomainException('Stan klucza zmienił się. Otwórz formularz ponownie przed przyjęciem zwrotu.');
        }
        $stmt = $pdo->prepare('SELECT rfid_tag_id FROM key_rfid_assignments WHERE key_id = ? AND assigned_to IS NULL LIMIT 1');
        $stmt->execute([$keyId]);
        $tag = $stmt->fetchColumn();
        $tag = $tag === false ? null : $tag;
        if ($action === 'issue') {
            $stmt = $pdo->prepare('INSERT INTO key_loans (key_id, rfid_tag_id, issued_to_card, issued_to_name, issued_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$keyId, $tag, $card, $personName]);
        } else {
            $stmt = $pdo->prepare('UPDATE key_loans SET returned_by_card = ?, returned_by_name = ?, returned_at = NOW() WHERE id = ? AND returned_at IS NULL');
            $stmt->execute([$card, $personName, $loan['id']]);
            if ($stmt->rowCount() !== 1) { throw new DomainException('Stan wypożyczenia zmienił się. Otwórz formularz ponownie.'); }
        }
        $operatorName = trim($loanOperator['first_name'] . ' ' . $loanOperator['last_name']);
        $details = sprintf('Ręcznie WWW | operator ID %d (%s; %s) | %s | osoba: %s | klucz: %s',
            $loanOperator['id'], $loanOperator['username'], $operatorName,
            $action === 'issue' ? 'Wydanie' : 'Zwrot', $personName, $key['name']);
        $stmt = $pdo->prepare('INSERT INTO key_logs (key_id, rfid_tag_id, action_type, action_details) VALUES (?, ?, ?, ?)');
        $stmt->execute([$keyId, $tag, $action === 'issue' ? 'ISSUE' : 'RETURN', mb_substr($details, 0, 1000)]);
        $pdo->commit();
        ki_json(['message' => ($action === 'issue' ? 'Wypożyczono klucz: ' : 'Przyjęto zwrot klucza: ') . $key['name']]);
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        ki_json(['error' => $e->getMessage()], 409);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('key_inventory manual loan: ' . $e->getMessage());
        $message = 'Nie udało się wykonać operacji. Sprawdź konfigurację połączeń i dziennik błędów PHP.';
        if ($e instanceof RuntimeException && !$e instanceof PDOException) { $message = $e->getMessage(); }
        ki_json(['error' => $message], 500);
    }
}


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

function key_inventory_shorten_description(string $description, int $limit = 100): string
{
    $description = trim($description);

    if (mb_strlen($description, 'UTF-8') <= $limit) {
        return $description;
    }

    return rtrim(mb_substr($description, 0, $limit, 'UTF-8')) . '...';
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
                    $shortDescription = key_inventory_shorten_description($description);
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
                            <?= e($shortDescription) ?>
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

<?php if ($canManageLoans): ?>
<style>
.ki-floating-loan { position:fixed; right:2rem; bottom:2rem; z-index:1030; width:4rem; height:4rem; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; padding:0; box-shadow:0 .5rem 1rem rgba(0,0,0,.25); }
.ki-floating-loan svg { width:1.8rem; height:1.8rem; }
@media(max-width:575.98px) { .ki-floating-loan { right:1rem; bottom:1rem; width:3.5rem; height:3.5rem; } }
#ki-loan-form { display:flex; flex-direction:column; min-height:0; overflow:hidden; }
#ki-loan-modal select[size] { min-height:9rem; }
</style>
<div id="ki-loan-result" class="alert d-none mt-3" role="status"></div>
<button type="button" class="btn btn-primary ki-floating-loan" data-bs-toggle="modal" data-bs-target="#ki-loan-modal" title="Wypożyczenie / zwrot" aria-label="Wypożyczenie lub zwrot klucza">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16m-5-5 5 5-5 5M20 17H4m5-5-5 5 5 5"/></svg>
</button>
<div class="modal fade" id="ki-loan-modal" tabindex="-1" aria-labelledby="ki-loan-title" aria-hidden="true" data-bs-backdrop="static">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <form id="ki-loan-form" autocomplete="off">
   <div class="modal-header"><h5 class="modal-title" id="ki-loan-title">Wypożyczenie / zwrot klucza</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button></div>
   <div class="modal-body">
    <div id="ki-loan-error" class="alert alert-info" role="status">Ładowanie danych…</div>
    <fieldset id="ki-loan-fields" disabled>
     <label for="ki-operation" class="form-label">Operacja</label>
     <select id="ki-operation" class="form-select mb-3"><option value="issue">Wypożyczenie</option><option value="return">Zwrot</option></select>
     <label for="ki-key-search" class="form-label">Szukaj klucza</label>
     <input type="search" id="ki-key-search" class="form-control mb-2" placeholder="Nazwa, budynek lub zawieszka">
     <label for="ki-key" class="form-label">Klucz</label>
     <select id="ki-key" class="form-select" size="5" required aria-describedby="ki-key-info"></select>
     <div id="ki-key-info" class="form-text mb-3" aria-live="polite"></div>
     <label for="ki-person-search" class="form-label">Szukaj pracownika</label>
     <input type="search" id="ki-person-search" class="form-control mb-2" placeholder="Nazwisko lub imię">
     <label for="ki-person" id="ki-person-label" class="form-label">Osoba pobierająca</label>
     <select id="ki-person" class="form-select" size="5" required></select>
    </fieldset>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button><button type="submit" id="ki-loan-save" class="btn btn-primary" disabled>Wypożycz klucz</button></div>
  </form>
 </div></div>
</div>
<?php endif; ?>
