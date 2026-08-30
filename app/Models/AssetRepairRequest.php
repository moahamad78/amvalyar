<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetRepairRequest extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_REPAIR = 'in_repair';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'company_id', 'asset_id', 'workflow_instance_id',
        'requested_by_user_id', 'requested_by_employee_id',
        'status', 'priority', 'title', 'problem_description',
        'diagnosis', 'repair_notes', 'estimated_cost', 'actual_cost',
        'reported_at', 'submitted_at', 'started_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'reported_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function workflowInstance(): BelongsTo { return $this->belongsTo(WorkflowInstance::class); }
    public function requesterUser(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function requesterEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'requested_by_employee_id'); }
}