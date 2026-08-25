<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetPlateTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'width_mm',
        'height_mm',
        'orientation',
        'elements',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'elements' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function defaultElements(): array
    {
        return [
            [
                'id' => 'company',
                'type' => 'field',
                'field' => 'company_name',
                'label' => 'نام شرکت',
                'x' => 4,
                'y' => 3,
                'w' => 42,
                'h' => 5,
                'font_size' => 10,
                'align' => 'center',
                'bold' => true,
            ],
            [
                'id' => 'asset_code',
                'type' => 'field',
                'field' => 'asset_code',
                'label' => 'کد اموال',
                'x' => 4,
                'y' => 10,
                'w' => 42,
                'h' => 6,
                'font_size' => 12,
                'align' => 'center',
                'bold' => true,
            ],
            [
                'id' => 'qr',
                'type' => 'qr',
                'field' => 'asset_code',
                'label' => 'QR',
                'x' => 32,
                'y' => 17,
                'w' => 14,
                'h' => 10,
                'font_size' => 8,
                'align' => 'center',
                'bold' => false,
            ],
        ];
    }
}
