/* NGD Admin Panel — shared behaviour (no build step, no jQuery). */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    /* ── Toasts ─────────────────────────────────────────── */
    const Toast = window.Swal ? Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true }) : null;
    window.notify = (icon, title) => Toast ? Toast.fire({ icon, title }) : alert(title);

    const flash = document.getElementById('flash');
    if (flash) {
        if (flash.dataset.success) notify('success', flash.dataset.success);
        if (flash.dataset.error) notify('error', flash.dataset.error);
    }

    /* ── Mobile sidebar ─────────────────────────────────── */
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sidebar-open]')) document.body.classList.add('side-open');
        if (e.target.closest('[data-sidebar-close]')) document.body.classList.remove('side-open');
    });

    /* ── App → Category cascading selects ───────────────── */
    // A category can belong to many apps (data-apps="1,3,5"); show it when the
    // chosen app is one of them.
    function syncCategories(form) {
        const appSel = form.querySelector('[data-app-filter]');
        const catSel = form.querySelector('[data-category-filter]');
        if (!appSel || !catSel) return;
        const app = appSel.value;
        let resetNeeded = false;
        catSel.querySelectorAll('option[data-apps]').forEach((o) => {
            const ids = (o.dataset.apps || '').split(',').filter(Boolean);
            const show = !app || ids.includes(app);
            o.hidden = !show;
            o.disabled = !show;
            if (!show && o.selected) resetNeeded = true;
        });
        if (resetNeeded) catSel.value = '';
    }
    document.querySelectorAll('form').forEach(syncCategories);
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-app-filter]')) syncCategories(e.target.form);
    });

    /* ── AJAX list: search / filters / per-page / pagination ── */
    let timer = null;
    let controller = null;

    async function loadList(form, url) {
        const target = document.querySelector(form.dataset.target || '#list-area');
        if (!target) return;
        if (!url) {
            const params = new URLSearchParams(new FormData(form));
            [...params.keys()].forEach((k) => { if (params.get(k) === '') params.delete(k); });
            url = form.action.split('?')[0] + (params.toString() ? '?' + params : '');
        }
        controller?.abort();
        controller = new AbortController();
        target.classList.add('list-loading');
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
            const html = await res.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.querySelector(form.dataset.target || '#list-area');
            if (fresh) {
                target.innerHTML = fresh.innerHTML;
                history.replaceState(null, '', url);
                target.dispatchEvent(new CustomEvent('list:updated', { bubbles: true }));
            }
        } catch (err) {
            if (err.name !== 'AbortError') notify('error', 'Could not load the list.');
        } finally {
            target.classList.remove('list-loading');
        }
    }

    document.querySelectorAll('form[data-list-form]').forEach((form) => {
        form.addEventListener('submit', (e) => { e.preventDefault(); loadList(form); });
        form.addEventListener('input', (e) => {
            if (e.target.type !== 'search' && e.target.type !== 'text') return;
            clearTimeout(timer);
            timer = setTimeout(() => loadList(form), 350);
        });
        form.addEventListener('change', (e) => {
            if (e.target.tagName === 'SELECT') {
                if (e.target.matches('[data-app-filter]')) syncCategories(form);
                loadList(form);
            }
        });
        form.querySelector('[data-reset]')?.addEventListener('click', () => {
            form.reset();
            form.querySelectorAll('input, select').forEach((el) => { if (el.name !== 'per_page') el.value = ''; });
            syncCategories(form);
            loadList(form);
        });
    });

    document.addEventListener('click', (e) => {
        const link = e.target.closest('#list-area .pagination a.page-link');
        if (!link) return;
        const form = document.querySelector('form[data-list-form]');
        if (!form) return;
        e.preventDefault();
        loadList(form, link.href);
        document.getElementById('list-area')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /* ── Confirm before delete ──────────────────────────── */
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form.matches('form[data-confirm]') || form.dataset.confirmed) return;
        e.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: form.dataset.confirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
        }).then((r) => {
            if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); }
        });
    });

    /* ── Inline status / paid toggles ───────────────────── */
    document.addEventListener('change', async (e) => {
        const input = e.target;
        if (!input.matches('input[data-toggle-url]')) return;
        input.disabled = true;
        try {
            const body = new FormData();
            body.append('field', input.dataset.field || 'status');
            const res = await fetch(input.dataset.toggleUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body });
            if (!res.ok) throw new Error();
            const data = await res.json();
            input.checked = !!data.value;
            const label = input.closest('td, .toggle-row')?.querySelector('[data-toggle-label]');
            if (label) label.textContent = data.value ? (input.dataset.on || 'Active') : (input.dataset.off || 'Inactive');
            notify('success', 'Updated');
        } catch {
            input.checked = !input.checked;
            notify('error', 'Update failed');
        } finally {
            input.disabled = false;
        }
    });

    /* ── WebP-only enforcement ──────────────────────────── */
    // Attached FIRST (admin.js loads before any page script), so on a non-webp
    // selection it can stop other change handlers (preview, backdrop gallery).
    const isWebp = (f) => f.type === 'image/webp' || /\.webp$/i.test(f.name || '');
    document.querySelectorAll('input[type=file][data-webp-only]').forEach((input) => {
        input.addEventListener('change', (e) => {
            const bad = [...input.files].filter((f) => !isWebp(f));
            if (!bad.length) return;
            e.stopImmediatePropagation();
            input.value = '';
            const drop = input.closest('.drop');
            if (drop) drop.querySelector('.fname').textContent = '';
            const preview = input.dataset.preview && document.querySelector(input.dataset.preview);
            if (preview) preview.querySelectorAll('img:not([data-current])').forEach((n) => n.remove());
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Only .webp images allowed',
                    html: 'These files are not <code>.webp</code> and were not added:<br><b>' +
                        bad.map((f) => f.name).join('<br>') + '</b><br><br>Please convert them to WebP and try again.',
                    confirmButtonColor: '#4f46e5',
                });
            } else {
                notify('error', 'Only .webp images are allowed.');
            }
        });
    });

    /* ── File drop zones + image previews ───────────────── */
    document.querySelectorAll('.drop').forEach((drop) => {
        const input = drop.querySelector('input[type=file]');
        if (!input) return;
        ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.add('drag')));
        ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('drag')));
        input.addEventListener('change', () => {
            const files = [...input.files];
            const name = drop.querySelector('.fname');
            if (name) name.textContent = files.length > 1 ? `${files.length} files selected` : (files[0]?.name || '');
            const preview = input.dataset.preview && document.querySelector(input.dataset.preview);
            if (preview && files[0] && files[0].type.startsWith('image/')) {
                preview.innerHTML = '';
                const img = new Image();
                img.src = URL.createObjectURL(files[0]);
                preview.appendChild(img);
            }
        });
    });

    /* ── Copy to clipboard ──────────────────────────────── */
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        const src = btn.dataset.copy;
        const text = src.startsWith('#') ? document.querySelector(src)?.dataset.value || document.querySelector(src)?.textContent : src;
        try {
            await navigator.clipboard.writeText(text.trim());
            notify('success', 'Copied to clipboard');
        } catch {
            notify('error', 'Copy failed');
        }
    });
})();
