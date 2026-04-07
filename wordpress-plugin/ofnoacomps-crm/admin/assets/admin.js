/* Ofnoacomps CRM Admin JS */
(function ($) {
    'use strict';

    var API = ofnoacompsCRMAdmin.apiBase;
    var HEADERS = { 'X-WP-Nonce': ofnoacompsCRMAdmin.nonce, 'Content-Type': 'application/json' };

    // ── API helper ───────────────────────────────────────────────────────────
    function api(method, path, data) {
        return fetch(API + path, {
            method: method,
            headers: HEADERS,
            body: data ? JSON.stringify(data) : undefined,
        }).then(function (r) { return r.json(); });
    }

    // ── Dashboard charts ─────────────────────────────────────────────────────
    function initDashboardCharts() {
        if (!document.getElementById('chart-leads-time')) return;

        var from = document.getElementById('filter-from')  ? document.getElementById('filter-from').value  : '';
        var to   = document.getElementById('filter-to')    ? document.getElementById('filter-to').value    : '';

        // Leads over time
        api('GET', '/reports/leads-over-time?from=' + from + '&to=' + to).then(function (res) {
            if (!res.data) return;
            var labels = res.data.map(function (r) { return r.period; });
            var values = res.data.map(function (r) { return r.count; });
            new Chart(document.getElementById('chart-leads-time'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'לידים', data: values,
                        borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)',
                        tension: 0.3, fill: true,
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });
        });

        // Leads by source
        api('GET', '/reports/leads-by-source?from=' + from + '&to=' + to).then(function (res) {
            if (!res.data) return;
            var labels = res.data.map(function (r) { return r.label; });
            var values = res.data.map(function (r) { return r.count; });
            var colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'];
            new Chart(document.getElementById('chart-leads-source'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{ data: values, backgroundColor: colors }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        });

        // Pipeline funnel
        api('GET', '/reports/pipeline-funnel').then(function (res) {
            if (!res.data) return;
            var labels = res.data.map(function (r) { return r.name; });
            var values = res.data.map(function (r) { return r.deal_count; });
            var colors = res.data.map(function (r) { return r.color || '#3b82f6'; });
            if (!document.getElementById('chart-funnel')) return;
            new Chart(document.getElementById('chart-funnel'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ label: 'עסקאות', data: values, backgroundColor: colors }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } }
                }
            });
        });
    }

    // ── Report charts ────────────────────────────────────────────────────────
    function initReportCharts() {
        if (!document.getElementById('report-leads-time')) return;

        var from = document.getElementById('rpt-from') ? document.getElementById('rpt-from').value : '';
        var to   = document.getElementById('rpt-to')   ? document.getElementById('rpt-to').value   : '';

        api('GET', '/reports/leads-over-time?from=' + from + '&to=' + to).then(function (res) {
            if (!res.data || !document.getElementById('report-leads-time')) return;
            new Chart(document.getElementById('report-leads-time'), {
                type: 'bar',
                data: {
                    labels: res.data.map(function (r) { return r.period; }),
                    datasets: [{ label: 'לידים', data: res.data.map(function (r) { return r.count; }), backgroundColor: '#3b82f6' }]
                },
                options: { responsive: true }
            });
        });

        api('GET', '/reports/leads-by-source?from=' + from + '&to=' + to).then(function (res) {
            if (!res.data || !document.getElementById('report-source-pie')) return;
            var colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899'];
            new Chart(document.getElementById('report-source-pie'), {
                type: 'pie',
                data: {
                    labels: res.data.map(function (r) { return r.label; }),
                    datasets: [{ data: res.data.map(function (r) { return r.count; }), backgroundColor: colors }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        });

        api('GET', '/reports/revenue?from=' + from + '&to=' + to).then(function (res) {
            if (!res.data || !document.getElementById('report-revenue')) return;
            new Chart(document.getElementById('report-revenue'), {
                type: 'bar',
                data: {
                    labels: res.data.map(function (r) { return r.period; }),
                    datasets: [{ label: 'הכנסות', data: res.data.map(function (r) { return r.revenue; }), backgroundColor: '#10b981' }]
                },
                options: { responsive: true }
            });
        });
    }

    // ── Lead status quick-change ─────────────────────────────────────────────
    function initLeadStatusChange() {
        $(document).on('change', '.lead-status-select', function () {
            var $sel   = $(this);
            var leadId = $sel.data('id');
            var status = $sel.val();
            api('PATCH', '/leads/' + leadId, { status: status }).then(function () {
                $sel.closest('tr').find('.ocrm-badge').attr('class', 'ocrm-badge badge-' + status).text($sel.find(':selected').text());
            });
        });
    }

    // ── Deal modal ───────────────────────────────────────────────────────────
    function initDealModal() {
        $(document).on('click', '.add-deal-btn', function () {
            var stageId = $(this).data('stage');
            $('#deal-modal-stage').val(stageId);
            showModal('#deal-modal');
        });

        $(document).on('click', '.kanban-deal-card', function () {
            var id = $(this).data('id');
            api('GET', '/deals/' + id).then(function (res) {
                var d = res.data;
                if (!d) return;
                $('#deal-edit-id').val(d.id);
                $('#deal-edit-name').val(d.name);
                $('#deal-edit-amount').val(d.amount);
                $('#deal-edit-stage').val(d.stage_id);
                $('#deal-edit-close-date').val(d.close_date);
                $('#deal-edit-notes').val(d.notes);
                showModal('#deal-edit-modal');
            });
        });

        $('#deal-form').on('submit', function (e) {
            e.preventDefault();
            var data = {
                name:        $('#deal-name').val(),
                amount:      $('#deal-amount').val(),
                stage_id:    $('#deal-modal-stage').val(),
                close_date:  $('#deal-close-date').val(),
                customer_id: $('#deal-customer-id').val() || null,
                notes:       $('#deal-notes').val(),
            };
            api('POST', '/deals', data).then(function () { location.reload(); });
        });

        $('#deal-edit-form').on('submit', function (e) {
            e.preventDefault();
            var id = $('#deal-edit-id').val();
            var data = {
                name:       $('#deal-edit-name').val(),
                amount:     $('#deal-edit-amount').val(),
                stage_id:   $('#deal-edit-stage').val(),
                close_date: $('#deal-edit-close-date').val(),
                notes:      $('#deal-edit-notes').val(),
            };
            api('PATCH', '/deals/' + id, data).then(function () { location.reload(); });
        });

        $(document).on('click', '.deal-won-btn', function () {
            if (!confirm('לסמן כזכייה?')) return;
            api('PATCH', '/deals/' + $(this).data('id'), { status: 'won' }).then(function () { location.reload(); });
        });

        $(document).on('click', '.deal-lost-btn', function () {
            var reason = prompt('סיבת הפסד (אופציונלי):');
            api('PATCH', '/deals/' + $(this).data('id'), { status: 'lost', lost_reason: reason || '' }).then(function () { location.reload(); });
        });
    }

    // ── Activity form ────────────────────────────────────────────────────────
    function initActivityForm() {
        $(document).on('submit', '.ocrm-activity-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var data = {
                type:        $form.find('[name=activity_type]').val(),
                subject:     $form.find('[name=subject]').val(),
                body:        $form.find('[name=body]').val(),
                entity_type: $form.data('entity-type'),
                entity_id:   $form.data('entity-id'),
            };
            var endpoint = data.entity_type === 'customer'
                ? '/customers/' + data.entity_id + '/activities'
                : '/leads/' + data.entity_id + '/activities';

            api('POST', endpoint, data).then(function () { location.reload(); });
        });
    }

    // ── Lead convert btn ─────────────────────────────────────────────────────
    function initConvertLead() {
        $(document).on('click', '.convert-lead-btn', function () {
            if (!confirm('להמיר ליד ללקוח?')) return;
            var id = $(this).data('id');
            api('POST', '/leads/' + id + '/convert', {}).then(function (res) {
                if (res.data && res.data.customer_id) {
                    alert('הליד הומר ללקוח בהצלחה!');
                    location.reload();
                }
            });
        });
    }

    // ── Modal helpers ─────────────────────────────────────────────────────────
    function showModal(selector) {
        $(selector).show();
    }
    function hideModal(selector) {
        $(selector).hide();
    }
    $(document).on('click', '.ocrm-modal-close, .ocrm-modal-overlay', function (e) {
        if ($(e.target).hasClass('ocrm-modal-overlay') || $(e.target).hasClass('ocrm-modal-close')) {
            $(this).closest('.ocrm-modal-overlay').hide();
        }
    });
    $(document).on('click', '.ocrm-open-modal', function () {
        showModal('#' + $(this).data('modal'));
    });

    // ── Date filter submit ────────────────────────────────────────────────────
    $(document).on('change', '#filter-from, #filter-to, #rpt-from, #rpt-to', function () {
        $(this).closest('form').submit();
    });

    // ── Init ─────────────────────────────────────────────────────────────────
    $(document).ready(function () {
        initDashboardCharts();
        initReportCharts();
        initLeadStatusChange();
        initDealModal();
        initActivityForm();
        initConvertLead();
    });

})(jQuery);
