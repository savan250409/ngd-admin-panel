/*
 * Admin media protection (client side). Loaded only when ADMIN_MEDIA_SECURITY is on.
 * - Every secured <img> (src = /secure-media?token=...) is redrawn onto a <canvas>,
 *   so Inspect shows no image URL and there is nothing to "Save image as".
 * - <video> elements lose the download / PiP / remote-playback controls.
 * - Right-click and drag are blocked on media.
 *
 * Performance: the <img> keep their native loading="lazy", so off-screen rows are
 * never fetched. Each image is redrawn to a canvas only once it has actually loaded
 * (i.e. when it scrolls into view), so long lists stay light on both network and CPU.
 *
 * Fails safe: if anything throws, the original element is left in place.
 */
(function () {
    'use strict';

    const isSecured = (url) => typeof url === 'string' && url.indexOf('/secure-media') !== -1;

    function drawCover(ctx, img, w, h, fit) {
        const iw = img.naturalWidth, ih = img.naturalHeight;
        if (!iw || !ih) return;
        if (fit === 'cover') {
            const s = Math.max(w / iw, h / ih);
            const sw = w / s, sh = h / s;
            ctx.drawImage(img, (iw - sw) / 2, (ih - sh) / 2, sw, sh, 0, 0, w, h);
        } else if (fit === 'contain') {
            const s = Math.min(w / iw, h / ih);
            const dw = iw * s, dh = ih * s;
            ctx.drawImage(img, 0, 0, iw, ih, (w - dw) / 2, (h - dh) / 2, dw, dh);
        } else {
            ctx.drawImage(img, 0, 0, w, h);
        }
    }

    function render(img) {
        try {
            if (!img.isConnected || !img.naturalWidth) return;
            const rect = img.getBoundingClientRect();
            const w = Math.round(rect.width || img.naturalWidth || 100);
            const h = Math.round(rect.height || img.naturalHeight || 100);
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const canvas = document.createElement('canvas');
            canvas.width = w * dpr;
            canvas.height = h * dpr;
            canvas.className = img.className;
            const st = img.getAttribute('style');
            if (st) canvas.setAttribute('style', st);
            canvas.style.width = w + 'px';
            canvas.style.height = h + 'px';
            const ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);
            drawCover(ctx, img, w, h, getComputedStyle(img).objectFit);
            canvas.setAttribute('draggable', 'false');
            canvas.setAttribute('aria-label', img.alt || 'image');
            img.replaceWith(canvas);
        } catch (e) { /* keep original img */ }
    }

    function handleImg(img) {
        try {
            if (!img || img.dataset.secured === '1' || !isSecured(img.getAttribute('src'))) return;
            img.dataset.secured = '1';
            // Draw once the image has loaded. With loading="lazy" that happens only
            // when it scrolls into view, so off-screen rows cost nothing until seen.
            if (img.complete && img.naturalWidth) render(img);
            else img.addEventListener('load', () => render(img), { once: true });
        } catch (e) { /* ignore */ }
    }

    function hardenVideo(v) {
        try {
            if (!v || v.dataset.secured === '1') return;
            v.dataset.secured = '1';
            v.setAttribute('controlsList', 'nodownload noplaybackrate noremoteplayback');
            v.setAttribute('disablePictureInPicture', '');
            v.setAttribute('disableRemotePlayback', '');
        } catch (e) { /* ignore */ }
    }

    function scan(root) {
        try {
            root.querySelectorAll('img').forEach(handleImg);
            root.querySelectorAll('video').forEach(hardenVideo);
        } catch (e) { /* ignore */ }
    }

    const blockOnMedia = (e) => {
        const t = e.target;
        if (t && /^(IMG|VIDEO|CANVAS)$/.test(t.tagName) && !t.closest('[data-allow-drag]')) e.preventDefault();
    };

    function start() {
        document.addEventListener('contextmenu', blockOnMedia);
        document.addEventListener('dragstart', blockOnMedia);
        scan(document);
        new MutationObserver((muts) => {
            muts.forEach((m) => m.addedNodes.forEach((n) => {
                if (n.nodeType !== 1) return;
                if (n.tagName === 'IMG') handleImg(n);
                else if (n.tagName === 'VIDEO') hardenVideo(n);
                else scan(n);
            }));
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
