<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryRequest extends Model
{
    use BelongsToCompany;


    protected $fillable = [

        'company_id',

        'request_number',

        'requester_employee_id',
        'requester_user_id',

        'site_id',
        'department_id',

        'delivery_target_type',
        'target_site_id',
        'target_department_id',
        'target_location_id',

        'workflow_instance_id',

        'status',
        'priority',

        'purpose',
        'description',

        'submitted_at',
        'approved_at',
        'rejected_at',
        'fulfilled_at',
    ];


    protected $casts = [

        'submitted_at' =>
            'datetime',

        'approved_at' =>
            'datetime',

        'rejected_at' =>
            'datetime',

        'fulfilled_at' =>
            'datetime',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }


    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'requester_employee_id'
        );
    }


    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requester_user_id'
        );
    }


    public function site(): BelongsTo
    {
        return $this->belongsTo(
            Site::class
        );
    }


    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class
        );
    }


    public function targetSite(): BelongsTo
    {
        return $this->belongsTo(
            Site::class,
            'target_site_id'
        );
    }

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'target_department_id'
        );
    }

    public function targetLocation(): BelongsTo
    {
        return $this->belongsTo(
            Location::class,
            'target_location_id'
        );
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstance::class
        );
    }


    public function items(): HasMany
    {
        return $this->hasMany(
            InventoryRequestItem::class
        )
        ->orderBy('sort_order')
        ->orderBy('id');
    }
}