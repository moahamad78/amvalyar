<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkflowInstanceBranch extends Model
{
    use BelongsToCompany;

    protected $fillable = [

        'company_id',

        'workflow_instance_id',
        'parent_step_id',

        'asset_category_id',

        'branch_key',
        'name',

        'approver_type',
        'approver_reference_id',

        'resolved_employee_id',
        'resolved_user_id',

        'status',
        'is_required',
        'sort_order',

        'activated_at',
        'acted_at',

        'acted_by_employee_id',
        'acted_by_user_id',

        'comment',
        'settings',
    ];


    protected $casts = [

        'is_required' =>
            'boolean',

        'sort_order' =>
            'integer',

        'activated_at' =>
            'datetime',

        'acted_at' =>
            'datetime',

        'settings' =>
            'array',
    ];


    public function instance(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstance::class,
            'workflow_instance_id'
        );
    }


    public function parentStep(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstanceStep::class,
            'parent_step_id'
        );
    }


    public function category(): BelongsTo
    {
        return $this->belongsTo(
            AssetCategory::class,
            'asset_category_id'
        );
    }


    public function resolvedEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'resolved_employee_id'
        );
    }


    public function resolvedUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_user_id'
        );
    }


    public function items(): HasMany
    {
        return $this->hasMany(
            WorkflowInstanceBranchItem::class,
            'workflow_instance_branch_id'
        );
    }
}