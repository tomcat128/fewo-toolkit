/* Fewo Toolkit – Chat (KI-Assistent): schickt die Frage ab und zeigt die Antwort, während sie entsteht */
(() => {
    'use strict';

    /** So viele frühere Nachrichten gehen mit jeder Frage mit (der Server begrenzt zusätzlich). */
    const MAX_HISTORY = 6;

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
        let busy = false;

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
                data.append('fewo_token', chat.dataset.token);
                data.append('question', question);
                data.append('history', JSON.stringify(history.slice(-MAX_HISTORY)));
                const response = await fetch(chat.dataset.url, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/x-ndjson, application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
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
        const warmUp = () => {
            const data = new FormData();
            data.append('fewo_token', chat.dataset.token);
            fetch(chat.dataset.warmUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(() => {});
        };
        if (chat.dataset.warmUrl) {
            (window.requestIdleCallback || ((callback) => setTimeout(callback, 500)))(warmUp);
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

    const boot = () => document.querySelectorAll('[data-fewo-chat]').forEach(init);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
