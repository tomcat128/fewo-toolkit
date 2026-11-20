/* Fewo Toolkit – Preisrechner (ohne Abhängigkeiten, funktioniert auch ohne JS per Formular-Post) */
(() => {
    'use strict';

    const addDays = (isoDate, days) => {
        const date = new Date(isoDate + 'T12:00:00');
        date.setDate(date.getDate() + days);
        return date.toISOString().slice(0, 10);
    };

    const init = (form) => {
        if (form.dataset.fewoReady) {
            return;
        }
        form.dataset.fewoReady = '1';

        const root = form.closest('.fewo-quote');
        const result = root.querySelector('[data-fewo-quote-result]');
        const arrival = form.elements.arrival;
        const departure = form.elements.departure;
        const button = form.querySelector('[type="submit"]');

        // Abreise frühestens einen Tag nach der Anreise
        arrival.addEventListener('change', () => {
            if (!arrival.value) {
                return;
            }
            const minDeparture = addDays(arrival.value, 1);
            departure.min = minDeparture;
            if (!departure.value || departure.value < minDeparture) {
                departure.value = '';
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) {
                return;
            }

            button.disabled = true;
            root.classList.add('is-loading');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const data = await response.json();
                result.innerHTML = data.html;
            } catch (error) {
                // Fallback: klassischer Formular-Post
                form.submit();
                return;
            } finally {
                button.disabled = false;
                root.classList.remove('is-loading');
            }
        });
    };

    const boot = () => document.querySelectorAll('form[data-fewo-quote]').forEach(init);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
