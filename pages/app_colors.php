<?php
declare(strict_types=1);

/*
 * Ustawienia kolorów informacji zwrotnej skanera.
 *
 * Wymagane ustawienie w bazie:
 *
 * scanner_feedback_colors
 *
 * JSON:
 * {
 *     "IssuedBackground": "#DCFCE7",
 *     "IssuedBorder": "#86EFAC",
 *     "ReturnedBackground": "#F3E8FF",
 *     "ReturnedBorder": "#C084FC"
 * }
 */

if (!has_permission($pdo, 'pages.app_colors.view') && !has_permission($pdo, 'pages.app_colors.edit')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

$errors = [];
$success = false;

$defaultColors = [
    'IssuedBackground' => '#DCFCE7',
    'IssuedBorder' => '#86EFAC',
    'ReturnedBackground' => '#F3E8FF',
    'ReturnedBorder' => '#C084FC',
];

$colorLabels = [
    'IssuedBackground' => 'Tło komunikatu „Wydano klucz”',
    'IssuedBorder' => 'Obramowanie komunikatu „Wydano klucz”',
    'ReturnedBackground' => 'Tło komunikatu „Zwrócono klucz”',
    'ReturnedBorder' => 'Obramowanie komunikatu „Zwrócono klucz”',
];

$colors = $defaultColors;

/*
 * Pobranie aktualnych ustawień.
 */
$stmt = $pdo->prepare('
    SELECT setting_value
    FROM app_settings
    WHERE setting_key = :setting_key
    LIMIT 1
');

$stmt->execute([
    'setting_key' => 'scanner_feedback_colors',
]);

$settingValue = $stmt->fetchColumn();

if ($settingValue !== false && is_string($settingValue)) {
    $decoded = json_decode($settingValue, true);

    if (is_array($decoded)) {
        foreach ($defaultColors as $key => $defaultValue) {
            if (
                isset($decoded[$key])
                && is_string($decoded[$key])
                && preg_match('/^#[0-9A-Fa-f]{6}$/', $decoded[$key])
            ) {
                $colors[$key] = strtoupper($decoded[$key]);
            }
        }
    }
}

/*
 * Zapis ustawień.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!has_permission($pdo, 'pages.app_colors.edit')) {
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        return;
    }

    verify_csrf();

    $newColors = [];

    foreach ($defaultColors as $key => $defaultValue) {
        $value = strtoupper(trim((string)($_POST[$key] ?? '')));

        if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
            $errors[] = 'Nieprawidłowy kolor dla: ' . ($colorLabels[$key] ?? $key);
        }

        $newColors[$key] = $value;
    }

    if (!$errors) {
        $json = json_encode(
            $newColors,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            $errors[] = 'Nie udało się przygotować ustawień do zapisu.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO app_settings (setting_key, setting_value)
                VALUES (:setting_key, :setting_value)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
            ');

            $stmt->execute([
                'setting_key' => 'scanner_feedback_colors',
                'setting_value' => $json,
            ]);

            $colors = $newColors;
            $success = true;
        } catch (Throwable $e) {
            $errors[] = 'Nie udało się zapisać ustawień.';
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Ustawienia kolorów</h1>
        <div class="text-muted">
            Kolory komunikatów wyświetlanych po zeskanowaniu klucza.
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Zapisano.</strong>
        Ustawienia kolorów zostały zapisane.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-xl-8 col-lg-10">
        <div class="card shadow-sm">
            <div class="card-header bg-body">
                <h2 class="h5 mb-0">Kolory komunikatów skanera</h2>
            </div>

            <div class="card-body">

                <div class="row g-4">

                    <?php foreach ($colors as $key => $color): ?>

                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">

                                <div class="d-flex align-items-center gap-3">

                                    <!-- Okrąg z aktualnym kolorem -->
                                    <span
                                        class="scanner-color-preview"
                                        data-preview-for="<?= e($key) ?>"
                                        style="
                                            width: 42px;
                                            height: 42px;
                                            min-width: 42px;
                                            border-radius: 50%;
                                            background-color: <?= e($color) ?>;
                                            border: 1px solid rgba(0,0,0,.15);
                                            box-shadow: 0 1px 3px rgba(0,0,0,.15);
                                            display: inline-block;
                                        "
                                        title="<?= e($color) ?>"
                                    ></span>

                                    <div class="flex-grow-1">
                                        <label
                                            for="<?= e($key) ?>"
                                            class="form-label fw-semibold mb-1"
                                        >
                                            <?= e($colorLabels[$key] ?? $key) ?>
                                        </label>

                                        <div class="text-muted small">
                                            Kliknij próbkę koloru, aby ją zmienić.
                                        </div>
                                    </div>

                                    <!-- Color picker -->
                                    <input
                                        type="color"
                                        class="form-control form-control-color scanner-color-picker"
                                        id="<?= e($key) ?>"
                                        name="<?= e($key) ?>"
                                        value="<?= e($color) ?>"
                                        data-preview-for="<?= e($key) ?>"
                                        title="Wybierz kolor"
                                        <?= !has_permission($pdo, 'pages.app_colors.edit') ? 'disabled' : '' ?>
                                    >

                                </div>

                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

                <hr class="my-4">

                <!-- Podgląd -->
                <h3 class="h6 mb-3">Podgląd</h3>

                <div class="row g-3">

                    <div class="col-md-6">
                        <div
                            id="issued-preview"
                            class="rounded-3 p-3"
                            style="
                                background-color: <?= e($colors['IssuedBackground']) ?>;
                                border: 2px solid <?= e($colors['IssuedBorder']) ?>;
                            "
                        >
                            <div class="fw-semibold">
                                Klucz wydany
                            </div>
                            <div class="small text-muted">
                                Przykładowy wygląd komunikatu po wydaniu klucza.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div
                            id="returned-preview"
                            class="rounded-3 p-3"
                            style="
                                background-color: <?= e($colors['ReturnedBackground']) ?>;
                                border: 2px solid <?= e($colors['ReturnedBorder']) ?>;
                            "
                        >
                            <div class="fw-semibold">
                                Klucz zwrócony
                            </div>
                            <div class="small text-muted">
                                Przykładowy wygląd komunikatu po zwrocie klucza.
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <?php if (has_permission($pdo, 'pages.app_colors.edit')): ?>
                <div class="card-footer bg-body d-flex justify-content-end">
                    <button
                        type="submit"
                        form="scanner-colors-form"
                        class="btn btn-primary"
                    >
                        Zapisz ustawienia
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (has_permission($pdo, 'pages.app_colors.edit')): ?>
<form
    method="post"
    id="scanner-colors-form"
    class="d-none"
>
    <?= csrf_input() ?>

    <?php foreach ($colors as $key => $color): ?>
        <input
            type="hidden"
            name="<?= e($key) ?>"
            id="hidden-<?= e($key) ?>"
            value="<?= e($color) ?>"
        >
    <?php endforeach; ?>
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const pickers = document.querySelectorAll('.scanner-color-picker');

    pickers.forEach(function (picker) {

        picker.addEventListener('input', function () {

            const key = picker.dataset.previewFor;
            const color = picker.value.toUpperCase();

            /*
             * Aktualizacja okrągłej próbki koloru.
             */
            const colorPreview = document.querySelector(
                '.scanner-color-preview[data-preview-for="' + key + '"]'
            );

            if (colorPreview) {
                colorPreview.style.backgroundColor = color;
                colorPreview.title = color;
            }

            /*
             * Aktualizacja hidden inputów wysyłanych formularzem.
             */
            const hiddenInput = document.getElementById('hidden-' + key);

            if (hiddenInput) {
                hiddenInput.value = color;
            }

            /*
             * Aktualizacja podglądu komunikatów.
             */
            const issuedPreview = document.getElementById('issued-preview');
            const returnedPreview = document.getElementById('returned-preview');

            if (issuedPreview) {
                if (key === 'IssuedBackground') {
                    issuedPreview.style.backgroundColor = color;
                }

                if (key === 'IssuedBorder') {
                    issuedPreview.style.borderColor = color;
                }
            }

            if (returnedPreview) {
                if (key === 'ReturnedBackground') {
                    returnedPreview.style.backgroundColor = color;
                }

                if (key === 'ReturnedBorder') {
                    returnedPreview.style.borderColor = color;
                }
            }
        });
    });

});
</script>
