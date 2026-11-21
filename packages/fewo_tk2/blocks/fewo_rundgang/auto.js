/* Fewo Toolkit – Szenen-Editor für den 360°-Rundgang (Block-Dialog; wird von form.php gestartet)
 *
 * Räume: 360°-Bilder aus der Dateiverwaltung mit einem Namen. Je Raum: Startposition, Wegweiser zu
 * anderen Räumen (mit Blickrichtung bei der Ankunft) und Hinweise auf besondere Stellen.
 * Der Zustand steht als JSON im versteckten Feld "tour" (Aufbau siehe FewoTk2\Tour\TourConfig).
 */
window.FewoTourEditor = (options) => {
    'use strict';

    const { root, labels } = options;
    /** Dateien der Räume: fID => {url, thumbnail} */
    const files = options.files || {};
    const input = root.querySelector('input[name="tour"]');
    const state = (() => {
        try {
            const data = JSON.parse(input.value || '{}') || {};
            return { first: data.first || '', scenes: Array.isArray(data.scenes) ? data.scenes : [] };
        } catch (e) {
            return { first: '', scenes: [] };
        }
    })();

    const roomsBody = root.querySelector('[data-rooms]');
    const sceneHeading = root.querySelector('[data-scene-heading]');
    const sceneEmpty = root.querySelector('[data-scene-empty]');
    const sceneBody = root.querySelector('[data-scene-body]');
    const viewerElement = root.querySelector('[data-viewer]');
    const statusElement = root.querySelector('[data-status]');
    const hotSpotsElement = root.querySelector('[data-hotspots]');
    const toolbarButtons = root.querySelectorAll('[data-set-start], [data-place]');
    const defaultHeading = sceneHeading.textContent;

    /** Raum, der gerade im Editor bearbeitet wird */
    let current = null;
    let viewer = null;
    /** Nächster Klick ins Panorama setzt etwas: {type: 'scene'|'info'} oder {move: Wegweiser/Hinweis} */
    let placing = null;
    /** Ankunftsrichtung eines Wegweisers wird im Zielraum festgelegt: {from: Raum, hotSpot} */
    let arrival = null;

    // ---------------------------------------------------------------- Hilfsfunktionen

    const format = (text, ...values) => text.replace(/%(?:(\d+)\$)?s/g, (match, index) => String(values[index ? index - 1 : 0]));
    const round = (value) => Math.round(Number(value) * 100) / 100;
    const hasImage = (scene) => Boolean(scene && scene.fID && files[scene.fID]);
    const sceneByName = (name) => state.scenes.find((scene) => scene.name === name) || null;
    const displayTitle = (scene) => scene.title || scene.name;
    const baseName = (path) => String(path || '').split(/[\\/]/).pop().split('?')[0].toLowerCase();

    /** Winkel wie auf dem Server: waagerecht -180…180°, senkrecht -90…90° (356° wird zu -4°). */
    const normalizeYaw = (value) => {
        let angle = Number(value) % 360;
        if (!Number.isFinite(angle)) {
            return 0;
        }
        if (angle > 180) {
            angle -= 360;
        } else if (angle <= -180) {
            angle += 360;
        }
        return round(angle);
    };
    const normalizePitch = (value) => Math.max(-90, Math.min(90, normalizeYaw(value)));

    /** Name eines Raums: Kleinbuchstaben, Ziffern, "-" und "_", beginnt mit einem Buchstaben (wie TourConfig::slug). */
    const slug = (value) => {
        let name = String(value || '').trim().toLowerCase()
            .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
            .replace(/[^a-z0-9_-]+/g, '-').replace(/-{2,}/g, '-').replace(/^[-_]+|[-_]+$/g, '');
        if (name === '') {
            return 'raum';
        }
        if (!/^[a-z]/.test(name)) {
            name = 'raum-' + name;
        }
        return name.slice(0, 40);
    };
    const uniqueName = (name, except) => {
        let candidate = name;
        for (let i = 2; state.scenes.some((scene) => scene !== except && scene.name === candidate); i++) {
            candidate = name + '-' + i;
        }
        return candidate;
    };

    const save = () => {
        input.value = JSON.stringify(state);
    };

    const element = (tag, attributes, ...children) => {
        const node = document.createElement(tag);
        Object.entries(attributes || {}).forEach(([key, value]) => {
            if (key === 'class') {
                node.className = value;
            } else if (key in node && key !== 'list') {
                node[key] = value;
            } else {
                node.setAttribute(key, value);
            }
        });
        node.append(...children);
        return node;
    };
    const button = (text, className, onClick, attributes) => {
        const node = element('button', Object.assign({ type: 'button', class: 'btn btn-sm ' + className }, attributes), text);
        node.addEventListener('click', onClick);
        return node;
    };

    const setStatus = (text, actions) => {
        statusElement.replaceChildren(text || '');
        statusElement.classList.toggle('is-prompt', Boolean(actions));
        (actions || []).forEach((action) => statusElement.append(' ', action));
    };

    // ---------------------------------------------------------------- Dateiverwaltung

    /** Liest Adresse, Titel und Vorschaubild einer Datei. */
    const loadFile = (fID) => new Promise((resolve) => {
        window.ConcreteFileManager.getFileDetails(fID, (response) => {
            const file = response && response.files && response.files[0];
            if (!file) {
                resolve(null);
                return;
            }
            const thumbnail = element('div');
            thumbnail.innerHTML = file.resultsThumbnailImg || '';
            const image = thumbnail.querySelector('img');
            files[file.fID] = { url: file.url, thumbnail: image ? image.getAttribute('src') : file.url };
            resolve(file);
        });
    });

    const chooseFiles = (multiple, callback) => {
        window.ConcreteFileManager.launchDialog((data) => {
            const ids = [].concat(data.fID).map(Number).filter((id) => id > 0);
            Promise.all(ids.map(loadFile)).then((loaded) => callback(loaded.filter(Boolean)));
        }, { multipleSelection: multiple, filters: [{ field: 'type', type: '1' }] });
    };

    const newScene = (name, fID, title) => ({
        name: uniqueName(slug(name)),
        fID: fID,
        title: title,
        yaw: 0,
        pitch: 0,
        hfov: 100,
        horizonPitch: 0,
        horizonRoll: 0,
        hotSpots: [],
    });

    const addImages = () => chooseFiles(true, (chosen) => {
        chosen.forEach((file) => {
            const title = String(file.title || file.fileName || '').replace(/\.[a-z0-9]{2,5}$/i, '').replace(/[_]+/g, ' ').trim();
            state.scenes.push(newScene(title, file.fID, title));
        });
        if (!sceneByName(state.first) && state.scenes.length) {
            state.first = state.scenes[0].name;
        }
        save();
        if (!current) {
            current = state.scenes.find(hasImage) || null;
        }
        renderRooms();
        renderEditor();
    });

    // ---------------------------------------------------------------- Räume

    const renameScene = (scene, value) => {
        const name = uniqueName(slug(value), scene);
        const previous = scene.name;
        scene.name = name;
        state.scenes.forEach((other) => other.hotSpots.forEach((hotSpot) => {
            if (hotSpot.target === previous) {
                hotSpot.target = name;
            }
        }));
        if (state.first === previous) {
            state.first = name;
        }
    };

    const removeScene = (scene) => {
        if (!window.confirm(format(labels.removeRoom, displayTitle(scene)))) {
            return;
        }
        state.scenes.splice(state.scenes.indexOf(scene), 1);
        state.scenes.forEach((other) => {
            other.hotSpots = other.hotSpots.filter((hotSpot) => hotSpot.target !== scene.name);
        });
        if (state.first === scene.name) {
            state.first = state.scenes.length ? state.scenes[0].name : '';
        }
        if (current === scene || (arrival && (arrival.from === scene || current === scene))) {
            current = null;
            arrival = null;
            placing = null;
        }
        save();
        renderRooms();
        renderEditor();
    };

    const moveScene = (scene, step) => {
        const index = state.scenes.indexOf(scene);
        const target = index + step;
        if (target < 0 || target >= state.scenes.length) {
            return;
        }
        state.scenes.splice(target, 0, state.scenes.splice(index, 1)[0]);
        save();
        renderRooms();
    };

    const selectScene = (scene) => {
        current = scene;
        arrival = null;
        placing = null;
        renderRooms();
        renderEditor();
    };

    const renderRooms = () => {
        roomsBody.replaceChildren();
        if (!state.scenes.length) {
            roomsBody.append(element('tr', {}, element('td', { colSpan: 5, class: 'text-muted' }, labels.noRooms)));
            return;
        }
        state.scenes.forEach((scene, index) => {
            const thumbnail = hasImage(scene)
                ? element('img', { class: 'fewo-tour-editor__thumb', src: files[scene.fID].thumbnail, alt: '' })
                : element('span', { class: 'fewo-tour-editor__thumb fewo-tour-editor__thumb--missing' }, labels.noImage);

            const nameInput = element('input', { type: 'text', class: 'form-control form-control-sm', value: scene.name, maxLength: 40, 'aria-label': labels.name, 'data-room-name': '' });
            nameInput.addEventListener('change', () => {
                renameScene(scene, nameInput.value);
                save();
                renderRooms();
                renderHotSpots();
            });
            const titleInput = element('input', { type: 'text', class: 'form-control form-control-sm', value: scene.title || '', maxLength: 255, placeholder: labels.titlePlaceholder, 'aria-label': labels.title, 'data-room-title': '' });
            titleInput.addEventListener('input', () => {
                scene.title = titleInput.value;
                save();
                if (scene === current) {
                    sceneHeading.textContent = format(labels.editing, displayTitle(scene));
                }
            });
            titleInput.addEventListener('change', renderHotSpots);

            const first = element('input', { type: 'radio', class: 'form-check-input', name: root.id + '-first', checked: state.first === scene.name, 'aria-label': labels.startRoom });
            first.addEventListener('change', () => {
                state.first = scene.name;
                save();
            });

            const actions = element('td', { class: 'text-end text-nowrap' });
            if (hasImage(scene)) {
                actions.append(button(labels.edit, scene === current ? 'btn-primary' : 'btn-outline-primary', () => selectScene(scene), { 'data-room-edit': '' }), ' ');
            }
            actions.append(
                button(hasImage(scene) ? labels.changeImage : labels.chooseImage, 'btn-outline-secondary', () => chooseFiles(false, (chosen) => {
                    if (!chosen.length) {
                        return;
                    }
                    scene.fID = chosen[0].fID;
                    save();
                    renderRooms();
                    if (current === scene || !current) {
                        current = scene;
                        renderRooms();
                        renderEditor();
                    }
                })), ' ',
                button('↑', 'btn-outline-secondary', () => moveScene(scene, -1), { title: labels.moveUp, 'aria-label': labels.moveUp, disabled: index === 0 }), ' ',
                button('↓', 'btn-outline-secondary', () => moveScene(scene, 1), { title: labels.moveDown, 'aria-label': labels.moveDown, disabled: index === state.scenes.length - 1 }), ' ',
                button('×', 'btn-outline-danger', () => removeScene(scene), { title: labels.remove, 'aria-label': labels.remove })
            );

            roomsBody.append(element('tr', { class: scene === current ? 'is-current' : '', 'data-room': scene.name },
                element('td', {}, thumbnail),
                element('td', {}, nameInput),
                element('td', {}, titleInput),
                element('td', { class: 'text-center' }, first),
                actions));
        });
    };

    // ---------------------------------------------------------------- Panorama

    const currentView = () => (viewer ? { yaw: viewer.getYaw(), pitch: viewer.getPitch(), hfov: viewer.getHfov() } : null);

    const destroyViewer = () => {
        if (viewer) {
            try {
                viewer.destroy();
            } catch (e) {
                // Viewer war noch nicht fertig geladen
            }
            viewer = null;
        }
        viewerElement.replaceChildren();
    };

    /** Baut das Panorama des aktuellen Raums neu auf; ohne Angabe in der Startposition des Raums. */
    const buildViewer = (view) => {
        destroyViewer();
        if (!hasImage(current)) {
            return;
        }
        const start = view || { yaw: current.yaw, pitch: current.pitch, hfov: current.hfov };
        viewer = window.pannellum.viewer(viewerElement, {
            type: 'equirectangular',
            panorama: files[current.fID].url,
            autoLoad: true,
            showFullscreenCtrl: false,
            mouseZoom: false,
            keyboardZoom: false,
            yaw: start.yaw,
            pitch: start.pitch,
            hfov: start.hfov,
            horizonPitch: Number(current.horizonPitch) || 0,
            horizonRoll: Number(current.horizonRoll) || 0,
            // Wegweiser hier als Symbol ohne Funktion: im Editor soll ein Klick nicht den Raum wechseln
            hotSpots: current.hotSpots.map((hotSpot) => ({
                type: 'info',
                cssClass: 'pnlm-hotspot pnlm-sprite pnlm-' + (hotSpot.type === 'scene' ? 'scene' : 'info'),
                pitch: hotSpot.pitch,
                yaw: hotSpot.yaw,
                text: hotSpotText(hotSpot),
            })),
        });
        viewerElement.classList.toggle('is-placing', Boolean(placing));
    };

    const rebuildViewer = () => buildViewer(currentView());

    const hotSpotText = (hotSpot) => {
        if (hotSpot.type !== 'scene') {
            return hotSpot.text;
        }
        const target = sceneByName(hotSpot.target);
        return hotSpot.text || (target ? displayTitle(target) : '');
    };

    // Klick (kein Ziehen) ins Panorama, während etwas gesetzt wird
    let pointerDown = null;
    viewerElement.addEventListener('pointerdown', (event) => {
        pointerDown = { x: event.clientX, y: event.clientY };
    }, true);
    viewerElement.addEventListener('pointerup', (event) => {
        const start = pointerDown;
        pointerDown = null;
        if (!placing || !viewer || !start || Math.hypot(event.clientX - start.x, event.clientY - start.y) > 5) {
            return;
        }
        if (event.target.closest('.pnlm-controls-container, .pnlm-hotspot-base')) {
            return;
        }
        const [pitch, yaw] = viewer.mouseEventToCoords(event);
        place(round(pitch), round(yaw));
    }, true);

    const place = (pitch, yaw) => {
        let hotSpot = placing.move;
        if (hotSpot) {
            hotSpot.pitch = pitch;
            hotSpot.yaw = yaw;
        } else if (placing.type === 'scene') {
            const others = state.scenes.filter((scene) => scene !== current);
            hotSpot = { type: 'scene', pitch: pitch, yaw: yaw, text: '', target: (others.find(hasImage) || others[0]).name };
            current.hotSpots.push(hotSpot);
        } else {
            hotSpot = { type: 'info', pitch: pitch, yaw: yaw, text: '' };
            current.hotSpots.push(hotSpot);
        }
        placing = null;
        save();
        setStatus('');
        rebuildViewer();
        renderHotSpots();
        const row = hotSpotsElement.querySelectorAll('[data-hotspot]')[current.hotSpots.indexOf(hotSpot)];
        const field = row && row.querySelector('input[type="text"]');
        if (field) {
            field.focus();
        }
    };

    const startPlacing = (mode, message) => {
        placing = mode;
        viewerElement.classList.add('is-placing');
        setStatus(message, [button(labels.cancel, 'btn-outline-secondary', () => {
            placing = null;
            viewerElement.classList.remove('is-placing');
            setStatus('');
        })]);
    };

    // ---------------------------------------------------------------- Wegweiser und Hinweise

    const startArrival = (hotSpot) => {
        const target = sceneByName(hotSpot.target);
        if (!hasImage(target)) {
            return;
        }
        arrival = { from: current, hotSpot: hotSpot };
        placing = null;
        current = target;
        renderRooms();
        renderEditor();
    };

    const endArrival = (apply) => {
        if (apply && viewer) {
            arrival.hotSpot.targetYaw = round(viewer.getYaw());
            arrival.hotSpot.targetPitch = round(viewer.getPitch());
            save();
        }
        current = arrival.from;
        arrival = null;
        renderRooms();
        renderEditor();
    };

    const renderHotSpots = () => {
        hotSpotsElement.replaceChildren();
        if (!current || arrival) {
            return;
        }
        if (!current.hotSpots.length) {
            hotSpotsElement.append(element('p', { class: 'text-muted' }, labels.noHotSpots));
            return;
        }
        const body = element('tbody');
        current.hotSpots.forEach((hotSpot) => {
            const isSignpost = hotSpot.type === 'scene';
            const text = element('input', {
                type: 'text', class: 'form-control form-control-sm', value: hotSpot.text || '', maxLength: 500,
                placeholder: isSignpost ? labels.signpostText : labels.noteText, 'aria-label': labels.text,
            });
            text.addEventListener('input', () => {
                hotSpot.text = text.value;
                save();
            });
            text.addEventListener('change', rebuildViewer);

            const details = element('td');
            if (isSignpost) {
                const select = element('select', { class: 'form-select form-select-sm', 'aria-label': labels.target });
                state.scenes.filter((scene) => scene !== current).forEach((scene) => {
                    select.append(element('option', { value: scene.name, selected: scene.name === hotSpot.target }, displayTitle(scene)));
                });
                select.addEventListener('change', () => {
                    hotSpot.target = select.value;
                    // die Ankunftsrichtung galt für den bisherigen Zielraum
                    delete hotSpot.targetYaw;
                    delete hotSpot.targetPitch;
                    save();
                    rebuildViewer();
                    renderHotSpots();
                });
                const custom = typeof hotSpot.targetYaw === 'number';
                const arrivalInfo = element('div', { class: 'small text-muted mt-1 d-flex flex-wrap align-items-center gap-1' },
                    labels.arrival + ': ' + (custom ? labels.arrivalCustom : labels.arrivalDefault));
                if (hasImage(sceneByName(hotSpot.target))) {
                    arrivalInfo.append(button(labels.arrivalSet, 'btn-outline-secondary py-0', () => startArrival(hotSpot), { 'data-arrival-set': '' }));
                }
                if (custom) {
                    arrivalInfo.append(button(labels.arrivalReset, 'btn-outline-secondary py-0', () => {
                        delete hotSpot.targetYaw;
                        delete hotSpot.targetPitch;
                        save();
                        renderHotSpots();
                    }));
                }
                details.append(element('div', { class: 'd-flex align-items-center gap-2' }, element('span', { class: 'small text-nowrap' }, labels.target), select), arrivalInfo);
            }

            body.append(element('tr', { 'data-hotspot': hotSpot.type },
                element('td', {}, element('span', { class: 'badge ' + (isSignpost ? 'bg-primary' : 'bg-secondary') }, isSignpost ? labels.signpost : labels.note)),
                element('td', {}, text),
                details,
                element('td', { class: 'text-end text-nowrap' },
                    button(labels.show, 'btn-outline-secondary', () => viewer && viewer.lookAt(hotSpot.pitch, hotSpot.yaw, undefined, 600)), ' ',
                    button(labels.move, 'btn-outline-secondary', () => startPlacing({ move: hotSpot }, labels.placeMove)), ' ',
                    button('×', 'btn-outline-danger', () => {
                        current.hotSpots.splice(current.hotSpots.indexOf(hotSpot), 1);
                        save();
                        rebuildViewer();
                        renderHotSpots();
                    }, { title: labels.remove, 'aria-label': labels.remove }))));
        });
        hotSpotsElement.append(element('table', { class: 'table table-sm align-middle' }, body));
    };

    // ---------------------------------------------------------------- Editor für den gewählten Raum

    const renderEditor = () => {
        const editable = hasImage(current);
        sceneEmpty.hidden = editable;
        sceneBody.hidden = !editable;
        if (!editable) {
            current = null;
            sceneHeading.textContent = defaultHeading;
            destroyViewer();
            return;
        }
        sceneHeading.textContent = format(labels.editing, displayTitle(current));
        root.querySelectorAll('[data-horizon]').forEach((field) => {
            field.value = Number(current[field.dataset.horizon]) || 0;
            field.disabled = Boolean(arrival);
        });
        toolbarButtons.forEach((toolbarButton) => {
            toolbarButton.disabled = Boolean(arrival);
        });

        if (arrival) {
            // Im Zielraum: Blickrichtung bei der Ankunft über diesen Wegweiser
            const hotSpot = arrival.hotSpot;
            buildViewer(typeof hotSpot.targetYaw === 'number'
                ? { yaw: hotSpot.targetYaw, pitch: hotSpot.targetPitch || 0, hfov: current.hfov }
                : null);
            setStatus(format(labels.arrivalPrompt, displayTitle(current)), [
                button(labels.arrivalApply, 'btn-primary', () => endArrival(true), { 'data-arrival-apply': '' }),
                button(labels.cancel, 'btn-outline-secondary', () => endArrival(false)),
            ]);
        } else {
            buildViewer(null);
            setStatus(format(labels.startValue, round(current.yaw), round(current.pitch)));
        }
        renderHotSpots();
    };

    root.querySelector('[data-add-images]').addEventListener('click', addImages);

    root.querySelector('[data-set-start]').addEventListener('click', () => {
        if (!viewer || !current) {
            return;
        }
        current.yaw = round(viewer.getYaw());
        current.pitch = round(viewer.getPitch());
        current.hfov = round(viewer.getHfov());
        save();
        setStatus(labels.startSaved + ' ' + format(labels.startValue, current.yaw, current.pitch));
    });

    root.querySelectorAll('[data-place]').forEach((placeButton) => placeButton.addEventListener('click', () => {
        if (!current) {
            return;
        }
        if (placeButton.dataset.place === 'scene' && state.scenes.length < 2) {
            setStatus(labels.needSecondRoom);
            return;
        }
        startPlacing({ type: placeButton.dataset.place }, placeButton.dataset.place === 'scene' ? labels.placeSignpost : labels.placeNote);
    }));

    root.querySelectorAll('[data-horizon]').forEach((field) => field.addEventListener('change', () => {
        if (!current) {
            return;
        }
        current[field.dataset.horizon] = Math.max(-45, Math.min(45, round(field.value) || 0));
        save();
        rebuildViewer();
    }));

    // ---------------------------------------------------------------- bestehenden Rundgang übernehmen

    /** Liest Pannellum-Einstellungen: JSON oder die JavaScript-Schreibweise des alten Blocks. */
    const parseSettings = (code) => {
        let body = String(code).trim();
        if (body.charAt(0) !== '{') {
            body = '{' + body + '\n}';
        }
        try {
            return JSON.parse(body);
        } catch (e) {
            // kein reines JSON
        }
        return new Function('hotspotFontAwesome', '"use strict"; return (' + body + '\n);')(() => {});
    };

    const importSettings = () => {
        const field = root.querySelector('[data-import-code]');
        let settings;
        try {
            settings = parseSettings(field.value);
        } catch (error) {
            window.alert(format(labels.importFailed, error.message));
            return;
        }
        const defaults = (settings && settings.default) || {};
        let source = settings && settings.scenes;
        if ((!source || typeof source !== 'object') && settings && settings.panorama) {
            // einzelnes Panorama ohne "scenes"
            source = { panorama: settings };
        }
        if (!source || typeof source !== 'object' || !Object.keys(source).length) {
            window.alert(labels.importEmpty);
            return;
        }

        // Bereits gewählte Bilder: nach Dateiname bzw. als Platzhalter {name} zuordnen
        const pool = state.scenes.filter(hasImage).map((scene) => ({ scene: scene, file: baseName(files[scene.fID].url), used: false }));
        const names = {};
        const imported = [];
        state.scenes = [];
        Object.entries(source).forEach(([key, config]) => {
            const settingsOf = config || {};
            const panorama = String(settingsOf.panorama || '');
            const placeholder = panorama.match(/^\{([A-Za-z0-9_.-]+)\}$/);
            const match = pool.find((entry) => !entry.used && (placeholder ? entry.scene.name === placeholder[1] : entry.file === baseName(panorama)));
            if (match) {
                match.used = true;
            }
            const scene = newScene(key, match ? match.scene.fID : 0, String(settingsOf.title || ''));
            scene.yaw = normalizeYaw(settingsOf.yaw !== undefined ? settingsOf.yaw : defaults.yaw);
            scene.pitch = normalizePitch(settingsOf.pitch !== undefined ? settingsOf.pitch : defaults.pitch);
            scene.hfov = Number(settingsOf.hfov !== undefined ? settingsOf.hfov : defaults.hfov) || 100;
            scene.horizonPitch = Number(settingsOf.horizonPitch) || 0;
            scene.horizonRoll = Number(settingsOf.horizonRoll) || 0;
            scene.source = Array.isArray(settingsOf.hotSpots) ? settingsOf.hotSpots : [];
            names[key] = scene.name;
            state.scenes.push(scene);
            imported.push(scene);
        });
        imported.forEach((scene) => {
            scene.hotSpots = scene.source.filter((hotSpot) => hotSpot && typeof hotSpot === 'object').map((hotSpot) => {
                const entry = {
                    type: hotSpot.type === 'scene' ? 'scene' : 'info',
                    pitch: normalizePitch(hotSpot.pitch),
                    yaw: normalizeYaw(hotSpot.yaw),
                    text: String(hotSpot.text || ''),
                };
                if (entry.type === 'scene') {
                    entry.target = names[hotSpot.sceneId];
                    if (typeof hotSpot.targetYaw === 'number') {
                        entry.targetYaw = normalizeYaw(hotSpot.targetYaw);
                        entry.targetPitch = normalizePitch(hotSpot.targetPitch);
                    }
                }
                return entry;
            }).filter((entry) => (entry.type === 'scene' ? entry.target && entry.target !== scene.name : entry.text !== ''));
            delete scene.source;
        });
        // Bilder, die zu keinem Raum der Einstellungen gehören, bleiben als eigene Räume erhalten
        pool.filter((entry) => !entry.used).forEach((entry) => {
            entry.scene.name = uniqueName(entry.scene.name, entry.scene);
            entry.scene.hotSpots = [];
            state.scenes.push(entry.scene);
        });
        state.first = names[defaults.firstScene] || state.scenes[0].name;

        // Allgemeine Angaben in die Felder des Formulars
        const form = root.closest('form') || document;
        const author = form.querySelector('[name="author"]');
        if (author && !author.value && typeof defaults.author === 'string') {
            author.value = defaults.author.replace(/^\[|\]$/g, '');
        }
        const check = (name, value) => {
            const box = form.querySelector('input[type="checkbox"][name="' + name + '"]');
            if (box && typeof value === 'boolean') {
                box.checked = value;
            }
        };
        check('compass', defaults.compass);
        check('autoLoad', defaults.autoLoad);
        check('useCustomCode', false);

        save();
        current = state.scenes.find(hasImage) || null;
        arrival = null;
        placing = null;
        renderRooms();
        renderEditor();
        field.value = '';
        window.alert(format(labels.importDone, imported.length));
    };
    root.querySelector('[data-import]').addEventListener('click', importSettings);

    // ---------------------------------------------------------------- Start

    current = state.scenes.find(hasImage) || null;
    renderRooms();
    renderEditor();
};
