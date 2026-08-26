<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryDisputeItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'delivery_dispute_id',
        'inventory_request_allocation_id',
        'inventory_request_item_id',
        'asset_id',
        'issue_type',
        'description',
        'custody_snapshot',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'custody_snapshot' => 'array',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(
            DeliveryDispute::class,
            'delivery_dispute_id'
        );
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(
            InventoryRequestAllocation::class,
            'inventory_request_allocation_id'
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