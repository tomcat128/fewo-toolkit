/* Fewo Toolkit – Chat (KI-Assistent): schickt die Frage ab und zeigt die Antwort, während sie entsteht.
 * Für den Block "Chat" und für den Chat auf jeder Seite (unten rechts, siehe initWidget). */
(() => {
    'use strict';

    /** So viele frühere Nachrichten gehen mit jeder Frage mit (der Server begrenzt zusätzlich). */
    const MAX_HISTORY = 6;
    /** So viele Fragen mit Antwort merkt sich der Chat auf jeder Seite, solange der Tab offen ist. */
    const MAX_STORED = 20;

    /** Speicher des Browser-Tabs; nicht verfügbar z. B. bei gesperrten Cookies. */
    const storage = {
        get(key) {
            try {
                return window.sessionStorage.getItem(key);
            } catch (e) {
                return null;
            }
        },
        set(key, value) {
            try {
                window.sessionStorage.setItem(key, value);
            } catch (e) {
                // dann eben ohne Gedächtnis
            }
        },
    };

    const readJson = (value) => {
        try {
            return JSON.parse(value || '{}') || {};
        } catch (e) {
            return {};
        }
    };

    /** Modelle schreiben trotz Vorgabe gelegentlich Markdown – hier wird daraus schlichter Text. */
    const tidy = (text) => text
        .replace(/\*\*(.+?)\*\*/g, '$1')
        .replace(/^#{1,6}\s+/gm, '')
        .replace(/^\s*[*-]\s+/gm, '• ');

    const init = (chat) => {
        if (chat.dataset.fewoReady) {
            return;
        }
        chat.dataset.fewoReady = '1';

        const log = chat.querySelector('[data-fewo-chat-log]');
        const form = chat.querySelector('[data-fewo-chat-form]');
        const input = form.elements.question;
        const submit = form.querySelector('[type="submit"]');
        const examples = Array.from(chat.querySelectorAll('[data-fewo-chat-example]'));
        const labels = readJson(chat.dataset.labels);
        const history = [];
        // Chat auf jeder Seite: Das Gespräch bleibt beim Wechsel der Seite erhalten (nur im Browser des Besuchers)
        const storeKey = chat.dataset.persist ? 'fewo-chat:' + chat.dataset.persist : '';
        const stored = [];
        let token = chat.dataset.token || '';
        let busy = false;

        /** Token des Chats; beim Chat auf jeder Seite wird es erst bei Bedarf vom Server geholt. */
        const getToken = async () => {
            if (!token && chat.dataset.tokenUrl) {
                const response = await fetch(chat.dataset.tokenUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                token = String((await response.json()).token || '');
            }
            return token;
        };

        const scrollDown = () => {
            log.scrollTop = log.scrollHeight;
        };

        const addMessage = (role, text) => {
            const message = document.createElement('div');
            message.className = 'fewo-chat__message fewo-chat__message--' + role;
            const author = document.createElement('span');
            author.className = 'fewo-chat__author';
            author.textContent = role === 'user' ? labels.you : labels.assistant;
            const body = document.createElement('div');
            body.className = 'fewo-chat__text';
            body.textContent = text;
            message.append(author, body);
            log.append(message);
            scrollDown();
            return message;
        };

        const setBusy = (state) => {
            busy = state;
            submit.disabled = state;
            examples.forEach((button) => {
                button.disabled = state;
            });
        };

        const showSources = (message, sources) => {
            const list = (Array.isArray(sources) ? sources : []).filter((source) => source && typeof source.url === 'string' && source.url.charAt(0) === '/');
            if (!list.length) {
                return;
            }
            const paragraph = document.createElement('p');
            paragraph.className = 'fewo-chat__sources';
            paragraph.textContent = labels.sources;
            list.forEach((source) => {
                const link = document.createElement('a');
                link.href = source.url;
                link.textContent = source.title || source.url;
                paragraph.append(' ', link);
            });
            message.append(paragraph);
        };

        const ask = async (question) => {
            if (busy || !question) {
                return;
            }
            setBusy(true);
            chat.classList.add('is-started');
            addMessage('user', question);
            const message = addMessage('assistant', '');
            const body = message.querySelector('.fewo-chat__text');
            // Screenreader lesen die Antwort erst vor, wenn sie vollständig ist
            message.setAttribute('aria-busy', 'true');

            let answer = '';
            let error = '';
            let sources = [];
            const handle = (line) => {
                const event = line.trim() ? readJson(line) : {};
                if (typeof event.delta === 'string') {
                    answer += event.delta;
                    body.textContent = tidy(answer);
                    scrollDown();
                }
                if (event.error) {
                    error = String(event.error);
                }
                if (event.done) {
                    sources = event.sources;
                }
            };

            try {
                const data = new FormData();
                data.append('fewo_token', await getToken());
                data.append('question', question);
                data.append('history', JSON.stringify(history.slice(-MAX_HISTORY)));
                const response = await fetch(chat.dataset.url, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/x-ndjson, application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (response.status === 403 && chat.dataset.tokenUrl) {
                    // abgelaufen: beim nächsten Versuch ein neues Token holen
                    token = '';
                }
                if (response.body && response.body.getReader) {
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';
                    for (;;) {
                        const { value, done } = await reader.read();
                        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
                        const lines = buffer.split('\n');
                        buffer = done ? '' : lines.pop();
                        lines.forEach(handle);
                        if (done) {
                            break;
                        }
                    }
                } else {
                    (await response.text()).split('\n').forEach(handle);
                }
                if (!answer && !error) {
                    error = labels.failed;
                }
            } catch (e) {
                error = error || labels.failed;
            }

            message.removeAttribute('aria-busy');
            if (answer) {
                body.textContent = tidy(answer).trim();
                history.push({ role: 'user', content: question }, { role: 'assistant', content: answer });
                showSources(message, sources);
                if (storeKey) {
                    stored.push({ question, answer, sources: Array.isArray(sources) ? sources : [] });
                    storage.set(storeKey, JSON.stringify(stored.slice(-MAX_STORED)));
                }
            }
            if (error) {
                if (answer) {
                    addMessage('error', error);
                } else {
                    message.classList.add('fewo-chat__message--error');
                    body.textContent = error;
                }
            }
            scrollDown();
            setBusy(false);
            input.focus({ preventScroll: true });
        };

        // Der Server lässt das Sprachmodell die Website-Inhalte schon einlesen, während der Besucher noch tippt
        let warmed = false;
        const warmUp = async () => {
            if (warmed || !chat.dataset.warmUrl) {
                return;
            }
            warmed = true;
            try {
                const data = new FormData();
                data.append('fewo_token', await getToken());
                await fetch(chat.dataset.warmUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true });
            } catch (e) {
                // nur eine Vorbereitung – die Frage selbst meldet Fehler
            }
        };
        if (chat.closest('[data-fewo-chat-widget]')) {
            // Chat auf jeder Seite: erst beim Öffnen, sonst liefe das bei jedem Seitenaufruf
            chat.addEventListener('fewo:open', () => {
                warmUp();
                scrollDown();
            });
        } else {
            (window.requestIdleCallback || ((callback) => setTimeout(callback, 500)))(warmUp);
        }

        // gemerktes Gespräch wieder anzeigen
        if (storeKey) {
            const saved = readJson(storage.get(storeKey) || '[]');
            (Array.isArray(saved) ? saved : []).slice(-MAX_STORED).forEach((entry) => {
                if (!entry || typeof entry.question !== 'string' || typeof entry.answer !== 'string') {
                    return;
                }
                addMessage('user', entry.question);
                showSources(addMessage('assistant', tidy(entry.answer).trim()), entry.sources);
                history.push({ role: 'user', content: entry.question }, { role: 'assistant', content: entry.answer });
                stored.push(entry);
            });
            chat.classList.toggle('is-started', stored.length > 0);
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const question = input.value.trim();
            if (!question || busy) {
                return;
            }
            input.value = '';
            ask(question);
        });
        examples.forEach((button) => {
            button.addEventListener('click', () => ask(button.textContent.trim()));
        });
    };

    /**
     * Chat auf jeder Seite: unten rechts zuerst nur eine kleine Schaltfläche mit Hinweistext; ein Klick (oder
     * Eingabe-/Leertaste) öffnet den Chat. Ob er offen war, bleibt beim Wechsel der Seite erhalten.
     */
    const initWidget = (root) => {
        if (root.dataset.fewoReady) {
            return;
        }
        root.dataset.fewoReady = '1';
        // Die Seite enthält schon einen Chat-Block: kein zweiter Chat daneben
        if (document.querySelector('[data-fewo-chat]:not(.fewo-chat--widget)')) {
            return;
        }
        const OPEN_KEY = 'fewo-chat:widget-open';
        const launcher = root.querySelector('[data-fewo-chat-open]');
        const panel = root.querySelector('[data-fewo-chat-panel]');
        const chat = root.querySelector('[data-fewo-chat]');
        const input = chat.querySelector('[name="question"]');

        const open = (focus) => {
            panel.hidden = false;
            root.classList.add('is-open');
            launcher.setAttribute('aria-expanded', 'true');
            storage.set(OPEN_KEY, '1');
            chat.dispatchEvent(new CustomEvent('fewo:open'));
            if (focus) {
                input.focus({ preventScroll: true });
            }
        };
        const close = (focus) => {
            panel.hidden = true;
            root.classList.remove('is-open');
            launcher.setAttribute('aria-expanded', 'false');
            storage.set(OPEN_KEY, '0');
            if (focus) {
                launcher.focus({ preventScroll: true });
            }
        };
        launcher.addEventListener('click', () => open(true));
        root.querySelector('[data-fewo-chat-close]').addEventListener('click', () => close(true));
        root.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                close(true);
            }
        });

        root.hidden = false;
        if (storage.get(OPEN_KEY) === '1') {
            open(false);
        }
    };

    const boot = () => {
        document.querySelectorAll('[data-fewo-chat]').forEach(init);
        document.querySelectorAll('[data-fewo-chat-widget]').forEach(initWidget);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
