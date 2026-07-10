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
        }
    }

    buildingSelect?.addEventListener(
        'change',
        applyFilters
    );

    availableOnly?.addEventListener(
        'change',
        applyFilters
    );

    issuedOnly?.addEventListener(
        'change',
        applyFilters
    );

    showAllButton?.addEventListener(
        'click',
        resetFilters
    );

    applyFilters();

    setInterval(refreshInventory, 5000);
})();