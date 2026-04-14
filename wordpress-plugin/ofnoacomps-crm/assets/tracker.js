/**
 * Ofnoacomps CRM — Frontend Tracker v2
 * - Captures UTM params, referrer, device info → cookie (read at form submit)
 * - Records every pageview to the server via AJAX
 * - Tracks button clicks, WhatsApp snippet links, tel: links
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

    // ── Session ID (per browser tab / session) ─────────────────────────────

    function getSessionId() {
        var key = 'ofnoacomps_sid';
        try {
            var sid = sessionStorage.getItem(key);
            if (!sid) {
                sid = 'sid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem(key, sid);
            }
            return sid;
        } catch (e) {
            return 'sid_' + Date.now();
        }
    }

    // ── AJAX sender ────────────────────────────────────────────────────────

    function sendAjax(action, data) {
        if (!window.ofnoacompsCRM || !window.ofnoacompsCRM.ajaxUrl) return;
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', window.ofnoacompsCRM.nonce || '');
        for (var k in data) {
            if (Object.prototype.hasOwnProperty.call(data, k)) {
                fd.append(k, data[k] !== undefined && data[k] !== null ? String(data[k]) : '');
            }
        }
        try {
            fetch(window.ofnoacompsCRM.ajaxUrl, { method: 'POST', body: fd });
        } catch (e) { /* silent fail */ }
    }

    // ── Init: populate/refresh UTM cookie ─────────────────────────────────

    function init() {
        var utmSource   = getParam('utm_source');
        var utmMedium   = getParam('utm_medium');
        var utmCampaign = getParam('utm_campaign');
        var utmTerm     = getParam('utm_term');
        var utmContent  = getParam('utm_content');
        var referrer    = document.referrer || '';
        var currentPage = window.location.href;

        var existing = getCookie(COOKIE_NAME);
        var hasNewUtm = !!utmSource;

        if (existing && !hasNewUtm) {
            existing.page_url = currentPage;
            setCookie(COOKIE_NAME, existing, COOKIE_DAYS);
        } else {
            var source = detectSource(utmSource, utmMedium, referrer);
            var data = {
                source:       source,
                medium:       utmMedium || '',
                campaign:     utmCampaign || '',
                utm_term:     utmTerm || '',
                utm_content:  utmContent || '',
                referrer:     referrer,
                landing_page: currentPage,
                page_url:     currentPage,
                device_type:  detectDevice(),
                ts:           Date.now(),
            };
            setCookie(COOKIE_NAME, data, COOKIE_DAYS);
            if (window.dataLayer) {
                window.dataLayer.push({
                    event: 'crm_session_start',
                    crm_source: data.source,
                    crm_medium: data.medium,
                    crm_campaign: data.campaign,
                });
            }
        }
    }

    // ── Pageview tracking ──────────────────────────────────────────────────

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

    // ── Click / button event tracking ─────────────────────────────────────

    function trackClicks() {
        document.addEventListener('click', function (e) {
            var el = e.target;
            // Walk up the DOM (max 6 levels) to find the actionable element
            for (var depth = 0; depth < 6; depth++) {
                if (!el || el === document.body) break;
                var tag  = (el.tagName || '').toUpperCase();
                var href = el.href || el.getAttribute('href') || '';

                var isButton    = tag === 'BUTTON' ||
                                  (tag === 'INPUT' && /^(submit|button)$/i.test(el.type || '')) ||
                                  el.getAttribute('role') === 'button';
                var isWhatsApp  = /wa\.me|whatsapp\.com|api\.whatsapp/.test(href);
                var isTel       = href.indexOf('tel:') === 0;
                var isEmail     = href.indexOf('mailto:') === 0;

                if (isButton || isWhatsApp || isTel || isEmail) {
                    var eventType = isWhatsApp ? 'whatsapp_click'
                                  : isTel      ? 'phone_click'
                                  : isEmail    ? 'email_click'
                                               : 'button_click';

                    var label = (
                        el.innerText        ||
                        el.textContent      ||
                        el.getAttribute('value') ||
                        el.getAttribute('aria-label') ||
                        el.getAttribute('title') ||
                        ''
                    ).replace(/\s+/g, ' ').trim().substring(0, 200);

                    var tracker = getCookie(COOKIE_NAME) || {};
                    sendAjax('ofnoacomps_track_event', {
                        session_id:  getSessionId(),
                        event_type:  eventType,
                        event_label: label,
                        event_value: href.substring(0, 400),
                        page_url:    window.location.href,
                        source:      tracker.source   || 'direct',
                        medium:      tracker.medium   || '',
                        campaign:    tracker.campaign || '',
                        device_type: detectDevice(),
                    });
                    break;
                }
                el = el.parentElement;
            }
        }, true); // capture phase catches WhatsApp snippets that stopPropagation
    }

    // ── Bootstrap ──────────────────────────────────────────────────────────

    function bootstrap() {
        init();
        trackPageview();
        trackClicks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    // ── Public API ─────────────────────────────────────────────────────────

    window.OfnoacompsCRM = {
        getTrackerData: function () {
            return getCookie(COOKIE_NAME) || {};
        },
        submitLead: function (formData) {
            if (!window.ofnoacompsCRM) return;
            var tracker = this.getTrackerData();
            var payload = Object.assign({}, formData, tracker);
            payload.nonce = window.ofnoacompsCRM.nonce;
            var fd = new FormData();
            fd.append('action', 'ofnoacomps_crm_submit_lead');
            Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });
            fetch(window.ofnoacompsCRM.ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) { console.log('[OfnoacompsCRM] Lead submitted', res); })
                .catch(function (err) { console.error('[OfnoacompsCRM] Error', err); });
        }
    };

})();