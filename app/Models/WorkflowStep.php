<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowStep extends Model
{
    protected $fillable = [

        'workflow_id',
        'name',
        'code',
        'step_type',
        'approver_type',
        'approver_reference_id',
        'sort_order',
        'is_required',
        'is_active',
        'rejection_action',
        'due_hours',
        'conditions',
        'settings',
        'description',
    ];


    protected $casts = [

        'sort_order' =>
            'integer',

        'is_required' =>
            'boolean',

        'is_active' =>
            'boolean',

        'due_hours' =>
            'integer',

        'conditions' =>
            'array',

        'settings' =>
            'array',
    ];


    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            Workflow::class
        );
    }


    public function company(): ?Company
    {
        return $this->workflow?->company;
    }
}