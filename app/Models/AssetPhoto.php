<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetPhoto extends Model
{
    use BelongsToCompany;

    protected $fillable = [

        'company_id',
        'asset_id',

        'path',
        'original_name',
        'mime_type',
        'file_size',

        'photo_type',
        'caption',

        'is_primary',
        'sort_order',

        'uploaded_by_user_id',
    ];


    protected $casts = [

        'file_size' =>
            'integer',

        'is_primary' =>
            'boolean',

        'sort_order' =>
            'integer',
    ];


    public function asset(): BelongsTo
    {
        return $this->belongsTo(
            Asset::class
        );
    }


    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id'
        );
    }
}