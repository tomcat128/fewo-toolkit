/* Fewo Toolkit – Preisrechner (ohne Abhängigkeiten, funktioniert auch ohne JS per Formular-Post) */
(() => {
    'use strict';

    // Alle Daten als Text "JJJJ-MM-TT": lässt sich direkt vergleichen und ist unabhängig von der Zeitzone.
    const toIso = (date) => date.toISOString().slice(0, 10);
    const fromIso = (iso) => new Date(iso + 'T00:00:00Z');
    const addDays = (iso, days) => {
        const date = fromIso(iso);
        date.setUTCDate(date.getUTCDate() + days);
        return toIso(date);
    };
    const addMonths = (month, count) => {
        const date = fromIso(month + '-01');
        date.setUTCMonth(date.getUTCMonth() + count);
        return toIso(date).slice(0, 7);
    };

    /**
     * Kalender der Felder "Anreise" und "Abreise" mit der Belegung der Wohnung.
     *
     * Er ersetzt den Kalender des Browsers, in dem sich einzelne Tage weder sperren noch einfärben lassen:
     * Ein Klick in eines der beiden Felder öffnet ihn direkt darunter, belegte Tage lassen sich nicht wählen.
     * Die Felder zeigen das Datum an; übertragen wird es wie bisher als "arrival" und "departure".
     */
    const createDatePicker = (popup, form) => {
        const { booked, labels } = JSON.parse(popup.dataset.fewoQuoteCalendar);
        const locale = document.documentElement.lang || undefined;
        const monthFormat = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric', timeZone: 'UTC' });
        const dayFormat = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
        const weekdayFormat = new Intl.DateTimeFormat(locale, { weekday: 'short', timeZone: 'UTC' });
        const fieldFormat = new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });

        const min = form.elements.arrival.min;
        const max = form.elements.arrival.max;
        popup.id = popup.id || form.elements.arrival.id + '-calendar';

        /**
         * Macht aus dem Datumsfeld ein Anzeigefeld, das den Kalender öffnet. Der Wert wandert in ein
         * verstecktes Feld gleichen Namens.
         */
        const convert = (input) => {
            const value = document.createElement('input');
            value.type = 'hidden';
            value.name = input.name;
            value.value = input.value;
            input.dataset.fewoDate = input.name;
            input.removeAttribute('name');
            input.removeAttribute('min');
            input.removeAttribute('max');
            input.removeAttribute('required');
            input.type = 'text';
            input.readOnly = true;
            input.autocomplete = 'off';
            input.classList.add('fewo-quote__date');
            // Auswahlfeld mit aufklappendem Kalender (ARIA-Muster "Date Picker Combobox")
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-haspopup', 'dialog');
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('aria-controls', popup.id);
            input.after(value);
            return { display: input, value };
        };
        const fields = { arrival: convert(form.elements.arrival), departure: convert(form.elements.departure) };
        const arrival = fields.arrival.value;
        const departure = fields.departure.value;
        const showValues = () => Object.values(fields).forEach(({ display, value }) => {
            display.value = value.value ? fieldFormat.format(fromIso(value.value)) : '';
        });

        // Belegte Nächte (der Tag, an dem die Nacht beginnt)
        const bookedNights = new Set();
        booked.forEach(([from, to]) => {
            for (let night = from; night < to; night = addDays(night, 1)) {
                bookedNights.add(night);
            }
        });
        const isBooked = (iso) => bookedNights.has(iso);
        const isRangeFree = (from, to) => {
            for (let night = from; night < to; night = addDays(night, 1)) {
                if (isBooked(night)) {
                    return false;
                }
            }
            return true;
        };
        /** Spätester Abreisetag nach einer Anreise: der Tag, an dem die nächste Belegung beginnt. */
        const lastDeparture = (from) => {
            let day = addDays(from, 1);
            while (day < max && !isBooked(day)) {
                day = addDays(day, 1);
            }
            return day;
        };

        /** Feld, für das der Kalender gerade geöffnet ist: 'arrival', 'departure' oder null (geschlossen). */
        let active = null;
        let viewMonth = (arrival.value || min).slice(0, 7);
        let focusDate = arrival.value || min;
        let reopening = false;

        popup.hidden = true;
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', labels.heading);
        const header = document.createElement('div');
        header.className = 'fewo-quote__calendar-header';
        const hint = document.createElement('strong');
        hint.className = 'fewo-quote__calendar-hint';
        hint.setAttribute('aria-live', 'polite');
        const navButton = (label, symbol, step) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fewo-quote__calendar-nav';
            button.setAttribute('aria-label', label);
            button.textContent = symbol;
            button.addEventListener('click', () => {
                viewMonth = addMonths(viewMonth, step);
                render();
            });
            return button;
        };
        const previous = navButton(labels.previous, '‹', -1);
        const next = navButton(labels.next, '›', 1);
        header.append(hint, previous, next);
        const months = document.createElement('div');
        months.className = 'fewo-quote__calendar-months';
        const legend = document.createElement('ul');
        legend.className = 'fewo-quote__calendar-legend';
        [['', labels.legendFree], ['is-booked is-booked-am', labels.legendBooked], ['is-selected', labels.legendSelected]].forEach(([classes, text]) => {
            const item = document.createElement('li');
            const sample = document.createElement('span');
            sample.className = ('fewo-quote__day ' + classes).trim();
            sample.setAttribute('aria-hidden', 'true');
            item.append(sample, ' ' + text);
            legend.appendChild(item);
        });
        popup.replaceChildren(header, months, legend);

        /** Was ein Klick auf den Tag bewirkt: 'departure', 'arrival' oder null (nicht wählbar). */
        const actionFor = (iso) => {
            if (iso < min || iso > max) {
                return null;
            }
            if (active === 'departure' && arrival.value && iso > arrival.value && iso <= lastDeparture(arrival.value)) {
                return 'departure';
            }
            return isBooked(iso) || iso >= max ? null : 'arrival';
        };

        const describe = (iso, action) => {
            let state = labels.free;
            if (iso < min) {
                state = labels.past;
            } else if (iso === arrival.value) {
                state = labels.arrival;
            } else if (iso === departure.value) {
                state = labels.departure;
            } else if (isBooked(iso) && action !== 'departure') {
                state = labels.booked;
            }
            return dayFormat.format(fromIso(iso)) + ': ' + state;
        };

        /** Zustand aller sichtbaren Tage aktualisieren (ohne den Kalender neu aufzubauen). */
        const refresh = () => {
            const from = arrival.value;
            const to = departure.value;
            if (!months.querySelector('[data-date="' + focusDate + '"]')) {
                focusDate = viewMonth + '-01';
            }
            months.querySelectorAll('[data-date]').forEach((button) => {
                const iso = button.dataset.date;
                const action = actionFor(iso);
                const classes = button.classList;
                classes.toggle('is-past', iso < min);
                classes.toggle('is-booked', isBooked(iso));
                classes.toggle('is-booked-am', isBooked(addDays(iso, -1)));
                classes.toggle('is-selected', Boolean(from) && (iso === from || (Boolean(to) && iso > from && iso <= to)));
                classes.toggle('is-arrival', iso === from);
                classes.toggle('is-departure', iso === to);
                classes.toggle('is-disabled', action === null);
                button.setAttribute('aria-disabled', action === null ? 'true' : 'false');
                button.setAttribute('aria-label', describe(iso, action));
                button.tabIndex = iso === focusDate ? 0 : -1;
            });
            hint.textContent = active === 'departure' && from ? labels.selectDeparture : labels.selectArrival;
            previous.disabled = viewMonth <= min.slice(0, 7);
            next.disabled = addMonths(viewMonth, 1) >= max.slice(0, 7);
        };

        const render = () => {
            months.replaceChildren();
            [viewMonth, addMonths(viewMonth, 1)].forEach((month) => {
                const section = document.createElement('div');
                section.className = 'fewo-quote__calendar-month';
                const title = document.createElement('div');
                title.className = 'fewo-quote__calendar-month-title';
                title.textContent = monthFormat.format(fromIso(month + '-01'));
                const weekdays = document.createElement('div');
                weekdays.className = 'fewo-quote__calendar-weekdays';
                weekdays.setAttribute('aria-hidden', 'true');
                for (let i = 0; i < 7; i++) {
                    const cell = document.createElement('span');
                    // Der 5. Januar 2026 war ein Montag
                    cell.textContent = weekdayFormat.format(fromIso(addDays('2026-01-05', i)));
                    weekdays.appendChild(cell);
                }
                const grid = document.createElement('div');
                grid.className = 'fewo-quote__calendar-days';
                const first = fromIso(month + '-01');
                // Leerfelder vor dem Ersten (Woche beginnt am Montag)
                for (let i = (first.getUTCDay() + 6) % 7; i > 0; i--) {
                    grid.appendChild(document.createElement('span'));
                }
                for (let iso = month + '-01'; iso.slice(0, 7) === month; iso = addDays(iso, 1)) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'fewo-quote__day';
                    button.dataset.date = iso;
                    button.textContent = String(Number(iso.slice(8)));
                    grid.appendChild(button);
                }
                section.append(title, weekdays, grid);
                months.appendChild(section);
            });
            refresh();
        };

        /** Kalender unter das Feld setzen, zu dem er gehört. */
        const place = () => {
            const field = fields[active].display.closest('.fewo-quote__field');
            popup.style.top = (field.offsetTop + field.offsetHeight + 6) + 'px';
            popup.style.left = Math.max(0, Math.min(field.offsetLeft, form.clientWidth - popup.offsetWidth)) + 'px';
        };

        const open = (name) => {
            if (reopening) {
                return;
            }
            // Abreise ohne Anreise: erst die Anreise wählen
            active = name === 'departure' && !arrival.value ? 'arrival' : name;
            const current = fields[active].value.value || arrival.value || min;
            viewMonth = current.slice(0, 7);
            focusDate = current;
            Object.entries(fields).forEach(([key, { display }]) => display.setAttribute('aria-expanded', key === name ? 'true' : 'false'));
            popup.hidden = false;
            render();
            place();
        };

        const close = (focusField) => {
            if (active === null) {
                return;
            }
            const name = active;
            active = null;
            popup.hidden = true;
            Object.values(fields).forEach(({ display }) => display.setAttribute('aria-expanded', 'false'));
            if (focusField) {
                // Fokus zurück ins Feld, ohne den Kalender gleich wieder zu öffnen
                reopening = true;
                fields[focusField === true ? name : focusField].display.focus();
                reopening = false;
            }
        };

        const select = (iso) => {
            const action = actionFor(iso);
            if (action === null) {
                return;
            }
            focusDate = iso;
            if (action === 'departure') {
                departure.value = iso;
                showValues();
                close('departure');
                form.dispatchEvent(new CustomEvent('fewo:stay', { bubbles: true }));
                return;
            }
            // Anreise gewählt: weiter mit der Abreise
            arrival.value = iso;
            departure.value = '';
            showValues();
            active = 'departure';
            Object.entries(fields).forEach(([key, { display }]) => display.setAttribute('aria-expanded', key === 'departure' ? 'true' : 'false'));
            refresh();
            place();
            form.dispatchEvent(new CustomEvent('fewo:stay', { bubbles: true }));
        };

        const focusDay = () => {
            const button = months.querySelector('[data-date="' + focusDate + '"]');
            if (button) {
                button.focus();
            }
        };

        Object.entries(fields).forEach(([name, { display }]) => {
            display.addEventListener('focus', () => open(name));
            display.addEventListener('click', () => open(name));
            display.addEventListener('keydown', (event) => {
                if (['Enter', ' ', 'ArrowDown'].includes(event.key)) {
                    // mit der Tastatur in den Kalender wechseln
                    event.preventDefault();
                    if (active === null) {
                        open(name);
                    }
                    focusDay();
                } else if (event.key === 'Escape') {
                    close(false);
                }
            });
        });

        months.addEventListener('click', (event) => {
            const button = event.target.closest('[data-date]');
            if (button) {
                select(button.dataset.date);
            }
        });

        popup.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(true);
                return;
            }
            // Pfeiltasten bewegen den Fokus tageweise bzw. wochenweise, auch über den Monatswechsel hinweg
            const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            const button = event.target.closest('[data-date]');
            if (!button || !(event.key in steps)) {
                return;
            }
            event.preventDefault();
            const target = addDays(button.dataset.date, steps[event.key]);
            if (target < min.slice(0, 7) + '-01' || target > max) {
                return;
            }
            focusDate = target;
            if (!months.querySelector('[data-date="' + target + '"]')) {
                viewMonth = addMonths(viewMonth, steps[event.key] < 0 ? -1 : 1);
                render();
            } else {
                refresh();
            }
            focusDay();
        });

        // Schließen bei Klick außerhalb oder wenn der Fokus Felder und Kalender verlässt
        const isInside = (node) => node instanceof Node && (popup.contains(node) || Object.values(fields).some(({ display }) => display === node));
        document.addEventListener('pointerdown', (event) => {
            if (active !== null && !isInside(event.target)) {
                close(false);
            }
        });
        form.addEventListener('focusout', (event) => {
            if (active !== null && event.relatedTarget && !isInside(event.relatedTarget)) {
                close(false);
            }
        });
        window.addEventListener('resize', () => active !== null && place());

        showValues();

        return {
            /** Fehlt ein Datum, öffnet sich der Kalender am betreffenden Feld. @returns {boolean} true, wenn beide gewählt sind */
            requireStay() {
                const missing = !arrival.value ? 'arrival' : (!departure.value ? 'departure' : null);
                if (missing) {
                    open(missing);
                    focusDay();
                }
                return missing === null;
            },
            /** Fehlermeldung, wenn der gewählte Zeitraum eine Belegung berührt – sonst null. */
            check() {
                const complete = arrival.value && departure.value && departure.value > arrival.value;
                return complete && !isRangeFree(arrival.value, departure.value) ? labels.unavailable : null;
            },
        };
    };

    const init = (form) => {
        if (form.dataset.fewoReady) {
            return;
        }
        form.dataset.fewoReady = '1';

        const root = form.closest('.fewo-quote');
        const result = root.querySelector('[data-fewo-quote-result]');
        const button = form.querySelector('[type="submit"]');
        const popup = form.querySelector('[data-fewo-quote-calendar]');
        // Mit Belegung: eigener Kalender an den Feldern. Ohne: die Datumsfelder des Browsers.
        const picker = popup ? createDatePicker(popup, form) : null;

        const showError = (message) => {
            const error = document.createElement('p');
            error.className = 'fewo-quote__error';
            error.setAttribute('role', 'alert');
            error.textContent = message;
            result.replaceChildren(error);
        };
        /** @returns {boolean} true, wenn der gewählte Zeitraum frei ist */
        const checkAvailability = () => {
            const message = picker ? picker.check() : null;
            if (message) {
                showError(message);
            } else if (result.querySelector('.fewo-quote__error')) {
                result.replaceChildren();
            }
            return !message;
        };

        if (picker) {
            // Ein zuvor berechneter Preis gilt nicht mehr, sobald ein anderer Zeitraum gewählt wird
            form.addEventListener('fewo:stay', () => result.replaceChildren());
        } else {
            const arrival = form.elements.arrival;
            const departure = form.elements.departure;
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
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (picker ? !picker.requireStay() : !form.reportValidity()) {
                return;
            }
            if (!checkAvailability()) {
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
