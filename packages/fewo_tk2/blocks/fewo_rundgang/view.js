/* Fewo Toolkit – 360°-Rundgang (Pannellum) */
(() => {
    'use strict';

    /**
     * Hilfsfunktionen, die in den Einstellungen verwendet werden können,
     * z. B. "createTooltipFunc": hotspotFontAwesome (wie im alten Pannellum-Block).
     */
    const helpers = {
        /** Hotspot mit einem Font-Awesome-Symbol: createTooltipArgs = {"fa-icon": "fa-bed", "fa-size": "20px"} */
        hotspotFontAwesome(hotSpotDiv, args) {
            const options = args || {};
            hotSpotDiv.classList.add('custom-tooltip');
            const icon = document.createElement('span');
            icon.classList.add('fa');
            if (options['fa-icon']) {
                icon.classList.add(options['fa-icon']);
            }
            if (options['fa-size']) {
                icon.style.fontSize = options['fa-size'];
            }
            icon.setAttribute('aria-hidden', 'true');
            hotSpotDiv.appendChild(icon);
            // Symbol im Hotspot zentrieren
            icon.style.marginLeft = (-(icon.scrollWidth - hotSpotDiv.offsetWidth) / 2 - 4) + 'px';
            icon.style.marginTop = (-(icon.scrollHeight - hotSpotDiv.offsetHeight) / 2 - 4) + 'px';
        },
    };

    /** Telefone und Tablets bekommen – wo vorhanden – die kleineren Bilder. */
    const useMobileImages = () => window.matchMedia('(pointer: coarse), (max-width: 767px)').matches;

    const readJson = (value) => {
        try {
            return JSON.parse(value || '{}') || {};
        } catch (e) {
            return {};
        }
    };

    /** Ersetzt die Platzhalter {name} durch die Adressen der Bilder. */
    const insertImages = (code, images) => code.replace(
        /\{([A-Za-z0-9_.-]+)\}/g,
        (placeholder, name) => (Object.prototype.hasOwnProperty.call(images, name) ? images[name] : placeholder),
    );

    /**
     * Liest die Einstellungen: bevorzugt als JSON, sonst in der JavaScript-Schreibweise des alten Blocks
     * (Schlüssel ohne Anführungszeichen, Kommentare, Funktionsnamen wie hotspotFontAwesome).
     */
    const parseSettings = (code) => {
        let body = code.trim();
        if (body.charAt(0) !== '{') {
            body = '{' + body + '\n}';
        }
        try {
            return JSON.parse(body);
        } catch (e) {
            // kein reines JSON
        }
        return new Function(...Object.keys(helpers), '"use strict"; return (' + body + '\n);')(...Object.values(helpers));
    };

    /** In JSON lassen sich Funktionen nur über ihren Namen angeben: "createTooltipFunc": "hotspotFontAwesome". */
    const resolveHelpers = (settings) => {
        const scenes = settings.scenes && typeof settings.scenes === 'object' ? Object.values(settings.scenes) : [];
        [settings, settings.default, ...scenes].forEach((part) => {
            (part && Array.isArray(part.hotSpots) ? part.hotSpots : []).forEach((hotSpot) => {
                if (typeof hotSpot.createTooltipFunc === 'string' && helpers[hotSpot.createTooltipFunc]) {
                    hotSpot.createTooltipFunc = helpers[hotSpot.createTooltipFunc];
                }
            });
        });
    };

    const showError = (container, error) => {
        const message = container.parentNode.querySelector('.fewo-tour__error');
        container.hidden = true;
        if (!message) {
            return;
        }
        message.hidden = false;
        const details = message.querySelector('[data-fewo-tour-details]');
        if (details && container.hasAttribute('data-show-details')) {
            details.textContent = String(error && error.message ? error.message : error);
        }
    };

    const init = (container) => {
        const images = Object.assign(
            readJson(container.dataset.images),
            useMobileImages() ? readJson(container.dataset.imagesMobile) : {},
        );
        try {
            const settings = parseSettings(insertImages(container.dataset.fewoTour, images));
            if (!settings || typeof settings !== 'object') {
                throw new Error('The settings are not an object.');
            }
            resolveHelpers(settings);
            container.fewoTour = window.pannellum.viewer(container, settings);
        } catch (error) {
            showError(container, error);
        }
    };

    const boot = () => document.querySelectorAll('[data-fewo-tour]').forEach(init);
    if (document.readyState === 'complete') {
        boot();
    } else {
        // Pannellum wird im Footer geladen – daher auf "load" warten
        window.addEventListener('load', boot);
    }
})();
