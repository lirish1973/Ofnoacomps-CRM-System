/**
 * Ofnoacomps CRM — Frontend Tracker v3
 * - UTM / referrer / device → cookie
 * - Pageview tracking via AJAX
 * - Button, WhatsApp, tel, email click tracking
 * - WooCommerce: add_to_cart, remove_from_cart, view_cart,
 *                checkout_start, checkout_complete, cart_abandonment
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'ofnoacomps_crm_tracker';
    var COOKIE_DAYS = 30;

    // ── Utilities ──────────────────────────────────────────────────────────

    function getParam(name) {
        var url = new URL(window.location.href);
        return url.searchParams.get(name) || '';
    }

    function detectSource(utmSource, utmMedium, referrer) {
        if (utmSource) {
            var src = utmSource.toLowerCase();
            var med = (utmMedium || '').toLowerCase();
            if (src.indexOf('google') !== -1 && med === 'cpc') return 'google_ads';
            if (src.indexOf('facebook') !== -1 && (med === 'cpc' || med === 'paid')) return 'facebook_ads';
            if (src.indexOf('instagram') !== -1) return 'instagram';
            if (src.indexOf('email') !== -1 || med === 'email') return 'email';
            if (src.indexOf('whatsapp') !== -1) return 'whatsapp';
            return src;
        }
        if (!referrer) return 'direct';
        try {
            var ref = new URL(referrer);
            var host = ref.hostname.replace('www.', '');
            if (host === window.location.hostname.replace('www.', '')) return 'direct';
            if (/google\./.test(host)) return 'google_organic';
            if (/bing\./.test(host)) return 'bing_organic';
            if (/facebook\.com|fb\.com/.test(host)) return 'facebook_organic';
            if (/instagram\.com/.test(host)) return 'instagram';
            if (/linkedin\.com/.test(host)) return 'linkedin';
            if (/youtube\.com/.test(host)) return 'youtube';
            if (/twitter\.com|x\.com/.test(host)) return 'twitter';
            if (/waze\.com/.test(host)) return 'waze';
            return 'referral';
        } catch (e) { return 'direct'; }
    }

    function detectDevice() {
        var ua = navigator.userAgent;
        if (/tablet|ipad/i.test(ua)) return 'tablet';
        if (/mobile|android|iphone/i.test(ua)) return 'mobile';
        return 'desktop';
    }

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var d = new Date();
            d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
            expires = '; expires=' + d.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(JSON.stringify(value)) + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                try { return JSON.parse(decodeURIComponent(c.substring(nameEQ.length))); } catch (e) { return null; }
            }
        }
        return null;
    }

    function getSessionId() {
        var key = 'ofnoacomps_sid';
        try {
            var sid = sessionStorage.getItem(key);
            if (!sid) {
                sid = 'sid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem(key, sid);
            }
            return sid;
        } catch (e) { return 'sid_' + Date.now(); }
    }

    // ── AJAX / Beacon senders ──────────────────────────────────────────────

    function buildFormData(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', (window.ofnoacompsCRM && window.ofnoacompsCRM.nonce) || '');
        for (var k in data) {
            if (Object.prototype.hasOwnProperty.call(data, k)) {
                fd.append(k, data[k] !== undefined && data[k] !== null ? String(data[k]) : '');
            }
        }
        return fd;
    }

    function sendAjax(action, data) {
        if (!window.ofnoacompsCRM || !window.ofnoacompsCRM.ajaxUrl) return;
        try { fetch(window.ofnoacompsCRM.ajaxUrl, { method: 'POST', body: buildFormData(action, data) }); }
        catch (e) { /* silent */ }
    }

    function sendBeacon(action, data) {
        if (!window.ofnoacompsCRM || !window.ofnoacompsCRM.ajaxUrl) return;
        if (navigator.sendBeacon) {
            try { navigator.sendBeacon(window.ofnoacompsCRM.ajaxUrl, buildFormData(action, data)); return; }
            catch (e) { /* fall through */ }
        }
        sendAjax(action, data);
    }

    // ── Base tracker data ──────────────────────────────────────────────────

    function getBase() {
        var t = getCookie(COOKIE_NAME) || {};
        return {
            session_id:  getSessionId(),
            source:      t.source   || 'direct',
            medium:      t.medium   || '',
            campaign:    t.campaign || '',
            device_type: detectDevice(),
            page_url:    window.location.href,
        };
    }

    // ── Init cookie ────────────────────────────────────────────────────────

    function init() {
        var utmSource   = getParam('utm_source');
        var utmMedium   = getParam('utm_medium');
        var utmCampaign = getParam('utm_campaign');
        var utmTerm     = getParam('utm_term');
        var utmContent  = getParam('utm_content');
        var referrer    = document.referrer || '';
        var currentPage = window.location.href;
        var existing    = getCookie(COOKIE_NAME);
        var hasNewUtm   = !!utmSource;

        if (existing && !hasNewUtm) {
            existing.page_url = currentPage;
            setCookie(COOKIE_NAME, existing, COOKIE_DAYS);
        } else {
            var source = detectSource(utmSource, utmMedium, referrer);
            var data = {
                source: source, medium: utmMedium || '', campaign: utmCampaign || '',
                utm_term: utmTerm || '', utm_content: utmContent || '',
                referrer: referrer, landing_page: currentPage,
                page_url: currentPage, device_type: detectDevice(), ts: Date.now(),
            };
            setCookie(COOKIE_NAME, data, COOKIE_DAYS);
            if (window.dataLayer) {
                window.dataLayer.push({ event: 'crm_session_start', crm_source: data.source, crm_medium: data.medium, crm_campaign: data.campaign });
            }
        }
    }

    // ── Pageview ───────────────────────────────────────────────────────────

    function trackPageview() {
        var tracker = getCookie(COOKIE_NAME) || {};
        sendAjax('ofnoacomps_track_pageview', {
            session_id:   getSessionId(),
            page_url:     window.location.href,
            page_title:   document.title || '',
            referrer:     document.referrer || '',
            source:       tracker.source       || 'direct',
            medium:       tracker.medium       || '',
            campaign:     tracker.campaign     || '',
            utm_term:     tracker.utm_term     || '',
            utm_content:  tracker.utm_content  || '',
            landing_page: tracker.landing_page || window.location.href,
            device_type:  detectDevice(),
        });
    }

    // ── Click tracking ─────────────────────────────────────────────────────

    function trackClicks() {
        document.addEventListener('click', function (e) {
            var el = e.target;
            for (var depth = 0; depth < 6; depth++) {
                if (!el || el === document.body) break;
                var tag  = (el.tagName || '').toUpperCase();
                var href = el.href || el.getAttribute('href') || '';
                var isButton   = tag === 'BUTTON' || (tag === 'INPUT' && /^(submit|button)$/i.test(el.type || '')) || el.getAttribute('role') === 'button';
                var isWhatsApp = /wa\.me|whatsapp\.com|api\.whatsapp/.test(href);
                var isTel      = href.indexOf('tel:') === 0;
                var isEmail    = href.indexOf('mailto:') === 0;

                if (isButton || isWhatsApp || isTel || isEmail) {
                    var eventType = isWhatsApp ? 'whatsapp_click' : isTel ? 'phone_click' : isEmail ? 'email_click' : 'button_click';
                    var label = (el.innerText || el.textContent || el.getAttribute('value') || el.getAttribute('aria-label') || el.getAttribute('title') || '')
                        .replace(/\s+/g, ' ').trim().substring(0, 200);
                    sendAjax('ofnoacomps_track_event', Object.assign({}, getBase(), {
                        event_type: eventType, event_label: label, event_value: href.substring(0, 400),
                    }));
                    break;
                }
                el = el.parentElement;
            }
        }, true);
    }

    // ── WooCommerce tracking ───────────────────────────────────────────────

    function isPage(selectors, pathFragment) {
        if (pathFragment && window.location.href.indexOf(pathFragment) !== -1) return true;
        for (var i = 0; i < selectors.length; i++) {
            if (document.querySelector(selectors[i])) return true;
        }
        return false;
    }

    function getProductName(el) {
        var productEl = el.closest ? el.closest('.product') : null;
        if (productEl) {
            var nameEl = productEl.querySelector('.woocommerce-loop-product__title, .product_title, h1, h2');
            if (nameEl) return nameEl.textContent.replace(/\s+/g, ' ').trim().substring(0, 200);
        }
        return el.getAttribute('data-product_id') || el.getAttribute('aria-label') || 'מוצר';
    }

    function setCartFlag(count) {
        try { sessionStorage.setItem('ofc_cart_has_items', '1'); sessionStorage.setItem('ofc_cart_count', String(count || 1)); } catch (e) {}
    }
    function clearCartFlag() {
        try { sessionStorage.removeItem('ofc_cart_has_items'); sessionStorage.removeItem('ofc_cart_count'); } catch (e) {}
    }
    function getCartCount() {
        try { return parseInt(sessionStorage.getItem('ofc_cart_count') || '1', 10); } catch (e) { return 1; }
    }
    function hasCartItems() {
        try { return !!sessionStorage.getItem('ofc_cart_has_items'); } catch (e) { return false; }
    }

    function trackWooCommerce() {
        var base = getBase();

        // 1. Add to cart — click
        document.addEventListener('click', function (e) {
            var el = e.target;
            for (var i = 0; i < 6; i++) {
                if (!el || el === document.body) break;
                var isAddBtn = (el.classList && (el.classList.contains('add_to_cart_button') || el.classList.contains('single_add_to_cart_button'))) || el.name === 'add-to-cart';
                if (isAddBtn) {
                    sendAjax('ofnoacomps_track_event', Object.assign({}, base, {
                        event_type: 'add_to_cart', event_label: getProductName(el),
                        event_value: el.getAttribute('data-product_id') || '',
                    }));
                    setCartFlag(getCartCount() + 1);
                    break;
                }
                el = el.parentElement;
            }
        }, true);

        // 2. Add to cart — WooCommerce AJAX confirmation
        if (window.jQuery) {
            window.jQuery(document.body).on('added_to_cart', function () { setCartFlag(getCartCount()); });
            window.jQuery(document.body).on('removed_from_cart', function (e, fragments, hash, $btn) {
                var productId = $btn ? ($btn.data('product_id') || '') : '';
                sendAjax('ofnoacomps_track_event', Object.assign({}, base, {
                    event_type: 'remove_from_cart', event_label: String(productId), event_value: String(productId),
                }));
            });
        }

        // 3. Remove from cart — click (non-AJAX)
        document.addEventListener('click', function (e) {
            var el = e.target;
            for (var i = 0; i < 4; i++) {
                if (!el || el === document.body) break;
                if (el.classList && el.classList.contains('remove') && el.closest && el.closest('.cart_item')) {
                    sendAjax('ofnoacomps_track_event', Object.assign({}, base, {
                        event_type: 'remove_from_cart',
                        event_label: el.getAttribute('data-product_id') || 'מוצר',
                        event_value: el.getAttribute('data-product_id') || '',
                    }));
                    break;
                }
                el = el.parentElement;
            }
        }, true);

        // 4. View cart page
        if (isPage(['.woocommerce-cart-form', 'body.woocommerce-cart'], '/cart')) {
            var cartItems = document.querySelectorAll('.cart_item');
            var itemCount = cartItems.length;
            sendAjax('ofnoacomps_track_event', Object.assign({}, base, {
                event_type: 'view_cart', event_label: itemCount + ' פריטים בעגלה', event_value: String(itemCount),
            }));
            if (itemCount > 0) setCartFlag(itemCount);
        }

        // 5. Checkout start
        if (isPage(['.woocommerce-checkout', 'body.woocommerce-checkout'], '/checkout')) {
            sendAjax('ofnoacomps_track_event', Object.assign({}, base, {
                event_type: 'checkout_start', event_label: 'עמוד תשלום', event_value: '',
            }));
        }

        // 6. Order complete
        var isOrderDone = isPage(['.woocommerce-order-received', 'body.woocommerce-order-received'], 'order-received');
        if (isOrderDone) {
            sendAjax('ofnoacomps_track_event', Object.assign({}, base, {
                event_type: 'checkout_complete', event_label: 'הזמנה הושלמה', event_value: '',
            }));
            clearCartFlag();
        }

        // 7. Cart abandonment — fires when leaving without completing checkout
        if (!isOrderDone) {
            window.addEventListener('pagehide', function () {
                if (!hasCartItems()) return;
                if (isPage(['.woocommerce-checkout'], '/checkout')) return; // on checkout, not abandonment yet
                sendBeacon('ofnoacomps_track_event', Object.assign({}, base, {
                    event_type: 'cart_abandonment',
                    event_label: getCartCount() + ' פריטים נטושים',
                    event_value: String(getCartCount()),
                }));
            });
        }
    }

    // ── Bootstrap ──────────────────────────────────────────────────────────

    function bootstrap() {
        init();
        trackPageview();
        trackClicks();

        // WooCommerce: activate only when WC signals found on page
        if (
            typeof wc_add_to_cart_params !== 'undefined' ||
            typeof woocommerce_params !== 'undefined' ||
            document.querySelector('.woocommerce') ||
            document.querySelector('[class*="woocommerce"]')
        ) {
            trackWooCommerce();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    // ── Public API ─────────────────────────────────────────────────────────

    window.OfnoacompsCRM = {
        getTrackerData: function () { return getCookie(COOKIE_NAME) || {}; },
        trackEvent: function (eventType, eventLabel, eventValue) {
            sendAjax('ofnoacomps_track_event', Object.assign({}, getBase(), {
                event_type: eventType, event_label: eventLabel || '', event_value: eventValue || '',
            }));
        },
        submitLead: function (formData) {
            if (!window.ofnoacompsCRM) return;
            var tracker = this.getTrackerData();
            var payload = Object.assign({}, formData, tracker);
            payload.nonce = window.ofnoacompsCRM.nonce;
            var fd = new FormData();
            Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });
            fd.append('action', 'ofnoacomps_crm_submit_lead');
            fetch(window.ofnoacompsCRM.ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) { console.log('[OfnoacompsCRM] Lead submitted', res); })
                .catch(function (err) { console.error('[OfnoacompsCRM] Error', err); });
        }
    };

})();
