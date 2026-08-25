<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkflowInstanceStep extends Model
{
    protected $fillable = [

        'workflow_instance_id',
        'workflow_step_id',

        'name',
        'code',
        'step_type',
        'approver_type',
        'approver_reference_id',
        'sort_order',
        'is_required',
        'rejection_action',
        'due_hours',
        'conditions',
        'settings',

        'resolved_employee_id',
        'resolved_user_id',

        'status',

        'activated_at',
        'due_at',
        'acted_at',

        'acted_by_employee_id',
        'acted_by_user_id',

        'comment',
    ];


    protected $casts = [

        'sort_order' =>
            'integer',

        'is_required' =>
            'boolean',

        'due_hours' =>
            'integer',

        'conditions' =>
            'array',

        'settings' =>
            'array',

        'activated_at' =>
            'datetime',

        'due_at' =>
            'datetime',

        'acted_at' =>
            'datetime',
    ];


    public function instance(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstance::class,
            'workflow_instance_id'
        );
    }


    public function actions(): HasMany
    {
        return $this->hasMany(
            WorkflowStepAction::class,
            'workflow_instance_step_id'
        )
        ->orderBy('acted_at')
        ->orderBy('id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowStep::class
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


    public function actedByEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'acted_by_employee_id'
        );
    }


    public function actedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'acted_by_user_id'
        );
    }
}