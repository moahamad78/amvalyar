<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowStepAction extends Model
{
    protected $fillable = [

        'workflow_instance_id',
        'workflow_instance_step_id',

        'action',

        'from_status',
        'to_status',

        'actor_employee_id',
        'actor_user_id',

        'comment',
        'metadata',

        'acted_at',
    ];


    protected $casts = [

        'metadata' =>
            'array',

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


    public function step(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstanceStep::class,
            'workflow_instance_step_id'
        );
    }


    public function actorEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'actor_employee_id'
        );
    }


    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }
}