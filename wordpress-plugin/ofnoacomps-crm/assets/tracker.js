/**
 * Ofnoacomps CRM — Frontend UTM & Traffic Source Tracker
 * Captures UTM params, referrer, device info and stores in cookie.
 * Cookie is read server-side when a lead form is submitted.
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'ofnoacomps_crm_tracker';
    var COOKIE_DAYS = 30;

    function getParam(name) {
        var url = new URL(window.location.href);
        return url.searchParams.get(name) || '';
    }

    function detectSource(utmSource, utmMedium, referrer) {
        if (utmSource) {
            // UTM source takes priority
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
        } catch (e) {
            return 'direct';
        }
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

    function init() {
        var utmSource   = getParam('utm_source');
        var utmMedium   = getParam('utm_medium');
        var utmCampaign = getParam('utm_campaign');
        var utmTerm     = getParam('utm_term');
        var utmContent  = getParam('utm_content');
        var referrer    = document.referrer || '';
        var currentPage = window.location.href;

        // If UTM params found — always refresh tracker (paid campaigns override)
        var existing = getCookie(COOKIE_NAME);
        var hasNewUtm = !!utmSource;

        if (existing && !hasNewUtm) {
            // Update current page but keep original landing/source
            existing.page_url = currentPage;
            setCookie(COOKIE_NAME, existing, COOKIE_DAYS);
            return;
        }

        var source = detectSource(utmSource, utmMedium, referrer);

        var data = {
            source:      source,
            medium:      utmMedium || '',
            campaign:    utmCampaign || '',
            utm_term:    utmTerm || '',
            utm_content: utmContent || '',
            referrer:    referrer,
            landing_page: currentPage,
            page_url:    currentPage,
            device_type: detectDevice(),
            ts:          Date.now(),
        };

        setCookie(COOKIE_NAME, data, COOKIE_DAYS);

        // Push to dataLayer for GA4 if available
        if (window.dataLayer) {
            window.dataLayer.push({
                event: 'crm_session_start',
                crm_source: data.source,
                crm_medium: data.medium,
                crm_campaign: data.campaign,
            });
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ── Expose helper for custom form integrations ──────────────────────────
    window.OfnoacompsCRM = {
        getTrackerData: function () {
            return getCookie(COOKIE_NAME) || {};
        },
        /**
         * Manually submit a lead from a custom form.
         * Usage: OfnoacompsCRM.submitLead({ first_name, last_name, email, phone, message })
         */
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

    // ── AJAX lead submit handler (WordPress) ───────────────────────────────
    // This is called when third-party forms use OfnoacompsCRM.submitLead()
    // The server-side handler is registered in class-rest-api.php via wp_ajax_*

})();
