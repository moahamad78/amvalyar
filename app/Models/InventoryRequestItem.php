<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryRequestItem extends Model
{
    protected $fillable = [

        'inventory_request_id',
        'asset_category_id',
        'asset_type_id',

        'item_code',
        'item_name',
        'unit',

        'requested_quantity',
        'approved_quantity',
        'fulfilled_quantity',

        'status',

        'description',
        'decision_note',

        'sort_order',
    ];


    protected $casts = [

        'requested_quantity' =>
            'decimal:3',

        'approved_quantity' =>
            'decimal:3',

        'fulfilled_quantity' =>
            'decimal:3',

        'sort_order' =>
            'integer',
    ];


    public function request(): BelongsTo
    {
        return $this->belongsTo(
            InventoryRequest::class,
            'inventory_request_id'
        );
    }


    public function category(): BelongsTo
    {
        return $this->belongsTo(
            AssetCategory::class,
            'asset_category_id'
        );
    }


    public function assetType(): BelongsTo
    {
        return $this->belongsTo(
            AssetType::class,
            'asset_type_id'
        );
    }
}