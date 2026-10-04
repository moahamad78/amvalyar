<?php

namespace App\Models\Concerns;

trait HidesFinancialValues
{
    public function getAttribute($key)
    {
        if (in_array($key, ['purchase_price', 'estimated_cost', 'actual_cost', 'labor_cost', 'parts_cost', 'external_service_cost', 'total_cost'], true)
            && request()->isMethodSafe() && ($user = auth()->user()) && !app(\App\Services\RoleAccessService::class)->financial($user)) {
            return null;
        }
        return parent::getAttribute($key);
    }
}
