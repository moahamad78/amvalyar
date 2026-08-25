<style>
.role-dashboard-v2 { margin-bottom: 20px; }
.role-dashboard-v2-main {
    background: linear-gradient(135deg, #111827, #1f2937);
    color: #fff;
    border-radius: 18px;
    padding: 22px;
    margin-bottom: 14px;
}
.role-dashboard-v2-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}
.role-dashboard-v2-role { color: #cbd5e1; font-size: 13px; margin-bottom: 8px; }
.role-dashboard-v2-title { font-size: 21px; font-weight: 800; margin: 0 0 7px; }
.role-dashboard-v2-description { color: #e5e7eb; line-height: 1.9; font-size: 13px; max-width: 760px; }
.role-dashboard-v2-count {
    min-width: 145px;
    text-align: center;
    background: rgba(255,255,255,.10);
    border: 1px solid rgba(255,255,255,.15);
    padding: 13px 16px;
    border-radius: 14px;
}
.role-dashboard-v2-count strong { display: block; font-size: 29px; }
.role-dashboard-v2-count span { color: #cbd5e1; font-size: 12px; }
.role-dashboard-v2-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.role-dashboard-v2-action {
    display: block;
    text-decoration: none;
    background: #fff;
    color: #111827;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px;
}
.role-dashboard-v2-action:hover { border-color: #93c5fd; }
.role-dashboard-v2-action strong { display: block; margin-bottom: 4px; }
.role-dashboard-v2-action span { display: block; color: #64748b; font-size: 12px; }
.role-dashboard-v2-tasks {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 16px;
}
.role-dashboard-v2-task {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    border-top: 1px solid #f1f5f9;
    padding: 10px 0;
}
.role-dashboard-v2-task:first-of-type { border-top: 0; }
@media (max-width: 700px) {
    .role-dashboard-v2-count { width: 100%; }
    .role-dashboard-v2-task { align-items: flex-start; flex-direction: column; }
}

.role-dashboard-v2-alerts {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px 15px;
    margin-bottom: 14px;
}

.role-dashboard-v2-alerts-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.role-dashboard-v2-alerts-summary {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    font-size: 12px;
}

.role-dashboard-v2-alert-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 9px 0;
    border-top: 1px solid #f1f5f9;
}

.role-dashboard-v2-alert-title {
    font-size: 13px;
    font-weight: 800;
}

.role-dashboard-v2-alert-source {
    font-size: 11px;
    color: #64748b;
    margin-top: 3px;
}

.role-dashboard-v2-alert-critical {
    color: #b91c1c;
}

.role-dashboard-v2-alert-warning {
    color: #92400e;
}

@media (max-width: 700px) {
    .role-dashboard-v2-alert-row {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="role-dashboard-v2">
    <div class="role-dashboard-v2-main">
        <div class="role-dashboard-v2-head">
            <div>
                <div class="role-dashboard-v2-role">
                    نقش: {{ $roleDashboard['role_label'] }}
                </div>

                <h2 class="role-dashboard-v2-title">
                    {{ $roleDashboard['headline'] }}
                </h2>

                <div class="role-dashboard-v2-description">
                    {{ $roleDashboard['description'] }}
                </div>
            </div>

            <a
                href="{{ route('task-center.index') }}"
                class="role-dashboard-v2-count text-white text-decoration-none"
            >
                <strong>{{ $roleDashboard['task_count'] }}</strong>
                <span>کار منتظر اقدام</span>

                @if($roleDashboard['overdue_count'] > 0)
                    <div class="small text-warning mt-1">
                        {{ $roleDashboard['overdue_count'] }} مورد معوق
                    </div>
                @endif
            </a>
        </div>
    </div>

    @if(count($roleDashboard['actions']) > 0)
        <div class="role-dashboard-v2-actions">
            @foreach($roleDashboard['actions'] as $action)
                <a
                    href="{{ route($action['route']) }}"
                    class="role-dashboard-v2-action"
                >
                    <strong>{{ $action['label'] }}</strong>
                    <span>{{ $action['description'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if(($roleDashboard['alert_summary']['total'] ?? 0) > 0)

        <div class="role-dashboard-v2-alerts">

            <div class="role-dashboard-v2-alerts-head">

                <div>
                    <strong>
                        هشدارهای عملیاتی
                    </strong>

                    <div class="role-dashboard-v2-alerts-summary mt-1">

                        @if(($roleDashboard['alert_summary']['critical'] ?? 0) > 0)
                            <span class="text-danger">
                                بحرانی:
                                {{ $roleDashboard['alert_summary']['critical'] }}
                            </span>
                        @endif

                        @if(($roleDashboard['alert_summary']['warning'] ?? 0) > 0)
                            <span class="text-warning">
                                پیگیری:
                                {{ $roleDashboard['alert_summary']['warning'] }}
                            </span>
                        @endif

                    </div>
                </div>

                <a
                    href="{{ route('operational-alerts.index') }}"
                    class="btn btn-sm btn-outline-danger"
                >
                    مشاهده همه
                </a>

            </div>

            @foreach($roleDashboard['alert_preview'] as $alert)

                <div class="role-dashboard-v2-alert-row">

                    <div>
                        <div
                            class="role-dashboard-v2-alert-title {{
                                $alert['severity'] === 'critical'
                                    ? 'role-dashboard-v2-alert-critical'
                                    : 'role-dashboard-v2-alert-warning'
                            }}"
                        >
                            {{ $alert['title'] }}
                        </div>

                        <div class="role-dashboard-v2-alert-source">
                            {{ $alert['source_label'] }}
                        </div>
                    </div>

                    @if(
                        $alert['action_route']
                        &&
                        \Illuminate\Support\Facades\Route::has(
                            $alert['action_route']
                        )
                    )
                        <a
                            href="{{
                                $alert['action_parameter'] !== null
                                    ? route(
                                        $alert['action_route'],
                                        $alert['action_parameter']
                                    )
                                    : route(
                                        $alert['action_route']
                                    )
                            }}"
                            class="btn btn-sm btn-outline-secondary"
                        >
                            بررسی
                        </a>
                    @endif

                </div>

            @endforeach

        </div>

    @endif

    @if($roleDashboard['task_count'] > 0)
        <div class="role-dashboard-v2-tasks">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>اولویت‌های کاری من</strong>

                <a
                    href="{{ route('task-center.index') }}"
                    class="btn btn-sm btn-outline-primary"
                >
                    مشاهده همه
                </a>
            </div>

            @foreach($roleDashboard['task_preview'] as $task)
                <div class="role-dashboard-v2-task">
                    <div>
                        <strong>{{ $task['title'] }}</strong>

                        <div class="small text-muted mt-1">
                            {{ $task['process_label'] }}

                            @if($task['subject_id'])
                                — درخواست #{{ $task['subject_id'] }}
                            @endif
                        </div>
                    </div>

                    <a
                        href="{{ route($task['route'], $task['route_parameter']) }}"
                        class="btn btn-sm btn-primary"
                    >
                        اقدام
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>