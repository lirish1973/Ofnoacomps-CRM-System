/**
 * Ofnoacomps Post Badges — Frontend JS  v1.0.2
 *
 * Wraps images that carry data-opb-* attributes with the badge overlay.
 * Works with:
 *   • Elementor Loop / Posts widget  (element_ready hook + MutationObserver)
 *   • Classic themes & Gutenberg     (DOMContentLoaded / load)
 *   • Lazy-load & AJAX pagination    (MutationObserver)
 *   • Elementor popups & carousels   (MutationObserver)
 */
(function () {
  'use strict';

  /* ── Core wrap function ─────────────────────────────────────────────────── */

  function wrapBadges(root) {
    root = root || document;
    var imgs = root.querySelectorAll('img[data-opb-badge]:not([data-opb-done])');

    imgs.forEach(function (img) {
      var text  = img.getAttribute('data-opb-badge');
      var type  = img.getAttribute('data-opb-type')  || 'square';
      var pos   = img.getAttribute('data-opb-pos')   || 'top-right';
      var style = img.getAttribute('data-opb-style') || '';

      if (!text) return;

      // Mark as processed — prevents double-wrapping on repeated calls
      img.setAttribute('data-opb-done', '1');

      // ── Build wrapper ──────────────────────────────────────────────────
      var wrap = document.createElement('div');
      wrap.className = 'opb-wrap';

      // Inherit parent's display so we don't break flex/grid card layouts
      var parentDisplay = window.getComputedStyle(img.parentNode).display;
      if (parentDisplay === 'flex' || parentDisplay === 'inline-flex') {
        wrap.style.display = 'flex';
        wrap.style.flexDirection = 'column';
      }

      img.parentNode.insertBefore(wrap, img);
      wrap.appendChild(img);

      // ── Build badge ────────────────────────────────────────────────────
      var badge = document.createElement('span');
      badge.className = 'opb-badge opb-type--' + type + ' opb-pos--' + pos;
      badge.textContent = text;
      badge.setAttribute('style', style);
      badge.setAttribute('aria-hidden', 'true');
      wrap.appendChild(badge);
    });
  }

  /* ── MutationObserver — catches lazy-loaded / AJAX / Elementor widgets ── */

  var _observerTimer = null;

  function scheduleWrap() {
    clearTimeout(_observerTimer);
    _observerTimer = setTimeout(function () { wrapBadges(); }, 120);
  }

  function startObserver() {
    if (!window.MutationObserver) return;
    var observer = new MutationObserver(function (mutations) {
      // Only react if at least one added node contains (or is) an image
      var relevant = mutations.some(function (m) {
        return Array.prototype.some.call(m.addedNodes, function (n) {
          return n.nodeType === 1 &&
            (n.tagName === 'IMG' || n.querySelector('img'));
        });
      });
      if (relevant) scheduleWrap();
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  /* ── Initial run ────────────────────────────────────────────────────────── */

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      wrapBadges();
      startObserver();
    });
  } else {
    wrapBadges();
    startObserver();
  }

  /* ── Elementor: re-run after each widget finishes rendering ─────────────── */

  window.addEventListener('load', function () {
    // Run once more on full load (catches widgets that render after DOM ready)
    wrapBadges();

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
      window.elementorFrontend.hooks.addAction(
        'frontend/element_ready/global',
        function ($scope) {
          // $scope is a jQuery object; pass the raw DOM element as root
          wrapBadges($scope && $scope[0] ? $scope[0] : document);
        }
      );
    }
  });

  /* ── Public API — for manual calls from custom themes / scripts ──────────── */
  window.OPB = { wrap: wrapBadges };

}());
