<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class WorkflowInstance extends Model
{
    use BelongsToCompany;


    protected $fillable = [

        'company_id',
        'workflow_id',

        'workflow_name',
        'workflow_code',
        'process_type',
        'workflow_version',

        'subject_type',
        'subject_id',

        'requester_employee_id',
        'requester_user_id',

        'status',
        'current_step_id',

        'started_at',
        'completed_at',
        'rejected_at',
        'cancelled_at',

        'context',
        'description',
    ];


    protected $casts = [

        'workflow_version' =>
            'integer',

        'started_at' =>
            'datetime',

        'completed_at' =>
            'datetime',

        'rejected_at' =>
            'datetime',

        'cancelled_at' =>
            'datetime',

        'context' =>
            'array',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }


    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            Workflow::class
        );
    }


    public function subject(): MorphTo
    {
        return $this->morphTo();
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


    public function steps(): HasMany
    {
        return $this->hasMany(
            WorkflowInstanceStep::class
        )
        ->orderBy('sort_order')
        ->orderBy('id');
    }


    public function actions(): HasMany
    {
        return $this->hasMany(
            WorkflowStepAction::class,
            'workflow_instance_id'
        )
        ->orderBy('acted_at')
        ->orderBy('id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstanceStep::class,
            'current_step_id'
        );
    }
}