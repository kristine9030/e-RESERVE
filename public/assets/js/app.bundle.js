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
        const fills = document.querySelectorAll('.prog-fill[data-width], .cr-fill[data-width]');
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

/* Real phone screens get the enlarged touch sizes of the landing page (see mobile.css). */
if (Math.min(window.screen.width, window.screen.height) < 600) document.documentElement.classList.add('is-phone');

/* Public menu: close the hamburger after a link is tapped (anchor links stay on the page). */
(function () {
    var menu = document.getElementById('publicNav');
    if (!menu || typeof bootstrap === 'undefined') return;
    menu.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (a && menu.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(menu).hide();
    });
})();

/* Navbar (phones): bell panel and hamburger menu never stay open together */
document.addEventListener('DOMContentLoaded', function () {
    var menu = document.getElementById('appNavMenu');
    var bellBtn = document.querySelector('.bell-mobile .bell-btn');
    if (!menu || !bellBtn || !window.bootstrap) { return; }
    bellBtn.addEventListener('show.bs.dropdown', function () {
        if (menu.classList.contains('show')) { bootstrap.Collapse.getOrCreateInstance(menu).hide(); }
    });
    menu.addEventListener('show.bs.collapse', function () {
        var dd = bootstrap.Dropdown.getInstance(bellBtn);
        if (dd) { dd.hide(); }
    });
});

/* ----------------------------------------------------------------------
   Live regions: any element with data-live="name" is refreshed in place.
   Every few seconds the page is fetched again in the background and only the
   regions whose content changed are swapped, so tables and counters stay
   current without a reload (and without touching forms, the camera or modals).
   window.liveRefresh() refreshes at once, e.g. right after a scan.
---------------------------------------------------------------------- */
(function () {
    if (!document.querySelector('[data-live]') || !window.fetch || !window.DOMParser) return;

    var EVERY = 3000, busy = false, again = false;

    function settle(root) {
        root.querySelectorAll('.fade-up').forEach(function (n) { n.classList.remove('fade-up'); });   // no replay of the entrance animation
        root.querySelectorAll('[data-count-to]').forEach(function (n) {                               // counters show the final number straight away
            var dec = parseInt(n.dataset.countDecimals || '0', 10);
            n.textContent = (n.dataset.countPrefix || '') +
                parseFloat(n.dataset.countTo).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) +
                (n.dataset.countSuffix || '');
        });
    }

    function refresh() {
        if (document.hidden) return;
        if (busy) { again = true; return; }
        busy = true;

        fetch(location.href, {
            credentials: 'same-origin', cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        }).then(function (r) {
            // Signed out (sent to the login page) or an error: leave the page as it is.
            if (!r.ok || (r.redirected && new URL(r.url).pathname !== location.pathname)) throw new Error('skip');
            return r.text();
        }).then(function (html) {
            var next = new DOMParser().parseFromString(html, 'text/html');
            document.querySelectorAll('[data-live]').forEach(function (el) {
                var fresh = next.querySelector('[data-live="' + el.getAttribute('data-live') + '"]');
                if (!fresh) return;
                settle(fresh);
                if (fresh.innerHTML.trim() === el.innerHTML.trim()) return;
                el.innerHTML = fresh.innerHTML;
                el.classList.remove('live-flash'); void el.offsetWidth; el.classList.add('live-flash');
            });
        }).catch(function (e) { if (window.console && e && e.message !== 'skip') console.warn('live refresh failed', e); })
          .then(function () {
              busy = false;
              if (again) { again = false; refresh(); }
          });
    }

    window.liveRefresh = refresh;
    setInterval(refresh, EVERY);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    window.addEventListener('online', refresh);
})();

;
/* ==========================================================================
   e-RESERVE — Motion layer
   · [data-tilt]       3D tilt that follows the pointer (and device tilt on phones)
   · [data-glare]      a moving highlight across a tilted surface
   · [data-depth]      parallax drift for floating decorations
   · .reveal-group     staggered entrance for children
   Everything is skipped for visitors who prefer reduced motion.
   ========================================================================== */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

    /* ---------- Tilt ---------- */
    function setupTilt(el) {
        var max  = parseFloat(el.dataset.tilt) || 10;
        var rest = (el.dataset.tiltRest || '0,0,0').split(',').map(parseFloat);
        var rx0 = rest[0] || 0, ry0 = rest[1] || 0, rz0 = rest[2] || 0;
        var glare = null;

        if (el.hasAttribute('data-glare')) {
            glare = document.createElement('span');
            glare.className = 'tilt-glare';
            el.appendChild(glare);
        }

        function apply(rx, ry, rz) {
            el.style.setProperty('--rx', rx.toFixed(2) + 'deg');
            el.style.setProperty('--ry', ry.toFixed(2) + 'deg');
            el.style.setProperty('--rz', rz.toFixed(2) + 'deg');
        }
        apply(rx0, ry0, rz0);

        if (reduce) { return; }

        var frame = null;
        function onMove(e) {
            var r  = el.getBoundingClientRect();
            var px = ((e.clientX - r.left) / r.width) - .5;     // -.5 … .5
            var py = ((e.clientY - r.top) / r.height) - .5;

            if (frame) { cancelAnimationFrame(frame); }
            frame = requestAnimationFrame(function () {
                apply(rx0 - py * max, ry0 + px * max * 1.2, rz0);
                el.classList.add('is-tilting');
                if (glare) {
                    glare.style.setProperty('--gx', ((px + .5) * 100).toFixed(1) + '%');
                    glare.style.setProperty('--gy', ((py + .5) * 100).toFixed(1) + '%');
                }
            });
        }
        function onLeave() {
            if (frame) { cancelAnimationFrame(frame); }
            el.classList.remove('is-tilting');
            apply(rx0, ry0, rz0);
        }

        if (!coarse) {
            el.addEventListener('pointermove', onMove);
            el.addEventListener('pointerleave', onLeave);
        }
    }

    document.querySelectorAll('[data-tilt]').forEach(setupTilt);

    /* On phones, let the ticket react to how the device is held (where allowed). */
    if (coarse && !reduce && 'DeviceOrientationEvent' in window) {
        var hero = document.querySelector('.ticket3d.main');
        if (hero) {
            window.addEventListener('deviceorientation', function (e) {
                if (e.gamma == null) { return; }
                var rest = (hero.dataset.tiltRest || '0,0,0').split(',').map(parseFloat);
                hero.style.setProperty('--ry', (rest[1] + Math.max(-20, Math.min(20, e.gamma)) * .4).toFixed(2) + 'deg');
                hero.style.setProperty('--rx', (rest[0] - Math.max(-20, Math.min(20, (e.beta || 45) - 45)) * .3).toFixed(2) + 'deg');
            }, { passive: true });
        }
    }

    /* ---------- Parallax scene (hero floating elements) ---------- */
    document.querySelectorAll('[data-parallax-scene]').forEach(function (scene) {
        var items = scene.querySelectorAll('[data-depth]');
        if (!items.length || reduce || coarse) { return; }

        var frame = null;
        scene.addEventListener('pointermove', function (e) {
            var r  = scene.getBoundingClientRect();
            var px = ((e.clientX - r.left) / r.width) - .5;
            var py = ((e.clientY - r.top) / r.height) - .5;

            if (frame) { cancelAnimationFrame(frame); }
            frame = requestAnimationFrame(function () {
                items.forEach(function (it) {
                    var d = parseFloat(it.dataset.depth) || 0;
                    it.style.setProperty('--px', (-px * d).toFixed(1) + 'px');
                    it.style.setProperty('--py', (-py * d).toFixed(1) + 'px');
                });
            });
        });
        scene.addEventListener('pointerleave', function () {
            items.forEach(function (it) { it.style.setProperty('--px', '0px'); it.style.setProperty('--py', '0px'); });
        });
    });

    /* ---------- Staggered children ---------- */
    document.querySelectorAll('.reveal-group').forEach(function (group) {
        Array.prototype.forEach.call(group.children, function (child, i) {
            child.style.transitionDelay = (i * 80) + 'ms';
            child.classList.add('reveal');
        });
    });

    /* ---------- Scroll progress bar ---------- */
    var bar = document.createElement('div');
    bar.className = 'scroll-progress';
    document.body.appendChild(bar);
    var tick = false;
    window.addEventListener('scroll', function () {
        if (tick) { return; }
        tick = true;
        requestAnimationFrame(function () {
            var h = document.documentElement.scrollHeight - window.innerHeight;
            bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, window.scrollY / h) : 0) + ')';
            tick = false;
        });
    }, { passive: true });
})();

;
