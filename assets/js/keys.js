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
    const rows = Array.from(table.querySelectorAll('tbody tr.keys-data-row'));
    const searchInput = document.getElementById('keysSearch');
    const resetButton = document.getElementById('keysResetFilters');
    const withoutRfidOnly = document.getElementById('keysWithoutRfidOnly');
    const availableOnly = document.getElementById('keysAvailableOnly');
    const visibleCount = document.getElementById('keysVisibleCount');
    const buildingFilterLabel = document.getElementById('keysBuildingFilterLabel');
    const noResultsRow = document.getElementById('keysNoResults');
    const sortButtons = Array.from(table.querySelectorAll('.keys-sort-button'));

    let selectedBuilding = '';
    let sortKey = '';
    let sortDirection = 'asc';

    const normalize = (value) => String(value ?? '').trim().toLocaleLowerCase('pl-PL');

    rows.forEach((row, index) => {
        row.dataset.originalIndex = String(index);
        row.dataset.searchText = normalize(
            Array.from(row.querySelectorAll('.keys-searchable'))
                .map((cell) => cell.textContent || '')
                .join(' ')
        );
    });

    const compareNatural = (left, right) => left.localeCompare(right, 'pl', {
        numeric: true,
        sensitivity: 'base',
    });

    function getSortValue(row, key) {
        return normalize(row.dataset[key] || '');
    }

    function updateSortIndicators() {
        sortButtons.forEach((button) => {
            const indicator = button.querySelector('.keys-sort-indicator');
            const active = button.dataset.sortKey === sortKey;

            button.classList.toggle('is-active', active);
            button.setAttribute(
                'aria-sort',
                active
                    ? (sortDirection === 'asc' ? 'ascending' : 'descending')
                    : 'none'
            );

            if (indicator) {
                indicator.textContent = active
                    ? (sortDirection === 'asc' ? '▲' : '▼')
                    : '';
            }
        });
    }

    function sortRows() {
        const sortedRows = [...rows].sort((left, right) => {
            if (sortKey === '') {
                return Number(left.dataset.originalIndex) - Number(right.dataset.originalIndex);
            }

            const result = compareNatural(
                getSortValue(left, sortKey),
                getSortValue(right, sortKey)
            );

            return sortDirection === 'asc' ? result : -result;
        });

        sortedRows.forEach((row) => tbody.appendChild(row));

        if (noResultsRow) {
            tbody.appendChild(noResultsRow);
        }
    }

    function applyFilters() {
        const needle = normalize(searchInput?.value || '');
        const filterWithoutRfid = Boolean(withoutRfidOnly?.checked);
        const filterAvailable = Boolean(availableOnly?.checked);

        let shown = 0;

        rows.forEach((row) => {
            const matchesSearch =
                needle === '' ||
                (row.dataset.searchText || '').includes(needle);

            const matchesBuilding =
                selectedBuilding === '' ||
                normalize(row.dataset.building) === normalize(selectedBuilding);

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
                buildingFilterLabel.textContent = `Budynek: ${selectedBuilding}`;
                buildingFilterLabel.classList.remove('d-none');
            } else {
                buildingFilterLabel.textContent = '';
                buildingFilterLabel.classList.add('d-none');
            }
        }

        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', shown !== 0);
        }
    }

    searchInput?.addEventListener('input', applyFilters);
    withoutRfidOnly?.addEventListener('change', applyFilters);
    availableOnly?.addEventListener('change', applyFilters);

    table.querySelectorAll('.keys-building-filter').forEach((button) => {
        button.addEventListener('click', () => {
            selectedBuilding = button.dataset.building || '';
            applyFilters();
        });
    });

    resetButton?.addEventListener('click', () => {
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
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const newSortKey = button.dataset.sortKey || '';

            if (sortKey === newSortKey) {
                sortDirection = sortDirection === 'asc'
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

    updateSortIndicators();
    applyFilters();
});