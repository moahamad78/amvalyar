<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetCategoryApprovalRoute extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'asset_category_id',
        'process_type',
        'approver_type',
        'approver_reference_id',
        'is_required',
        'is_active',
        'sort_order',
        'settings',
    ];

    protected $casts = [
        'approver_reference_id' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            AssetCategory::class,
            'asset_category_id'
        );
    }
}