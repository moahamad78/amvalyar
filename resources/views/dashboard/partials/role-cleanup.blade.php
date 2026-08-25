<style>
/*
|--------------------------------------------------------------------------
| Dashboard V3 compact layout
|--------------------------------------------------------------------------
| This layer changes presentation only. Existing dashboard data,
| permissions, Task Center and workflow logic remain untouched.
*/

.dash-wrap .role-dashboard-v2 {
    margin-bottom: 14px;
}

.dash-wrap .role-dashboard-v2-main {
    padding: 18px 20px;
    margin-bottom: 10px;
    border-radius: 16px;
}

.dash-wrap .role-dashboard-v2-title {
    font-size: 20px;
}

.dash-wrap .role-dashboard-v2-description {
    line-height: 1.75;
}

.dash-wrap .role-dashboard-v2-count {
    min-width: 130px;
    padding: 10px 14px;
}

.dash-wrap .role-dashboard-v2-count strong {
    font-size: 26px;
}

.dash-wrap .role-dashboard-v2-actions {
    grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
    gap: 8px;
    margin-bottom: 10px;
}

.dash-wrap .role-dashboard-v2-action {
    padding: 11px 13px;
    border-radius: 12px;
}

.dash-wrap .role-dashboard-v2-tasks {
    padding: 13px 15px;
    border-radius: 14px;
    margin-bottom: 14px;
}

.dash-wrap .role-dashboard-v2-task {
    padding: 8px 0;
}

.dash-wrap .dash-header {
    padding: 18px 20px;
    margin-bottom: 14px;
}

.dash-wrap .dash-header h1 {
    font-size: 24px;
}

.dash-wrap .company-meta {
    margin-top: 12px;
    gap: 7px;
}

.dash-wrap .company-meta span {
    padding: 5px 10px;
    font-size: 12px;
}

.dash-wrap .stat-grid {
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

.dash-wrap .dash-card {
    padding: 13px 14px;
    min-height: 90px;
}

.dash-wrap .dash-card .label {
    margin-bottom: 5px;
    font-size: 12px;
}

.dash-wrap .dash-card .value {
    font-size: 23px;
}

.dash-wrap .dash-card .sub {
    margin-top: 3px;
}

.dash-wrap .dash-section {
    padding: 15px 16px;
    margin-bottom: 14px;
}

.dash-wrap .dash-section-title {
    margin-bottom: 12px;
}

.dash-wrap .dash-section-title h2 {
    font-size: 18px;
}

.dash-wrap .capacity-grid {
    gap: 12px;
}

.dash-wrap .capacity-box {
    padding: 12px 14px;
}

.dash-wrap .capacity-head {
    margin-bottom: 7px;
}

.dash-wrap .progress {
    height: 9px;
}

.dash-wrap .dashboard-columns {
    gap: 14px;
}

.dash-wrap .category-row {
    margin-bottom: 11px;
}

.dash-wrap table {
    margin-bottom: 0;
}

.dash-wrap .table > :not(caption) > * > * {
    padding-top: .48rem;
    padding-bottom: .48rem;
}

.dash-wrap .btn-sm {
    padding-top: .26rem;
    padding-bottom: .26rem;
}

@media (max-width: 900px) {
    .dash-wrap .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .dash-wrap .stat-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /*
     * Keep the operational dashboard short.
     * Only the visual preview is limited; the "view all" pages
     * and server-side data remain unchanged.
     */
    document.querySelectorAll('.dash-section').forEach(function (section) {
        const heading = section.querySelector('h2');

        if (!heading) {
            return;
        }

        const title = (heading.textContent || '').trim();

        if (!title.includes('آخرین گردش')) {
            return;
        }

        const rows = section.querySelectorAll('tbody tr');

        rows.forEach(function (row, index) {
            if (index >= 5) {
                row.style.display = 'none';
            }
        });
    });
});
</script>