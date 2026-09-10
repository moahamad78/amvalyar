<?php

namespace App\Services;

use App\Models\{AssetCategory, Company, Department, Employee, Location, Site};
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AssetReportFilters
{
    public function validate(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['warehouse', 'assigned', 'destroyed'])],
            'custody_type' => ['nullable', Rule::in(['employee', 'organization', 'user'])],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'site_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'min_value' => ['nullable', 'numeric', 'min:0'],
            'max_value' => ['nullable', 'numeric', 'min:0', ...($request->filled('min_value') ? ['gte:min_value'] : [])],
            'sort' => ['nullable', Rule::in(['id', 'title', 'purchase_price', 'purchase_date'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([20, 25, 50, 100])],
            'transaction_type' => ['nullable', Rule::in(['delivery', 'transfer', 'return', 'destroy'])],
            'date_from' => ['nullable', 'string', 'max:10'],
            'date_to' => ['nullable', 'string', 'max:10'],
        ]);
        foreach (['date_from', 'date_to'] as $field) {
            if (! empty($data[$field])) {
                try {
                    if (! JalaliDate::toGregorianDate($data[$field])) {
                        throw new \InvalidArgumentException;
                    }
                } catch (\Throwable) {
                    throw ValidationException::withMessages([$field => 'تاریخ شمسی معتبر وارد کنید.']);
                }
            }
        }
        if (! empty($data['date_from']) && ! empty($data['date_to']) && JalaliDate::toGregorianDate($data['date_from']) > JalaliDate::toGregorianDate($data['date_to'])) {
            throw ValidationException::withMessages(['date_to' => 'تاریخ پایان باید پس از تاریخ شروع باشد.']);
        }
        foreach (['category_id' => AssetCategory::class, 'employee_id' => Employee::class, 'department_id' => Department::class, 'site_id' => Site::class, 'location_id' => Location::class] as $key => $model) {
            if (! empty($data[$key])) {
                $query = $model::query()->whereKey($data[$key]);
                if ($request->user()->isSuperAdmin() && ! empty($data['company_id'])) {
                    $query->where('company_id', $data['company_id']);
                }
                abort_unless($query->exists(), 404);
            }
        }
        return $data;
    }

    public function apply(Builder $query, array $filters): Builder
    {
        foreach (['status' => 'status', 'custody_type' => 'custody_type', 'category_id' => 'asset_category_id', 'employee_id' => 'custody_employee_id', 'department_id' => 'custody_department_id', 'site_id' => 'current_site_id', 'location_id' => 'current_location_id'] as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }
        if (auth()->user()?->isSuperAdmin() && ! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }
        if (isset($filters['min_value']) && $filters['min_value'] !== '') {
            $query->where('purchase_price', '>=', $filters['min_value']);
        }
        if (isset($filters['max_value']) && $filters['max_value'] !== '') {
            $query->where('purchase_price', '<=', $filters['max_value']);
        }
        if (trim($filters['search'] ?? '') !== '') {
            $term = '%'.trim($filters['search']).'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $term)->orWhere('asset_code', 'like', $term)->orWhere('inventory_code', 'like', $term)->orWhere('serial_number', 'like', $term)->orWhere('brand', 'like', $term));
        }
        return $query;
    }

    public function transactions(Builder $query, array $filters): Builder
    {
        $query->whereHas('asset', fn (Builder $assets) => $this->apply($assets, $filters));
        if (! empty($filters['transaction_type'])) {
            $query->where('type', $filters['transaction_type']);
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $date = \Carbon\Carbon::parse(JalaliDate::toGregorianDate($filters[$field]), 'Asia/Tehran');
                $query->where('created_at', $field === 'date_from' ? '>=' : '<', ($field === 'date_from' ? $date->startOfDay() : $date->addDay()->startOfDay())->setTimezone(config('app.timezone')));
            }
        }
        return $query;
    }

    public function options(Request $request): array
    {
        $result = [];
        foreach (['categories' => AssetCategory::class, 'departments' => Department::class, 'sites' => Site::class, 'locations' => Location::class, 'employees' => Employee::class] as $key => $model) {
            $query = $model::query();
            if ($request->user()->isSuperAdmin() && $request->filled('company_id')) {
                $query->where('company_id', $request->integer('company_id'));
            }
            $result[$key] = $query->orderBy($key === 'employees' ? 'display_name' : 'name')->get();
        }
        $result['companies'] = $request->user()->isSuperAdmin() ? Company::query()->orderBy('name')->get() : collect();
        return $result;
    }
}
