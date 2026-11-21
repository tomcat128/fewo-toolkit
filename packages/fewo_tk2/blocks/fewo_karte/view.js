/* Fewo Toolkit – Karte (Leaflet/OpenStreetMap mit Zwei-Klick-Lösung) */
(() => {
    'use strict';

    const STORAGE_KEY = 'fewo-map-consent';

    const hasStoredConsent = () => {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    };

    const storeConsent = () => {
        try {
            window.localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            // Speicher nicht verfügbar (z. B. privater Modus) – dann eben jedes Mal fragen
        }
    };

    const popupContent = (marker) => {
        const wrapper = document.createElement('div');
        if (marker.title) {
            const title = document.createElement('strong');
            title.textContent = marker.title;
            wrapper.appendChild(title);
        }
        if (marker.text) {
            const text = document.createElement('p');
            text.style.margin = marker.title ? '.25rem 0 0' : '0';
            text.style.whiteSpace = 'pre-line';
            text.textContent = marker.text;
            wrapper.appendChild(text);
        }
        return wrapper.childNodes.length ? wrapper : null;
    };

    const render = (canvas) => {
        if (canvas.dataset.fewoReady || typeof window.L === 'undefined') {
            return;
        }
        canvas.dataset.fewoReady = '1';

        const config = JSON.parse(canvas.dataset.fewoMap);
        const map = window.L.map(canvas, { scrollWheelZoom: false });
        window.L.tileLayer(config.tileUrl, {
            maxZoom: 19,
            attribution: config.attribution,
        }).addTo(map);

        const bounds = [];
        config.markers.forEach((marker) => {
            const leafletMarker = window.L.marker([marker.lat, marker.lng], { title: marker.title || '' }).addTo(map);
            const content = popupContent(marker);
            if (content) {
                leafletMarker.bindPopup(content);
            }
            bounds.push([marker.lat, marker.lng]);
        });

        if (config.zoom > 0 || bounds.length === 1) {
            map.setView(bounds[0], config.zoom || 15);
        } else {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
        }

        // Mausrad-Zoom erst nach Klick in die Karte (verhindert versehentliches Zoomen beim Scrollen)
        map.once('click', () => map.scrollWheelZoom.enable());
    };

    const init = (root) => {
        const canvas = root.querySelector('[data-fewo-map]');
        if (!canvas) {
            return;
        }
        const consent = root.querySelector('[data-fewo-map-consent]');
        if (!consent || hasStoredConsent()) {
            if (consent) {
                consent.remove();
            }
            render(canvas);
            return;
        }
        consent.querySelector('[data-fewo-map-load]').addEventListener('click', () => {
            if (consent.querySelector('[data-fewo-map-remember]').checked) {
                storeConsent();
                // Alle anderen Karten auf der Seite ebenfalls laden
                document.querySelectorAll('.fewo-map').forEach((other) => other !== root && init(other));
            }
            consent.remove();
            render(canvas);
        });
    };

    const boot = () => document.querySelectorAll('.fewo-map').forEach(init);
    if (document.readyState === 'complete') {
        boot();
    } else {
        // Leaflet wird im Footer geladen – daher auf "load" warten
        window.addEventListener('load', boot);
    }
})();
