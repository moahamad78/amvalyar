<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;

final class AssetCodeSequence extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'prefix',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'company_id' =>
                'integer',

            'last_sequence' =>
                'integer',
        ];
    }
}