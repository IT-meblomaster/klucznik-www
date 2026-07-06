(() => {
    const container = document.getElementById('key-inventory-content');
    const status = document.getElementById('key-inventory-refresh-state');

    if (!container) {
        return;
    }

    const endpoint = container.dataset.refreshUrl;

    if (!endpoint) {
        return;
    }

    let inFlight = false;

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
                    'X-Requested-With': 'fetch'
                }
            });

            if (response.status === 401) {
                window.location.href = 'index.php?page=login';
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            container.innerHTML = await response.text();

            if (status) {
                const now = new Date();
                status.textContent = `Ostatnio: ${now.toLocaleTimeString('pl-PL')}`;
            }
        } catch (error) {
            console.error('Nie udało się odświeżyć dostępności kluczy:', error);

            if (status) {
                status.textContent = 'Błąd odświeżania';
            }
        } finally {
            inFlight = false;
        }
    }

    setInterval(refreshInventory, 5000);
})();