<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryRequestAllocation extends Model
{
    use BelongsToCompany;

    protected $fillable = [

        'company_id',

        'inventory_request_id',
        'inventory_request_item_id',

        'asset_id',

        'status',

        'reserved_by_user_id',
        'reserved_by_employee_id',

        'reserved_at',
        'approved_at',
        'released_at',
        'delivered_at',
        'receipt_confirmed_at',

        'note',
    ];


    protected $casts = [

        'reserved_at' =>
            'datetime',

        'approved_at' =>
            'datetime',

        'released_at' =>
            'datetime',

        'delivered_at' =>
            'datetime',

        'receipt_confirmed_at' =>
            'datetime',
    ];


    public function request(): BelongsTo
    {
        return $this->belongsTo(
            InventoryRequest::class,
            'inventory_request_id'
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


    public function reservedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reserved_by_user_id'
        );
    }


    public function reservedByEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'reserved_by_employee_id'
        );
    }
}