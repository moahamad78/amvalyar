<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetRepairWorkOrder extends Model
{
    use BelongsToCompany;

    public const TYPE_INTERNAL = 'internal';
    public const TYPE_EXTERNAL = 'external';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const OUTCOME_REPAIRED = 'repaired';
    public const OUTCOME_PARTIALLY_REPAIRED = 'partially_repaired';
    public const OUTCOME_UNREPAIRABLE = 'unrepairable';
    public const OUTCOME_SENT_EXTERNAL = 'sent_external';

    protected $fillable = [
        'company_id',
        'asset_repair_request_id',
        'work_order_number',
        'repair_type',
        'assigned_employee_id',
        'external_provider_name',
        'status',
        'outcome',
        'received_at',
        'expected_return_at',
        'actual_return_at',
        'labor_cost',
        'parts_cost',
        'external_service_cost',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $appends = [
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'actual_return_at' => 'datetime',
            'labor_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'external_service_cost' => 'decimal:2',
        ];
    }

    public function getTotalCostAttribute(): string
    {
        $total =
            (float) ($this->labor_cost ?? 0)
            + (float) ($this->parts_cost ?? 0)
            + (float) ($this->external_service_cost ?? 0);

        return number_format($total, 2, '.', '');
    }

    public function repairRequest(): BelongsTo
    {
        return $this->belongsTo(
            AssetRepairRequest::class,
            'asset_repair_request_id'
        );
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'assigned_employee_id'
        );
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by_user_id'
        );
    }
}