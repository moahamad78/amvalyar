<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DeliveryDispute extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'inventory_request_id',
        'workflow_instance_id',
        'requester_receipt_step_id',
        'reported_by_user_id',
        'reported_by_employee_id',
        'status',
        'reason_code',
        'description',
        'reported_at',
        'warehouse_received_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'warehouse_received_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function inventoryRequest(): BelongsTo
    {
        return $this->belongsTo(
            InventoryRequest::class
        );
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstance::class
        );
    }

    public function requesterReceiptStep(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstanceStep::class,
            'requester_receipt_step_id'
        );
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by_user_id'
        );
    }

    public function reportedByEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'reported_by_employee_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            DeliveryDisputeItem::class
        )->orderBy('id');
    }
}