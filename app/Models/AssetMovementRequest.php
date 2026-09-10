<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetMovementRequest extends Model
{
    use BelongsToCompany;


    public const TYPE_TRANSFER =
        'transfer';

    public const TYPE_RETURN =
        'return';

    public const TYPE_DISPOSAL =
        'disposal';


    public const STATUS_DRAFT =
        'draft';

    public const STATUS_SUBMITTED =
        'submitted';

    public const STATUS_APPROVED =
        'approved';

    public const STATUS_REJECTED =
        'rejected';

    public const STATUS_COMPLETED =
        'completed';

    public const STATUS_CANCELLED =
        'cancelled';


    protected $fillable = [

        'company_id',
        'asset_id',
        'movement_type',
        'target_custody_type',
        'target_user_id',
        'target_employee_id',
        'target_department_id',
        'target_site_id',
        'target_location_id',

        'requested_by_user_id',
        'requested_by_employee_id',

        'workflow_instance_id',

        'status',

        'reason',
        'notes',

        'submitted_at',
        'completed_at',
        'cancelled_at',
    ];


    protected $casts = [

        'submitted_at' =>
            'datetime',

        'completed_at' =>
            'datetime',

        'cancelled_at' =>
            'datetime',
    ];


    public function asset(): BelongsTo
    {
        return $this->belongsTo(
            Asset::class
        );
    }


    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'target_user_id'
        );
    }

    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'target_employee_id'
        );
    }


    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id'
        );
    }


    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'requested_by_employee_id'
        );
    }


    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstance::class,
            'workflow_instance_id'
        );
    }
}
