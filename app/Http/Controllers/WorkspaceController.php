<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class WorkspaceController extends Controller
{
    public function preferences(Request $request)
    {
        $theme = DB::table('workspace_preferences')->where('user_id', $request->user()->id)->value('theme') ?? 'company';

        return view('workspace.preferences', compact('theme'));
    }

    public function saveTheme(Request $request)
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['company', 'ocean', 'forest', 'violet', 'dark'])]]);
        DB::table('workspace_preferences')->updateOrInsert(['user_id' => $request->user()->id], ['theme' => $data['theme'], 'updated_at' => now()]);

        return back()->with('success', 'تم شخصی شما ذخیره شد.');
    }

    public function history(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasRole('company_admin'), 403);
        $data = $request->validate([
            'kind' => ['nullable', Rule::in(['logins', 'activity'])],
            'user_id' => ['nullable', 'integer'], 'ip' => ['nullable', 'ip'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $users = User::query()->orderBy('name')->get();
        $kind = $data['kind'] ?? 'logins';
        if ($kind === 'logins') {
            $query = DB::table('login_sessions')->join('users', 'users.id', '=', 'login_sessions.user_id')
                ->select('login_sessions.id', 'login_sessions.user_id', 'users.name', 'users.username', 'login_sessions.ip_address', 'login_sessions.user_agent', 'login_sessions.computer_name', 'login_sessions.started_at as occurred_at', 'login_sessions.revoked_at', 'login_sessions.last_activity_at');
            if (! $request->user()->isSuperAdmin()) {
                $query->where('users.company_id', $request->user()->company_id ?? -1);
            }
            $prefix = 'login_sessions.';
            $dateColumn = 'started_at';
        } else {
            $query = AuditLog::query()->with('user');
            $prefix = 'audit_logs.';
            $dateColumn = 'created_at';
        }
        if (! empty($data['user_id'])) {
            $query->where($prefix.'user_id', $data['user_id']);
        }
        if (! empty($data['ip'])) {
            $query->where($prefix.'ip_address', $data['ip']);
        }
        if (! empty($data['from'])) {
            $query->where($prefix.$dateColumn, '>=', \Carbon\Carbon::parse($data['from'], 'Asia/Tehran')->startOfDay()->setTimezone(config('app.timezone')));
        }
        if (! empty($data['to'])) {
            $query->where($prefix.$dateColumn, '<', \Carbon\Carbon::parse($data['to'], 'Asia/Tehran')->addDay()->startOfDay()->setTimezone(config('app.timezone')));
        }
        $rows = $query->orderByDesc($prefix.$dateColumn)->paginate(25)->withQueryString();

        return view('workspace.history', compact('rows', 'users', 'kind'));
    }

    private function definitions(Request $request): array
    {
        return json_decode(DB::table('workspace_preferences')->where('user_id', $request->user()->id)->value('charts') ?? '[]', true) ?: [];
    }

    public function charts(Request $request)
    {
        $filterService = app(\App\Services\AssetReportFilters::class);
        $filters = $filterService->validate($request);
        $definitions = $this->definitions($request);
        $isDefault = empty($definitions);
        if ($isDefault) {
            $definitions = [
                ['title'=>'اموال به تفکیک نوع تحویل','group'=>'custody','metric'=>'count','kind'=>'donut','status'=>'all'],
                ['title'=>'توزیع اموال در واحدها','group'=>'department','metric'=>'count','kind'=>'bar','status'=>'all'],
                ['title'=>'ارزش خرید به تفکیک دسته','group'=>'category','metric'=>'value','kind'=>'bar','status'=>'all'],
            ];
        }
        $charts = [];
        foreach ($definitions as $definition) {
            $savedFilters = $definition['filters'] ?? [];
            if (($definition['status'] ?? 'all') !== 'all') {
                $savedFilters['status'] = $definition['status'];
            }
            // Current nonempty filters temporarily override saved filters for comparison.
            $effectiveFilters = array_merge($savedFilters, array_filter($filters, fn ($v) => $v !== null && $v !== ''));
            $charts[] = app(\App\Services\AssetAnalytics::class)->chart($definition, $effectiveFilters);
        }
        return view('workspace.charts', [
            'charts' => $charts, 'isDefault' => $isDefault, 'filters' => $filters,
            ...$filterService->options($request),
        ]);
    }

    public function saveChart(Request $request)
    {
        $definition = $request->validate([
            'title' => ['required', 'string', 'max:80'], 'group' => ['required', Rule::in(array_keys(\App\Services\AssetAnalytics::GROUPS))],
            'metric' => ['required', Rule::in(['count', 'value'])], 'kind' => ['required', Rule::in(['bar', 'donut', 'table'])],
            'status' => ['required', Rule::in(['all', 'warehouse', 'assigned', 'destroyed'])],
        ]);
        $request->validate(['report_filters' => ['nullable', 'array']]);
        $filterRequest = clone $request;
        $filterRequest->replace($request->input('report_filters', []));
        $definition['filters'] = app(\App\Services\AssetReportFilters::class)->validate($filterRequest);
        $charts = $this->definitions($request);
        abort_if(count($charts) >= 12, 422, 'حداکثر ۱۲ نمودار شخصی قابل ذخیره است.');
        $definition['id'] = (string) Str::uuid();
        $charts[] = $definition;
        $this->persistCharts($request, $charts);

        return back()->with('success', 'نمودار ذخیره شد.');
    }

    public function deleteChart(Request $request, string $id)
    {
        $charts = $this->definitions($request);
        abort_unless(collect($charts)->contains('id', $id), 404);
        $this->persistCharts($request, array_values(array_filter($charts, fn ($chart) => $chart['id'] !== $id)));

        return back()->with('success', 'نمودار حذف شد.');
    }

    private function persistCharts(Request $request, array $charts): void
    {
        DB::table('workspace_preferences')->updateOrInsert(['user_id' => $request->user()->id], ['charts' => json_encode($charts), 'updated_at' => now()]);
    }
}
