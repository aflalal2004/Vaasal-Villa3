// Vaasal Villa website — animations, video, gallery & lightbox
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // ---------------------------------------------------------------- header & back-to-top
  var header = document.querySelector('.site-header');
  var top = document.createElement('button');
  top.className = 'to-top'; top.setAttribute('aria-label', 'Back to top'); top.textContent = '↑';
  top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });
  document.body.appendChild(top);
  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    if (header) header.classList.toggle('scrolled', y > 40);
    top.classList.toggle('show', y > 600);
  }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // ---------------------------------------------------------------- scroll reveal + counters
  function countUp(el) {
    var end = parseFloat(el.getAttribute('data-count')), suf = el.getAttribute('data-suffix') || '', t0 = null;
    if (reduce) { el.textContent = end + suf; return; }
    function step(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / 1600), v = end * (1 - Math.pow(1 - p, 3));
      el.textContent = (end % 1 ? v.toFixed(1) : Math.round(v)) + suf;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var targets = $$('.reveal, [data-count]');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('in');
        if (en.target.hasAttribute('data-count')) countUp(en.target);
        io.unobserve(en.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    targets.forEach(function (t) { io.observe(t); });
  } else {
    targets.forEach(function (t) { t.classList.add('in'); if (t.hasAttribute('data-count')) countUp(t); });
  }

  // ---------------------------------------------------------------- videos
  // Background videos: honour reduced motion, fall back to the poster image on error,
  // and only play the promo band while it is on screen (saves bandwidth & battery).
  $$('video[data-bg]').forEach(function (v) {
    var fallback = function () {
      var poster = v.getAttribute('poster');
      if (!poster) { v.style.display = 'none'; return; }
      var img = document.createElement('img'); img.src = poster; img.alt = ''; v.replaceWith(img);
    };
    // Note: a <source> whose media query doesn't match (e.g. the phone video on desktop) also
    // fires "error", so only fall back once the LAST source has failed or the element itself errors.
    var failed = false, once = function () { if (!failed) { failed = true; fallback(); } };
    v.addEventListener('error', function (e) { if (e.target === v) once(); });
    var srcs = v.querySelectorAll('source');
    if (srcs.length) srcs[srcs.length - 1].addEventListener('error', once);
    if (v.error) once();
    if (reduce) { v.removeAttribute('autoplay'); v.pause(); }
  });
  var toggle = document.querySelector('.video-toggle');
  if (toggle) {
    var hv = document.querySelector('.hero-media video');
    var sync = function () { toggle.textContent = hv && !hv.paused ? '❚❚ Pause video' : '▶ Play video'; };
    if (hv) { hv.addEventListener('play', sync); hv.addEventListener('pause', sync); } else toggle.remove();
    toggle.addEventListener('click', function () { if (!hv) return; hv.paused ? hv.play() : hv.pause(); });
    sync();
  }
  $$('.video-band video').forEach(function (v) {
    if (reduce || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(function (e) {
      e.forEach(function (en) { if (en.isIntersecting) { var p = v.play(); if (p && p.catch) p.catch(function () {}); } else v.pause(); });
    }, { threshold: 0.25 }).observe(v);
  });
  $$('[data-play]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = document.getElementById(b.getAttribute('data-play')); if (!v) return;
      v.muted = false; v.controls = true; v.currentTime = 0; v.play();
      b.closest('.wrap').style.opacity = '0'; b.closest('.wrap').style.pointerEvents = 'none';
      v.addEventListener('pause', function () { b.closest('.wrap').style.opacity = ''; b.closest('.wrap').style.pointerEvents = ''; v.muted = true; v.controls = false; }, { once: true });
    });
  });

  // ---------------------------------------------------------------- lightbox
  var lb = null, items = [], idx = 0;
  function buildLb() {
    lb = document.createElement('div'); lb.className = 'lightbox'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
    lb.innerHTML = '<button class="x" aria-label="Close">✕</button><button class="prev" aria-label="Previous">‹</button><img alt=""><button class="next" aria-label="Next">›</button><div class="cap"></div>';
    document.body.appendChild(lb);
    lb.querySelector('.x').onclick = close;
    lb.querySelector('.prev').onclick = function (e) { e.stopPropagation(); show(idx - 1); };
    lb.querySelector('.next').onclick = function (e) { e.stopPropagation(); show(idx + 1); };
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
    document.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('open')) return;
      if (e.key === 'Escape') close(); if (e.key === 'ArrowLeft') show(idx - 1); if (e.key === 'ArrowRight') show(idx + 1);
    });
    var sx = 0;
    lb.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) { var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1)); });
  }
  function show(i) {
    idx = (i + items.length) % items.length;
    var it = items[idx], img = lb.querySelector('img');
    img.style.animation = 'none'; void img.offsetWidth; img.style.animation = '';
    img.src = it.getAttribute('data-full'); img.alt = it.getAttribute('data-caption') || '';
    lb.querySelector('.cap').textContent = (it.getAttribute('data-caption') || '') + '  ·  ' + (idx + 1) + ' / ' + items.length;
    var multi = items.length > 1;
    lb.querySelector('.prev').style.display = lb.querySelector('.next').style.display = multi ? '' : 'none';
  }
  function close() { lb.classList.remove('open'); document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-full]'); if (!t) return;
    e.preventDefault();
    if (!lb) buildLb();
    var group = t.getAttribute('data-group') || 'all';
    items = $$('[data-full][data-group="' + group + '"]').filter(function (x) { return !x.closest('.hide'); });
    if (!items.length) items = [t];
    lb.classList.add('open'); document.body.style.overflow = 'hidden';
    show(Math.max(0, items.indexOf(t)));
  });

  // ---------------------------------------------------------------- gallery filter
  $$('.gallery-filter').forEach(function (bar) {
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-filter]'); if (!b) return;
      $$('button', bar).forEach(function (x) { x.classList.toggle('on', x === b); });
      var f = b.getAttribute('data-filter');
      $$('.masonry figure').forEach(function (fig) { fig.classList.toggle('hide', f !== 'all' && fig.getAttribute('data-cat') !== f); });
    });
  });

  // ---------------------------------------------------------------- slider (room detail)
  $$('.slider').forEach(function (s) {
    var slides = $$('.slide', s), dots = $$('.dots button', s), thumbs = $$('.thumbs img', s.parentNode), cur = 0, timer = null;
    if (slides.length < 2) { $$('.nav-btn,.dots', s).forEach(function (x) { x.remove(); }); return; }
    function go(i) {
      cur = (i + slides.length) % slides.length;
      slides.forEach(function (x, k) { x.classList.toggle('on', k === cur); });
      dots.forEach(function (x, k) { x.classList.toggle('on', k === cur); });
      thumbs.forEach(function (x, k) { x.classList.toggle('on', k === cur); });
    }
    function auto() { if (reduce) return; clearInterval(timer); timer = setInterval(function () { go(cur + 1); }, 5000); }
    s.querySelector('.nav-btn.p').onclick = function () { go(cur - 1); auto(); };
    s.querySelector('.nav-btn.n').onclick = function () { go(cur + 1); auto(); };
    dots.forEach(function (d, k) { d.onclick = function () { go(k); auto(); }; });
    thumbs.forEach(function (t, k) { t.onclick = function () { go(k); auto(); }; });
    s.addEventListener('mouseenter', function () { clearInterval(timer); }); s.addEventListener('mouseleave', auto);
    go(0); auto();
  });

  // ---------------------------------------------------------------- booking date helpers
  $$('input[type=date][data-min-today]').forEach(function (el) { el.min = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10); });
  var ci = document.querySelector('[name=check_in]'), co = document.querySelector('[name=check_out]');
  if (ci && co) ci.addEventListener('change', function () {
    if (!ci.value) return; var d = new Date(ci.value); d.setDate(d.getDate() + 1); var n = d.toISOString().slice(0, 10);
    co.min = n; if (!co.value || co.value <= ci.value) co.value = n;
  });
})();
