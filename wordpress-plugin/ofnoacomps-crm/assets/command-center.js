/* Ofnoacomps CRM — Command Center charts */
(function () {
    'use strict';
    if (typeof Chart === 'undefined' || !window.ccData) return;

    var d   = window.ccData;
    var css = getComputedStyle(document.documentElement);
    function v(name) { return css.getPropertyValue(name).trim(); }

    var ink = v('--ink-2'), muted = v('--muted'), grid = v('--grid'), axis = v('--axis'), surface = v('--surface');
    var s1 = v('--series-1'), s2 = v('--series-2');

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size   = 12;
    Chart.defaults.color       = muted;

    var tooltip = {
        rtl: true, textDirection: 'rtl',
        backgroundColor: v('--ink'), titleColor: surface, bodyColor: surface,
        padding: 10, cornerRadius: 8, displayColors: true, boxPadding: 4
    };

    function nf(n) { return Number(n).toLocaleString('he-IL'); }

    function timeScales() {
        return {
            x: {
                reverse: true, // RTL: newest day on the left edge reads naturally from the right
                grid: { display: false },
                border: { color: axis },
                ticks: { color: muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }
            },
            y: {
                position: 'right',
                beginAtZero: true,
                grid: { color: grid },
                border: { display: false },
                ticks: { color: muted, precision: 0, callback: nf }
            }
        };
    }

    function barDay(id, datasets) {
        var el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'bar',
            data: { labels: d.labels, datasets: datasets },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: datasets.length > 1, position: 'top', align: 'start', rtl: true,
                              labels: { color: ink, boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rectRounded' } },
                    tooltip: tooltip
                },
                scales: (function () { var s = timeScales(); if (datasets.length > 1) { s.x.stacked = true; s.y.stacked = true; } return s; })()
            }
        });
    }

    function hbar(id, rows, color) {
        var el = document.getElementById(id);
        if (!el || !rows || !rows.length) return;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: rows.map(function (r) { return r.label; }),
                datasets: [{ data: rows.map(function (r) { return r.total; }), backgroundColor: color,
                             borderRadius: 4, borderSkipped: 'start', barThickness: 14 }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltip },
                scales: {
                    x: { reverse: true, beginAtZero: true, grid: { color: grid }, border: { display: false },
                         ticks: { color: muted, precision: 0, callback: nf, maxTicksLimit: 5 } },
                    y: { position: 'right', grid: { display: false }, border: { color: axis }, ticks: { color: ink } }
                }
            }
        });
    }

    var bar = { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22, categoryPercentage: 0.8, barPercentage: 0.9 };

    // Leads per day
    barDay('cc-leads-day', [Object.assign({ label: 'לידים', data: d.leads, backgroundColor: s1 }, bar)]);

    // Leads by source
    hbar('cc-leads-source', d.sources, s1);

    // Meta — posts per day, stacked by channel (2px surface gap between segments)
    barDay('cc-meta-day', [
        Object.assign({ label: 'פייסבוק', data: d.meta_fb, backgroundColor: s1, borderColor: surface, borderWidth: { top: 2 } }, bar),
        Object.assign({ label: 'אינסטגרם', data: d.meta_ig, backgroundColor: s2, borderColor: surface, borderWidth: { top: 2 } }, bar)
    ]);

    // SEO — GSC clicks per day
    var clicksEl = document.getElementById('cc-seo-clicks');
    if (clicksEl) {
        new Chart(clicksEl, {
            type: 'line',
            data: { labels: d.labels, datasets: [{
                label: 'קליקים', data: d.clicks, borderColor: s1, borderWidth: 2,
                backgroundColor: 'transparent', tension: 0.25,
                pointRadius: 0, pointHoverRadius: 5, pointHoverBorderWidth: 2, pointHoverBorderColor: surface, pointHoverBackgroundColor: s1
            }] },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: tooltip },
                scales: timeScales()
            }
        });
    }

    // SEO — traffic by channel
    hbar('cc-seo-channels', d.channels, s1);
})();
