/* Fewo Toolkit – Bildergalerie mit Lightbox (natives <dialog>, keine Abhängigkeiten) */
(() => {
    'use strict';

    const init = (gallery) => {
        if (gallery.dataset.fewoReady) {
            return;
        }
        gallery.dataset.fewoReady = '1';

        const links = Array.from(gallery.querySelectorAll('.fewo-gallery__link'));
        const showCaptions = gallery.dataset.showCaptions === '1';
        let current = 0;
        let touchStartX = null;

        const dialog = document.createElement('dialog');
        dialog.className = 'fewo-lightbox';
        dialog.setAttribute('aria-label', gallery.dataset.labelCounter.replace('{index}', '1').replace('{total}', links.length));
        dialog.innerHTML = `
            <figure class="fewo-lightbox__figure">
                <img class="fewo-lightbox__image" alt="">
                <figcaption class="fewo-lightbox__caption"></figcaption>
            </figure>
            <p class="fewo-lightbox__counter" aria-live="polite"></p>
            <button type="button" class="fewo-lightbox__button fewo-lightbox__close">&times;</button>
            <button type="button" class="fewo-lightbox__button fewo-lightbox__prev">&#8249;</button>
            <button type="button" class="fewo-lightbox__button fewo-lightbox__next">&#8250;</button>`;
        dialog.querySelector('.fewo-lightbox__close').setAttribute('aria-label', gallery.dataset.labelClose);
        dialog.querySelector('.fewo-lightbox__prev').setAttribute('aria-label', gallery.dataset.labelPrev);
        dialog.querySelector('.fewo-lightbox__next').setAttribute('aria-label', gallery.dataset.labelNext);
        document.body.appendChild(dialog);

        const image = dialog.querySelector('.fewo-lightbox__image');
        const caption = dialog.querySelector('.fewo-lightbox__caption');
        const counter = dialog.querySelector('.fewo-lightbox__counter');
        const single = links.length < 2;
        dialog.querySelector('.fewo-lightbox__prev').hidden = single;
        dialog.querySelector('.fewo-lightbox__next').hidden = single;

        const preload = (index) => {
            const link = links[(index + links.length) % links.length];
            if (link) {
                (new Image()).src = link.href;
            }
        };

        const show = (index) => {
            current = (index + links.length) % links.length;
            const link = links[current];
            const thumb = link.querySelector('img');
            image.src = link.href;
            image.width = Number(link.dataset.width) || 0;
            image.height = Number(link.dataset.height) || 0;
            image.alt = thumb ? thumb.alt : '';
            caption.textContent = showCaptions ? link.dataset.caption : '';
            caption.hidden = !caption.textContent;
            counter.textContent = gallery.dataset.labelCounter
                .replace('{index}', String(current + 1))
                .replace('{total}', String(links.length));
            preload(current + 1);
            preload(current - 1);
        };

        const open = (index) => {
            show(index);
            dialog.showModal();
            document.documentElement.classList.add('fewo-lightbox-open');
        };

        dialog.addEventListener('close', () => {
            document.documentElement.classList.remove('fewo-lightbox-open');
            links[current].focus();
        });

        links.forEach((link, index) => link.addEventListener('click', (event) => {
            if (typeof dialog.showModal !== 'function') {
                return; // Sehr alte Browser öffnen einfach das Bild
            }
            event.preventDefault();
            open(index);
        }));

        dialog.querySelector('.fewo-lightbox__close').addEventListener('click', () => dialog.close());
        dialog.querySelector('.fewo-lightbox__prev').addEventListener('click', () => show(current - 1));
        dialog.querySelector('.fewo-lightbox__next').addEventListener('click', () => show(current + 1));

        // Klick auf den Hintergrund schließt. Die Bildfläche (figure) füllt den ganzen Dialog,
        // ein Klick neben das Bild trifft deshalb sie und nicht den Dialog selbst.
        const figure = dialog.querySelector('.fewo-lightbox__figure');
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog || event.target === figure) {
                dialog.close();
            }
        });

        dialog.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                show(current - 1);
            } else if (event.key === 'ArrowRight') {
                show(current + 1);
            }
        });

        // Wischgesten
        dialog.addEventListener('touchstart', (event) => {
            touchStartX = event.changedTouches[0].clientX;
        }, { passive: true });
        dialog.addEventListener('touchend', (event) => {
            if (touchStartX === null) {
                return;
            }
            const delta = event.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(delta) > 50) {
                show(current + (delta < 0 ? 1 : -1));
            }
        });
    };

    const boot = () => document.querySelectorAll('[data-fewo-gallery]').forEach(init);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
