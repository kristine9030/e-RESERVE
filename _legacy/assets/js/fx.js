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
