<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowInstanceBranchItem extends Model
{
    protected $fillable = [

        'workflow_instance_branch_id',
        'inventory_request_item_id',
        'asset_id',
    ];


    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            WorkflowInstanceBranch::class,
            'workflow_instance_branch_id'
        );
    }


    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(
            InventoryRequestItem::class,
            'inventory_request_item_id'
        );
    }


    public function asset(): BelongsTo
    {
        return $this->belongsTo(
            Asset::class
        );
    }
}