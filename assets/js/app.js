/* ==========================================================================
   e-RESERVE — Front-end behaviour
   Bootstrap 5.3 · SweetAlert2 · DataTables · Font Awesome
   ========================================================================== */

(function () {
    'use strict';

    /* ----------------------------------------------------------------------
       Shared SweetAlert2 presets so every dialog matches the brand
    ---------------------------------------------------------------------- */
    const BRAND = {
        green: '#0B3B2C',
        gold: '#E9A93A',
        danger: '#D9483B'
    };

    window.eReserve = {
        toast(icon, title) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon,
                title: title,
                showConfirmButton: false,
                timer: 2600,
                timerProgressBar: true
            });
        },

        confirm(opts) {
            return Swal.fire({
                icon: opts.icon || 'question',
                title: opts.title,
                html: opts.html || '',
                showCancelButton: true,
                confirmButtonText: opts.confirmText || 'Confirm',
                cancelButtonText: opts.cancelText || 'Cancel',
                reverseButtons: true,
                focusCancel: false
            });
        },

        info(title, html, icon) {
            return Swal.fire({
                icon: icon || 'info',
                title: title,
                html: html || '',
                confirmButtonText: 'Got it'
            });
        },

        /* Used by every "UI only" control so nothing feels dead in a demo. */
        soon(feature) {
            Swal.fire({
                icon: 'info',
                iconColor: BRAND.gold,
                title: feature,
                html: '<p class="mb-0">This control is wired for the prototype presentation. ' +
                      'It will be connected once the MySQL database and PayMongo API go live.</p>',
                confirmButtonText: 'Understood'
            });
        },

        /* Fake processing spinner — used by the mock checkout. */
        processing(title, ms) {
            return new Promise(function (resolve) {
                let timer;
                Swal.fire({
                    title: title || 'Processing…',
                    html: '<p class="mb-0 text-soft">Please do not close this window.</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen() {
                        Swal.showLoading();
                        timer = setTimeout(function () {
                            Swal.close();
                            resolve();
                        }, ms || 1800);
                    },
                    willClose() { clearTimeout(timer); }
                });
            });
        }
    };

    /* ----------------------------------------------------------------------
       Countdown — any element with [data-countdown="ISO date"]
    ---------------------------------------------------------------------- */
    function initCountdowns() {
        const nodes = document.querySelectorAll('[data-countdown]');
        if (!nodes.length) return;

        function pad(n) { return String(n).padStart(2, '0'); }

        function tick() {
            nodes.forEach(function (node) {
                const target = new Date(node.dataset.countdown).getTime();
                let diff = target - Date.now();

                if (diff <= 0) {
                    node.querySelectorAll('.cd-num').forEach(function (el) { el.textContent = '00'; });
                    const live = node.querySelector('[data-cd-live]');
                    if (live) live.textContent = 'The night has arrived!';
                    return;
                }

                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);

                const set = function (key, val) {
                    const el = node.querySelector('[data-cd="' + key + '"]');
                    if (el) el.textContent = key === 'days' ? String(d) : pad(val);
                };
                set('days', d); set('hours', h); set('minutes', m); set('seconds', s);
            });
        }

        tick();
        setInterval(tick, 1000);
    }

    /* ----------------------------------------------------------------------
       Sticky public navbar
    ---------------------------------------------------------------------- */
    function initNavbar() {
        const nav = document.querySelector('.nav-public');
        if (!nav) return;
        const onScroll = function () { nav.classList.toggle('scrolled', window.scrollY > 40); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ----------------------------------------------------------------------
       Dashboard sidebar (mobile)
    ---------------------------------------------------------------------- */
    function initSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('[data-sidebar-toggle]');
        const backdrop = document.querySelector('.sidebar-backdrop');
        if (!sidebar || !toggle) return;

        const close = function () {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('show');
        };

        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('show', sidebar.classList.contains('open'));
        });

        if (backdrop) backdrop.addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    }

    /* ----------------------------------------------------------------------
       Scroll reveal
    ---------------------------------------------------------------------- */
    function initReveal() {
        const items = document.querySelectorAll('.reveal');
        if (!items.length) return;

        if (!('IntersectionObserver' in window)) {
            items.forEach(function (i) { i.classList.add('in'); });
            return;
        }

        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

        items.forEach(function (i) { io.observe(i); });
    }

    /* ----------------------------------------------------------------------
       Animated stat counters
    ---------------------------------------------------------------------- */
    function initCounters() {
        const nodes = document.querySelectorAll('[data-count-to]');
        if (!nodes.length || !('IntersectionObserver' in window)) return;

        const run = function (el) {
            const to = parseFloat(el.dataset.countTo);
            const decimals = parseInt(el.dataset.countDecimals || '0', 10);
            const prefix = el.dataset.countPrefix || '';
            const suffix = el.dataset.countSuffix || '';
            const dur = 1100;
            const start = performance.now();

            const step = function (now) {
                const p = Math.min((now - start) / dur, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                const val = to * eased;
                el.textContent = prefix + val.toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }) + suffix;
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { run(e.target); io.unobserve(e.target); }
            });
        }, { threshold: 0.4 });

        nodes.forEach(function (n) { io.observe(n); });
    }

    /* ----------------------------------------------------------------------
       DataTables — any table with .js-datatable
    ---------------------------------------------------------------------- */
    function initTables() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) return;

        jQuery('.js-datatable').each(function () {
            const $t = jQuery(this);
            if (jQuery.fn.DataTable.isDataTable($t)) return;

            $t.DataTable({
                pageLength: parseInt($t.data('page-length') || 10, 10),
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                order: $t.data('order-col') !== undefined
                    ? [[parseInt($t.data('order-col'), 10), $t.data('order-dir') || 'desc']]
                    : [],
                autoWidth: false,
                language: {
                    search: '',
                    searchPlaceholder: 'Search records…',
                    lengthMenu: 'Show _MENU_',
                    info: 'Showing _START_–_END_ of _TOTAL_ records',
                    infoEmpty: 'No records to show',
                    zeroRecords: 'No matching records found',
                    paginate: { previous: '<i class="fa-solid fa-chevron-left"></i>', next: '<i class="fa-solid fa-chevron-right"></i>' }
                },
                columnDefs: [{ targets: 'no-sort', orderable: false }]
            });
        });
    }

    /* ----------------------------------------------------------------------
       Generic UI-only buttons: [data-soon="Label"]
    ---------------------------------------------------------------------- */
    function initSoonButtons() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-soon]');
            if (!btn) return;
            e.preventDefault();
            window.eReserve.soon(btn.dataset.soon);
        });
    }

    /* ----------------------------------------------------------------------
       Confirm-before-navigate: [data-confirm-*]
    ---------------------------------------------------------------------- */
    function initConfirmLinks() {
        document.addEventListener('click', function (e) {
            const el = e.target.closest('[data-confirm-title]');
            if (!el) return;
            e.preventDefault();

            const href = el.getAttribute('href') || el.dataset.href;
            window.eReserve.confirm({
                icon: el.dataset.confirmIcon || 'question',
                title: el.dataset.confirmTitle,
                html: el.dataset.confirmText || '',
                confirmText: el.dataset.confirmYes || 'Yes, continue',
                cancelText: el.dataset.confirmNo || 'Not now'
            }).then(function (r) {
                if (r.isConfirmed && href) window.location.href = href;
            });
        });
    }

    /* ----------------------------------------------------------------------
       Copy-to-clipboard: [data-copy="text"]
    ---------------------------------------------------------------------- */
    function initCopy() {
        document.addEventListener('click', function (e) {
            const el = e.target.closest('[data-copy]');
            if (!el) return;
            e.preventDefault();
            const text = el.dataset.copy;
            const done = function () { window.eReserve.toast('success', 'Copied to clipboard'); };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); done(); } catch (err) { /* noop */ }
                document.body.removeChild(ta);
            }
        });
    }

    /* ----------------------------------------------------------------------
       Progress bars animate from 0 on reveal
    ---------------------------------------------------------------------- */
    function initProgress() {
        const fills = document.querySelectorAll('.prog-fill[data-width]');
        if (!fills.length) return;
        requestAnimationFrame(function () {
            setTimeout(function () {
                fills.forEach(function (f) { f.style.width = f.dataset.width + '%'; });
            }, 180);
        });
    }

    /* ----------------------------------------------------------------------
       Boot
    ---------------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initNavbar();
        initSidebar();
        initCountdowns();
        initReveal();
        initCounters();
        initTables();
        initSoonButtons();
        initConfirmLinks();
        initCopy();
        initProgress();

        // Bootstrap tooltips
        if (window.bootstrap) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    });
})();
