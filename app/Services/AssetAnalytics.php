<?php

namespace App\Services;

use App\Models\{Asset, AssetCategory, Department, Employee, Site};

final class AssetAnalytics
{
    public const GROUPS = ['category' => 'دسته اموال', 'department' => 'واحد سازمانی', 'site' => 'سایت', 'employee' => 'تحویل‌گیرنده', 'status' => 'وضعیت', 'custody' => 'نوع تحویل'];

    public function chart(array $definition, array $filters = []): array
    {
        $group = $definition['group'];
        $columns = ['status' => 'status', 'custody' => 'custody_type', 'category' => 'asset_category_id', 'site' => 'current_site_id', 'department' => 'custody_department_id', 'employee' => 'custody_employee_id'];
        $filterKeys = ['status' => 'status', 'custody' => 'custody_type', 'category' => 'category_id', 'site' => 'site_id', 'department' => 'department_id', 'employee' => 'employee_id'];
        $column = $columns[$group];
        $query = app(AssetReportFilters::class)->apply(Asset::query(), $filters);
        $aggregate = $definition['metric'] === 'value' ? 'SUM(COALESCE(purchase_price,0))' : 'COUNT(*)';
        $rows = $query->selectRaw($column.' as group_key, '.$aggregate.' as amount')->groupBy($column)->orderByDesc('amount')->orderBy($column)->get();
        $labels = match ($group) {
            'category' => AssetCategory::query()->pluck('name', 'id'),
            'site' => Site::query()->pluck('name', 'id'),
            'department' => Department::query()->pluck('name', 'id'),
            'employee' => Employee::query()->pluck('display_name', 'id'),
            default => collect(['warehouse' => 'انبار', 'assigned' => 'تحویل‌شده', 'destroyed' => 'اسقاط', 'employee' => 'پرسنلی', 'organization' => 'سازمانی', 'user' => 'کاربری']),
        };
        $total = (float) $rows->sum('amount');
        foreach ($rows as $row) {
            $row->label = $labels[$row->group_key] ?? ($row->group_key ?: 'تعیین‌نشده');
            $row->percentage = $total > 0 ? round(100 * $row->amount / $total, 1) : 0;
            // Null groups stay unlinked: an empty filter would misleadingly show all assets.
            $row->url = $row->group_key === null || $row->group_key === '' ? null : route('reports.index', array_merge($filters, [$filterKeys[$group] => $row->group_key]));
        }
        return ['definition' => $definition, 'filters' => $filters, 'rows' => $rows, 'max' => max(1, (float) $rows->max('amount')), 'total' => $total];
    }
}
