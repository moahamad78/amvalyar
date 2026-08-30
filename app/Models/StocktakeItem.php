<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StocktakeItem extends Model
{
    use BelongsToCompany;

    public const RESULT_PENDING = 'pending';
    public const RESULT_MATCHED = 'matched';
    public const RESULT_MISSING = 'missing';
    public const RESULT_MISPLACED = 'misplaced';
    public const RESULT_CUSTODY_MISMATCH = 'custody_mismatch';
    public const RESULT_DAMAGED = 'damaged';
    public const RESULT_RECOUNT = 'recount';

    protected $fillable = [
        'company_id',
        'stocktake_id',
        'asset_id',
        'expected_status',
        'expected_custody_type',
        'expected_user_id',
        'expected_employee_id',
        'expected_department_id',
        'expected_site_id',
        'expected_location_id',
        'result_status',
        'observed_custody_type',
        'observed_user_id',
        'observed_employee_id',
        'observed_department_id',
        'observed_site_id',
        'observed_location_id',
        'counted_by',
        'counted_at',
        'count_round',
        'notes',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'count_round' => 'integer',
    ];

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}