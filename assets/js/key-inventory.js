'use strict';

(() => {
    const container = document.getElementById('key-inventory-content');
    const status = document.getElementById('key-inventory-refresh-state');
    const buildingSelect = document.getElementById('key-inventory-building');
    const availableOnly = document.getElementById('key-inventory-available');
    const issuedOnly = document.getElementById('key-inventory-issued');
    const showAllButton = document.getElementById('key-inventory-show-all');

    if (!container) {
        return;
    }

    const endpoint = container.dataset.refreshUrl;

    if (!endpoint) {
        return;
    }

    let inFlight = false;
    let refreshPending = false;

    function matchesStatus(tile) {
        const showAvailable = Boolean(availableOnly?.checked);
        const showIssued = Boolean(issuedOnly?.checked);
        const tileStatus = tile.dataset.status || '';

        if (!showAvailable && !showIssued) {
            return true;
        }

        if (showAvailable && showIssued) {
            return true;
        }

        if (showAvailable) {
            return tileStatus === 'available';
        }

        return tileStatus === 'issued';
    }

    function applyFilters() {
        const selectedBuilding = buildingSelect?.value || '';
        const groups = Array.from(
            container.querySelectorAll('.key-inventory-group')
        );

        let visibleKeys = 0;

        groups.forEach((group) => {
            const matchesBuilding =
                selectedBuilding === ''
                || group.dataset.buildingId === selectedBuilding;

            const tiles = Array.from(
                group.querySelectorAll('.key-inventory-tile')
            );

            let visibleInGroup = 0;

            tiles.forEach((tile) => {
                const visible =
                    matchesBuilding
                    && matchesStatus(tile);

                tile.classList.toggle('d-none', !visible);

                if (visible) {
                    visibleKeys++;
                    visibleInGroup++;
                }
            });

            group.classList.toggle(
                'd-none',
                visibleInGroup === 0
            );
        });

        const noResults = container.querySelector(
            '#key-inventory-no-results'
        );

        if (noResults) {
            noResults.classList.toggle(
                'd-none',
                visibleKeys !== 0
            );
        }
    }

    function resetFilters() {
        if (buildingSelect) {
            buildingSelect.value = '';
        }

        if (availableOnly) {
            availableOnly.checked = false;
        }

        if (issuedOnly) {
            issuedOnly.checked = false;
        }

        applyFilters();
    }

    async function refreshInventory() {
        if (inFlight) {
            refreshPending = true;
            return;
        }

        inFlight = true;

        try {
            if (status) {
                status.textContent = 'Odświeżanie...';
            }

            const response = await fetch(endpoint, {
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'fetch',
                },
            });

            if (response.status === 401) {
                window.location.href = 'index.php?page=login';
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            container.innerHTML = await response.text();

            applyFilters();

            if (status) {
                const now = new Date();

                status.textContent =
                    `Ostatnio: ${now.toLocaleTimeString('pl-PL')}`;
            }
        } catch (error) {
            console.error(
                'Nie udało się odświeżyć dostępności kluczy:',
                error
            );

            if (status) {
                status.textContent = 'Błąd odświeżania';
            }
        } finally {
            inFlight = false;

            if (refreshPending) {
                refreshPending = false;
                void refreshInventory();
            }
        }
    }

    buildingSelect?.addEventListener('change', applyFilters);
    availableOnly?.addEventListener('change', applyFilters);
    issuedOnly?.addEventListener('change', applyFilters);
    showAllButton?.addEventListener('click', resetFilters);

    applyFilters();

    document.addEventListener('ki-loan-saved', refreshInventory);
    setInterval(refreshInventory, 5000);
})();

// Obsługa ręcznego wypożyczenia i zwrotu.
(() => {
    const modal = document.getElementById('ki-loan-modal');

    if (!modal) {
        return;
    }

    const form = document.getElementById('ki-loan-form');
    const fields = document.getElementById('ki-loan-fields');

    // Usunięcie wyboru operacji z dotychczasowego formularza PHP.
    document.querySelector('label[for="ki-operation"]')?.remove();
    document.getElementById('ki-operation')?.remove();

    const keySelect = document.getElementById('ki-key');
    const personSelect = document.getElementById('ki-person');
    const keySearch = document.getElementById('ki-key-search');
    const personSearch = document.getElementById('ki-person-search');
    const info = document.getElementById('ki-key-info');
    const error = document.getElementById('ki-loan-error');
    const save = document.getElementById('ki-loan-save');
    const result = document.getElementById('ki-loan-result');

    let keys = [];
    let people = [];
    let csrf = '';
    let busy = false;
    let ready = false;
    let generation = 0;

    const normalize = value => String(value ?? '')
        .toLocaleLowerCase('pl-PL')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ł/g, 'l');

    const selectedKey = () => keys.find(
        key => String(key.id) === keySelect.value
    );

    function message(text, type = 'danger') {
        error.textContent = text;
        error.className = 'alert alert-' + type + (text ? '' : ' d-none');
    }

    function update() {
        const key = selectedKey();
        const returning = Boolean(key?.loan_id);

        save.disabled =
            busy
            || !ready
            || !key
            || !personSelect.value;

        save.textContent = busy
            ? 'Zapisywanie…'
            : !key
                ? 'Wybierz klucz'
                : returning
                    ? 'Przyjmij zwrot'
                    : 'Wypożycz klucz';

        document.getElementById('ki-person-label').textContent = !key
            ? 'Pracownik'
            : returning
                ? 'Osoba zwracająca'
                : 'Osoba pobierająca';

        info.textContent = key
            ? (
                key.loan_id
                    ? `Pobrał: ${key.issued_to_name || '—'} • Data: ${key.issued_at || '—'}`
                    : 'Klucz jest dostępny.'
            )
            : 'Wybierz klucz i pracownika.';
    }

    function renderKeys() {
        const previous = keySelect.value;

        keySelect.replaceChildren();

        const term = normalize(keySearch.value);

        keys.forEach(key => {
            const searchable = normalize(
                `${key.name} ${key.building} ${key.zawieszka || ''}`
            );

            if (!searchable.includes(term)) {
                return;
            }

            const option = new Option(
                `${key.name} — ${key.building}${
                    key.zawieszka ? ' (' + key.zawieszka + ')' : ''
                }`,
                String(key.id)
            );

            keySelect.add(option);
        });

        keySelect.selectedIndex = -1;

        const keep = Array.from(keySelect.options).find(
            option => option.value === previous && !option.disabled
        );

        if (keep) {
            keep.selected = true;
        }

        update();
    }

    function renderPeople() {
        const previous = personSelect.value;

        personSelect.replaceChildren();

        const term = normalize(personSearch.value);

        people.forEach(person => {
            const label = `${person.last_name} ${person.first_name}`.trim();

            if (!normalize(label).includes(term)) {
                return;
            }

            personSelect.add(
                new Option(
                    `${label} (karta: ${person.card})`,
                    person.card
                )
            );
        });

        personSelect.selectedIndex = -1;

        const keep = Array.from(personSelect.options).find(
            option => option.value === previous
        );

        if (keep) {
            keep.selected = true;
        }

        update();
    }

    async function request(action, options = {}) {
        const response = await fetch(
            `index.php?page=key_inventory&partial=1&loan_api=${action}`,
            {
                cache: 'no-store',
                credentials: 'same-origin',
                ...options,
            }
        );

        let data;

        try {
            data = await response.json();
        } catch (_) {
            throw new Error(
                'Nieprawidłowa odpowiedź serwera. Odśwież stronę i sprawdź dziennik błędów PHP.'
            );
        }

        if (!response.ok) {
            throw new Error(
                data.error || 'Nie udało się wykonać operacji.'
            );
        }

        return data;
    }

    modal.addEventListener('show.bs.modal', async () => {
        const ticket = ++generation;

        ready = false;
        fields.disabled = true;
        keys = [];
        people = [];
        csrf = '';

        keySelect.replaceChildren();
        personSelect.replaceChildren();

        keySearch.value = '';
        personSearch.value = '';

        result.classList.add('d-none');

        message('Ładowanie danych…', 'info');
        update();

        try {
            const data = await request('options');

            if (ticket !== generation) {
                return;
            }

            keys = data.keys;
            people = data.people;
            csrf = data.csrf;

            ready = true;
            fields.disabled = false;

            renderKeys();
            renderPeople();

            message(
                people.length
                    ? ''
                    : 'Brak osób z jednoznacznym numerem karty w bazie pracowników.',
                'warning'
            );
        } catch (e) {
            if (ticket === generation) {
                message(e.message);
            }
        }
    });

    modal.addEventListener('hide.bs.modal', event => {
        if (busy) {
            event.preventDefault();
        } else {
            ++generation;
            ready = false;
        }
    });

    modal.addEventListener('hidden.bs.modal', () => {
        keys = [];
        people = [];
        csrf = '';

        keySelect.replaceChildren();
        personSelect.replaceChildren();
    });

    keySearch.addEventListener('input', renderKeys);
    personSearch.addEventListener('input', renderPeople);
    keySelect.addEventListener('change', update);
    personSelect.addEventListener('change', update);

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (busy || save.disabled || !form.reportValidity()) {
            return;
        }

        const key = selectedKey();

        // Rodzaj operacji wynika ze stanu wybranego klucza.
        const body = new URLSearchParams({
            _csrf: csrf,
            operation: key.loan_id ? 'return' : 'issue',
            key_id: keySelect.value,
            person_card: personSelect.value,
            loan_id: key.loan_id || '',
        });

        busy = true;
        fields.disabled = true;

        update();
        message('');

        try {
            const data = await request('save', {
                method: 'POST',
                body,
            });

            busy = false;

            bootstrap.Modal.getOrCreateInstance(modal).hide();

            result.className = 'alert alert-success mt-3';
            result.textContent = data.message;

            document.dispatchEvent(new Event('ki-loan-saved'));
        } catch (e) {
            message(
                e.message
                + ' Przed kolejną próbą zamknij i otwórz formularz, aby pobrać aktualne dane.'
            );

            ready = false;
        } finally {
            busy = false;
            fields.disabled = !ready;
            update();
        }
    });
})();