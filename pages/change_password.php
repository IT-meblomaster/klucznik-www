<?php
declare(strict_types=1);

if (!is_logged_in()) {
    redirect('index.php?page=login');
}

$userId = current_user_id();

if (!$userId) {
    redirect('index.php?page=login');
}

$errors = [];
$success = false;

$stmt = $pdo->prepare('
    SELECT id, username, password_hash
    FROM users
    WHERE id = :id AND is_active = 1
    LIMIT 1
');
$stmt->execute(['id' => $userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    logout();
    redirect('index.php?page=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $newPasswordRepeat = (string)($_POST['new_password_repeat'] ?? '');

    if ($currentPassword === '') {
        $errors[] = 'Podaj obecne hasło.';
    }

    if ($newPassword === '') {
        $errors[] = 'Podaj nowe hasło.';
    }

    if ($newPassword !== '' && mb_strlen($newPassword) < 6) {
        $errors[] = 'Nowe hasło musi mieć co najmniej 6 znaków.';
    }

    if ($newPassword !== $newPasswordRepeat) {
        $errors[] = 'Powtórzone hasło nie jest takie samo.';
    }

    if (!$errors && !password_verify($currentPassword, (string)$currentUser['password_hash'])) {
        $errors[] = 'Obecne hasło jest nieprawidłowe.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('
            UPDATE users
            SET password_hash = :password_hash
            WHERE id = :id
        ');
        $stmt->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        $success = true;
        $currentPassword = '';
        $newPassword = '';
        $newPasswordRepeat = '';

        set_flash('success', 'Hasło zostało zmienione.');
        redirect('index.php?page=change_password');
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Zmiana hasła</h1>
</div>

<div class="row">
    <div class="col-lg-6 col-xl-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="text-muted">
                    Zmieniasz hasło dla użytkownika:
                    <strong><?= e((string)$currentUser['username']) ?></strong>
                </p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Hasło zostało zmienione.
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <?= csrf_input() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Obecne hasło</label>
                        <input
                            type="password"
                            name="current_password"
                            id="current_password"
                            class="form-control"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nowe hasło</label>
                        <input
                            type="password"
                            name="new_password"
                            id="new_password"
                            class="form-control"
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >
                        <div class="form-text">Minimum 6 znaków.</div>
                    </div>

                    <div class="mb-4">
                        <label for="new_password_repeat" class="form-label">Powtórz nowe hasło</label>
                        <input
                            type="password"
                            name="new_password_repeat"
                            id="new_password_repeat"
                            class="form-control"
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Zmień hasło
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
