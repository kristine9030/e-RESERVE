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
       Dashboard navbar — scroll shadow + auto-close of the mobile collapse
    ---------------------------------------------------------------------- */
    function initAppNav() {
        const nav = document.querySelector('.app-nav');
        if (!nav) return;

        const onScroll = function () { nav.classList.toggle('scrolled', window.scrollY > 8); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        const menu = nav.querySelector('.navbar-collapse');
        if (!menu || !window.bootstrap) return;

        /* Tapping a destination on mobile should close the collapsed menu. */
        menu.addEventListener('click', function (e) {
            const link = e.target.closest('a[href]');
            if (!link || link.classList.contains('dropdown-toggle')) return;
            if (!menu.classList.contains('show')) return;
            bootstrap.Collapse.getOrCreateInstance(menu).hide();
        });
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
            const form = el.form || null;          // a submit button: confirm, then send its form
            window.eReserve.confirm({
                icon: el.dataset.confirmIcon || 'question',
                title: el.dataset.confirmTitle,
                html: el.dataset.confirmText || '',
                confirmText: el.dataset.confirmYes || 'Yes, continue',
                cancelText: el.dataset.confirmNo || 'Not now'
            }).then(function (r) {
                if (!r.isConfirmed) return;
                if (form) {
                    // requestSubmit keeps the clicked button's name/value and runs submit handlers
                    if (typeof form.requestSubmit === 'function') { form.requestSubmit(el.type === 'submit' ? el : undefined); }
                    else { form.submit(); }
                } else if (href) {
                    window.location.href = href;
                }
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
       Official report form — plain-text row filter.
       The report table is deliberately NOT a DataTable: every row must stay
       in the DOM so the printed form and the export match the screen.
    ---------------------------------------------------------------------- */
    function initReportFilter() {
        document.querySelectorAll('.report-sheet').forEach(function (sheet) {
            const input = sheet.querySelector('[data-report-filter]');
            const badge = sheet.querySelector('[data-report-count]');
            const rows = sheet.querySelectorAll('.report-table tbody tr');
            if (!input || !rows.length) return;

            const total = rows.length;

            input.addEventListener('input', function () {
                const q = input.value.trim().toLowerCase();
                let shown = 0;

                rows.forEach(function (row) {
                    if (row.querySelector('.report-empty')) return;
                    const hit = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
                    row.style.display = hit ? '' : 'none';
                    if (hit) shown++;
                });

                if (badge) badge.textContent = shown + ' of ' + total;
            });
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
        /* Every password box gets an eye button to show or hide what was typed. */
    function initPasswordToggle() {
        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            if (input.dataset.pwToggle) { return; }
            input.dataset.pwToggle = '1';

            var host = input.closest('.input-group');
            if (!host) {
                host = document.createElement('div');
                host.className = 'pw-wrap';
                input.parentNode.insertBefore(host, input);
                host.appendChild(input);
            }
            host.classList.add('has-pw-toggle');

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pw-toggle';
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('aria-pressed', 'false');
            btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
            host.appendChild(btn);

            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.innerHTML = '<i class="fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
                input.focus();
            });
        });
    }
document.addEventListener('DOMContentLoaded', function () {
        initNavbar();
        initAppNav();
        initCountdowns();
        initReveal();
        initCounters();
        initPasswordToggle();
        initTables();
        initSoonButtons();
        initConfirmLinks();
        initCopy();
        initReportFilter();
        initProgress();

        // Bootstrap tooltips
        if (window.bootstrap) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    });
})();
