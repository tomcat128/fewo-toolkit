/* Fewo Toolkit – Anfrageformular: kleine Komfortfunktionen, das Formular funktioniert auch ohne JS */
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

        const arrival = form.elements.arrival;
        const departure = form.elements.departure;
        const syncDeparture = () => {
            if (arrival.value) {
                departure.min = addDays(arrival.value, 1);
                if (departure.value && departure.value < departure.min) {
                    departure.value = '';
                }
            }
        };
        arrival.addEventListener('change', syncDeparture);
        syncDeparture();

        // Doppeltes Absenden verhindern
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }
            form.querySelector('[type="submit"]').disabled = true;
        });
    };

    const boot = () => {
        document.querySelectorAll('form[data-fewo-inquiry]').forEach(init);
        // Meldungen nach dem Absenden für Screenreader fokussieren
        const message = document.querySelector('.fewo-form [data-fewo-focus]');
        if (message) {
            message.focus({ preventScroll: false });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
