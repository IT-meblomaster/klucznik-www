'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-rfid-no-enter').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });

    const modalElement = document.getElementById('keyModal');

    if (modalElement && modalElement.dataset.showOnLoad === '1') {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }

    const table = document.getElementById('keysTable');

    if (!table) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const rows = Array.from(
        table.querySelectorAll('tbody tr.keys-data-row')
    );

    const searchInput = document.getElementById('keysSearch');
    const originalResetButton = document.getElementById('keysResetFilters');
    const withoutRfidOnly = document.getElementById('keysWithoutRfidOnly');
    const availableOnly = document.getElementById('keysAvailableOnly');
    const visibleCount = document.getElementById('keysVisibleCount');
    const buildingFilterLabel = document.getElementById(
        'keysBuildingFilterLabel'
    );
    const noResultsRow = document.getElementById('keysNoResults');
    const sortButtons = Array.from(
        table.querySelectorAll('.keys-sort-button')
    );

    let selectedBuilding = '';
    let sortKey = '';
    let sortDirection = 'asc';

    const normalize = (value) => {
        return String(value ?? '')
            .trim()
            .toLocaleLowerCase('pl-PL');
    };

    rows.forEach((row, index) => {
        row.dataset.originalIndex = String(index);

        row.dataset.searchText = normalize(
            Array.from(row.querySelectorAll('.keys-searchable'))
                .map((cell) => cell.textContent || '')
                .join(' ')
        );
    });

    const compareNatural = (left, right) => {
        return left.localeCompare(right, 'pl', {
            numeric: true,
            sensitivity: 'base',
        });
    };

    function getSortValue(row, key) {
        return normalize(row.dataset[key] || '');
    }

    function updateSortIndicators() {
        sortButtons.forEach((button) => {
            const indicator = button.querySelector(
                '.keys-sort-indicator'
            );

            const active = button.dataset.sortKey === sortKey;

            button.classList.toggle('is-active', active);

            button.setAttribute(
                'aria-sort',
                active
                    ? (
                        sortDirection === 'asc'
                            ? 'ascending'
                            : 'descending'
                    )
                    : 'none'
            );

            if (indicator) {
                indicator.textContent = active
                    ? (
                        sortDirection === 'asc'
                            ? '▲'
                            : '▼'
                    )
                    : '';
            }
        });
    }

    function sortRows() {
        const sortedRows = [...rows].sort((left, right) => {
            if (sortKey === '') {
                return (
                    Number(left.dataset.originalIndex) -
                    Number(right.dataset.originalIndex)
                );
            }

            const result = compareNatural(
                getSortValue(left, sortKey),
                getSortValue(right, sortKey)
            );

            return sortDirection === 'asc'
                ? result
                : -result;
        });

        sortedRows.forEach((row) => {
            tbody.appendChild(row);
        });

        if (noResultsRow) {
            tbody.appendChild(noResultsRow);
        }
    }

    function applyFilters() {
        const needle = normalize(searchInput?.value || '');
        const filterWithoutRfid = Boolean(
            withoutRfidOnly?.checked
        );
        const filterAvailable = Boolean(
            availableOnly?.checked
        );

        let shown = 0;

        rows.forEach((row) => {
            const matchesSearch =
                needle === '' ||
                (row.dataset.searchText || '').includes(needle);

            const matchesBuilding =
                selectedBuilding === '' ||
                normalize(row.dataset.building) ===
                    normalize(selectedBuilding);

            const matchesRfid =
                !filterWithoutRfid ||
                row.dataset.hasRfid === '0';

            const matchesAvailability =
                !filterAvailable ||
                row.dataset.available === '1';

            const visible =
                matchesSearch &&
                matchesBuilding &&
                matchesRfid &&
                matchesAvailability;

            row.classList.toggle('d-none', !visible);

            if (visible) {
                shown++;
            }
        });

        if (visibleCount) {
            visibleCount.textContent = String(shown);
        }

        if (buildingFilterLabel) {
            if (selectedBuilding !== '') {
                buildingFilterLabel.textContent =
                    `Budynek: ${selectedBuilding}`;

                buildingFilterLabel.classList.remove('d-none');
            } else {
                buildingFilterLabel.textContent = '';
                buildingFilterLabel.classList.add('d-none');
            }
        }

        if (noResultsRow) {
            noResultsRow.classList.toggle(
                'd-none',
                shown !== 0
            );
        }
    }

    function clearSearch() {
        if (searchInput) {
            searchInput.value = '';
        }

        applyFilters();
        searchInput?.focus();
    }

    function showAll() {
        if (searchInput) {
            searchInput.value = '';
        }

        if (withoutRfidOnly) {
            withoutRfidOnly.checked = false;
        }

        if (availableOnly) {
            availableOnly.checked = false;
        }

        selectedBuilding = '';
        sortKey = '';
        sortDirection = 'asc';

        sortRows();
        updateSortIndicators();
        applyFilters();

        searchInput?.focus();
    }

    function prepareFilterButtons() {
        if (!originalResetButton) {
            return;
        }

        originalResetButton.textContent = 'Wyczyść';
        originalResetButton.id = 'keysClearSearch';

        originalResetButton.addEventListener(
            'click',
            clearSearch
        );

        const filtersContainer = originalResetButton.closest(
            '.keys-filters'
        );

        if (!filtersContainer) {
            return;
        }

        const switches = Array.from(
            filtersContainer.querySelectorAll(
                '.form-check.form-switch'
            )
        );

        const controlsRow = document.createElement('div');

        controlsRow.className =
            'd-flex flex-wrap align-items-end ' +
            'justify-content-between gap-3';

        const switchesContainer = document.createElement('div');

        switchesContainer.className =
            'd-flex flex-column gap-1';

        switches.forEach((switchElement) => {
            switchesContainer.appendChild(switchElement);
        });

        const showAllButton = document.createElement('button');

        showAllButton.type = 'button';
        showAllButton.id = 'keysShowAll';
        showAllButton.className =
            'btn btn-outline-secondary';
        showAllButton.textContent = 'Pokaż wszystko';

        showAllButton.addEventListener(
            'click',
            showAll
        );

        controlsRow.appendChild(switchesContainer);
        controlsRow.appendChild(showAllButton);
        filtersContainer.appendChild(controlsRow);
    }

    searchInput?.addEventListener(
        'input',
        applyFilters
    );

    withoutRfidOnly?.addEventListener(
        'change',
        applyFilters
    );

    availableOnly?.addEventListener(
        'change',
        applyFilters
    );

    table
        .querySelectorAll('.keys-building-filter')
        .forEach((button) => {
            button.addEventListener('click', () => {
                selectedBuilding =
                    button.dataset.building || '';

                applyFilters();
            });
        });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const newSortKey =
                button.dataset.sortKey || '';

            if (sortKey === newSortKey) {
                sortDirection =
                    sortDirection === 'asc'
                        ? 'desc'
                        : 'asc';
            } else {
                sortKey = newSortKey;
                sortDirection = 'asc';
            }

            sortRows();
            updateSortIndicators();
        });
    });

    prepareFilterButtons();
    updateSortIndicators();
    applyFilters();
});