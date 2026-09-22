<?php
declare(strict_types=1);

function key_access_normalize_card(string $card): string
{
    $card = trim($card);
    if (!preg_match('/\A[0-9]{1,64}\z/', $card)) {
        throw new RuntimeException('Numer karty musi zawierać od 1 do 64 cyfr.');
    }
    $card = ltrim($card, '0');
    return $card === '' ? '0' : $card;
}

function key_access_parse_cards(string $text): array
{
    if (strlen($text) > 100000) {
        throw new RuntimeException('Lista kart jest zbyt długa.');
    }
    $tokens = preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $cards = [];
    foreach ($tokens as $token) {
        $cards[] = key_access_normalize_card($token);
    }
    $cards = array_values(array_unique($cards, SORT_STRING));
    sort($cards, SORT_STRING);
    return $cards;
}

function key_access_save(PDO $pdo, int $keyId, bool $restricted, array $cards): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Zapis uprawnień wymaga transakcji.');
    }
    // Wspólna blokada z aplikacją czytnika: flaga i lista zmieniają się atomowo.
    $stmt = $pdo->prepare('SELECT id FROM `keys` WHERE id = ? FOR UPDATE');
    $stmt->execute([$keyId]);
    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Klucz nie istnieje.');
    }
    $pdo->prepare('UPDATE `keys` SET is_restricted = ? WHERE id = ?')
        ->execute([$restricted ? 1 : 0, $keyId]);
    $pdo->prepare('DELETE FROM key_authorized_cards WHERE key_id = ?')->execute([$keyId]);
    $insert = $pdo->prepare('INSERT INTO key_authorized_cards (key_id, card_number) VALUES (?, ?)');
    foreach ($cards as $card) {
        $insert->execute([$keyId, key_access_normalize_card($card)]);
    }
}

// Do wykorzystania także w ręcznym wydawaniu: wywołać w transakcji,
// przed INSERT do key_loans. Zwrot nie wymaga uprawnienia do pobrania.
function key_access_assert_issue(PDO $pdo, int $keyId, string $card): bool
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Kontrola wydania wymaga transakcji.');
    }
    $stmt = $pdo->prepare('SELECT is_restricted, is_active FROM `keys` WHERE id = ? FOR UPDATE');
    $stmt->execute([$keyId]);
    $key = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$key || !(int)$key['is_active']) {
        throw new RuntimeException('Klucz nie istnieje albo jest nieaktywny.');
    }
    if (!(int)$key['is_restricted']) {
        return false;
    }
    $normalized = key_access_normalize_card($card);
    $stmt = $pdo->prepare('SELECT card_number FROM key_authorized_cards WHERE key_id = ?');
    $stmt->execute([$keyId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $allowed) {
        if (key_access_normalize_card((string)$allowed) === $normalized) {
            return true;
        }
    }
    throw new RuntimeException('Brak uprawnienia do pobrania tego klucza.');
}

