<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class AssetTransaction extends Model
{
    use BelongsToCompany;

    protected $fillable = [
                'company_id',
'asset_id',
        'asset_movement_request_id',
        'from_user_id',
        'to_user_id',
        'type',
        'plate_number',
        'description',
        'created_by',
        'from_custody_type',
        'to_custody_type',
        'from_employee_id',
        'to_employee_id',
        'from_department_id',
        'to_department_id',
        'from_site_id',
        'to_site_id',
        'from_location_id',
        'to_location_id',
    ];



    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }



    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class,'from_user_id');
    }



    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class,'to_user_id');
    }



    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class,'created_by');
    }

    public function fromEmployee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class, 'from_employee_id');
    }

    public function toEmployee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class, 'to_employee_id');
    }

    public function fromLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Location::class, 'from_location_id');
    }

    public function toLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Location::class, 'to_location_id');
    }
}
