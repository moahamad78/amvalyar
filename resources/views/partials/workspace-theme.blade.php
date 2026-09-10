@php
    $personalTheme = auth()->check() ? (\Illuminate\Support\Facades\DB::table('workspace_preferences')->where('user_id',auth()->id())->value('theme') ?? 'company') : 'company';
    $palette = ['ocean'=>['#0c4a6e','#075985','#0284c7','#f0f9ff'],'forest'=>['#14532d','#166534','#15803d','#f0fdf4'],'violet'=>['#4c1d95','#5b21b6','#7c3aed','#f5f3ff'],'dark'=>['#111827','#1f2937','#60a5fa','#111827']][$personalTheme] ?? null;
@endphp
@if($palette)
<style>:root { --tenant-primary: {{ $palette[0] }}; --tenant-secondary: {{ $palette[1] }}; --tenant-accent: {{ $palette[2] }}; --tenant-surface: {{ $palette[3] }}; }</style>
@endif
@if($personalTheme==='dark')
<script>document.documentElement.setAttribute('data-bs-theme','dark');</script>
<style>
body { color:#e5e7eb; } .app-sidebar { background:#1f2937;border-color:#374151;color:#e5e7eb; }
.app-sidebar-group-button {color:inherit} .app-sidebar-group-button:hover,.app-sidebar-link:hover {background:#374151;}
.app-sidebar-link.active {background:#374151;color:#fff} .app-sidebar-header {border-color:#374151}
.card,.table {--bs-body-bg:#1f2937;--bs-table-bg:#1f2937;--bs-table-color:#e5e7eb;background-color:#1f2937;color:#e5e7eb}
.bg-white,.table-light {background-color:#1f2937!important;color:#e5e7eb!important;--bs-table-bg:#1f2937;--bs-table-color:#e5e7eb}
[data-bs-theme="dark"] :is(.dash-header,.dash-card,.dash-section,.sa-header,.stat-card,.section-card,.role-dashboard-v2-action,.role-dashboard-v2-tasks,.role-dashboard-v2-alerts) {background:#1f2937;color:#e5e7eb;border-color:#374151}
[data-bs-theme="dark"] :is(.capacity-box,.company-meta span,.status-badge,.app-sidebar-badge,.sidebar-mobile-toggle) {background:#374151;color:#f3f4f6;border-color:#4b5563}
[data-bs-theme="dark"] :is(.dash-muted,.sa-muted,.dash-card .label,.stat-card .title,.role-dashboard-v2-action span,.role-dashboard-v2-alert-source) {color:#b6c2d2}
[data-bs-theme="dark"] .app-sidebar-close {color:#f3f4f6}
[data-bs-theme="dark"] .jdp-container {color:#111827;color-scheme:light}
[data-bs-theme="dark"] :is(.small-progress,.mini-progress) {background:#374151}
</style>
@endif
