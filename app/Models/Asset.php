<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\EnforcesCompanyAssetLimit;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    /*
     * Asset code is immutable after creation.
     *
     * The system may generate asset_code during creation,
     * but normal model updates are not allowed to replace it.
     */
    protected static function booted(): void
    {
        /*
         * Permanent Asset Code lifecycle:
         *
         * NULL -> CODE
         *     Allowed exactly once during official issuance.
         *
         * CODE -> different CODE
         *     Forbidden.
         *
         * CODE -> NULL
         *     Forbidden.
         */

        static::updating(
            function (Asset $asset): void {

                if (
                    !$asset->isDirty(
                        'asset_code'
                    )
                ) {
                    return;
                }


                $original =
                    trim(
                        (string) (
                            $asset->getOriginal(
                                'asset_code'
                            )
                            ?? ''
                        )
                    );


                $new =
                    trim(
                        (string) (
                            $asset->asset_code
                            ?? ''
                        )
                    );


                /*
                 * First official issuance.
                 */
                if (
                    $original === ''
                    &&
                    $new !== ''
                ) {
                    return;
                }


                /*
                 * Once issued, the permanent code
                 * can never be changed or cleared.
                 */
                $asset->asset_code =
                    $asset->getOriginal(
                        'asset_code'
                    );
            }
        );
    }

    use BelongsToCompany, EnforcesCompanyAssetLimit;
    use HasFactory;

    protected $fillable = [
                'company_id',
'asset_category_id',
        'asset_type_id',
        'coding_site_id',
        'coding_site_code_snapshot',
        'main_nature_code_snapshot',
        'sub_nature_code_snapshot',
        'inventory_code',
        'asset_code',
        'asset_code_issued_at',
        'asset_code_issued_by_user_id',
        'title',
        'brand',
        'model',
        'serial_number',
        'manufacturer',
        'country',
        'purchase_date',
        'purchase_price',
        'description',
        'is_active',
        'status',
        'custody_type',
        'custody_user_id',
        'custody_employee_id',
        'custody_department_id',
        'current_site_id',
        'current_location_id',
    ];


    /**
     * @deprecated Physical plate code is the permanent asset_code.
     * This compatibility alias prevents legacy read paths from creating
     * a second identifier.
     */
    public function getPlateNumberAttribute(mixed $value): ?string
    {
        $code = trim(
            (string) (
                $this->attributes['asset_code']
                ?? ''
            )
        );

        return $code !== ''
            ? $code
            : null;
    }

    /**
     * @deprecated Separate plate numbers are no longer persisted.
     */
    public function setPlateNumberAttribute(mixed $value): void
    {
        unset(
            $this->attributes['plate_number']
        );
    }
    protected function casts(): array
    {
        return [
            'purchase_date'  => 'date',
            'asset_code_issued_at' => 'datetime',
            'purchase_price' => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            AssetCategory::class,
            'asset_category_id'
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AssetTransaction::class);
    }


    public function isActive(): bool
    {
        return $this->is_active === true;
    }


    public function assetType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(
            AssetType::class,
            'asset_type_id'
        );
    }


    public function photos(): HasMany
    {
        return $this->hasMany(
            AssetPhoto::class
        )
            ->orderBy('sort_order')
            ->orderBy('id');
    }


    public function primaryPhoto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            AssetPhoto::class
        )
            ->where(
                'is_primary',
                true
            );
    }


    public function attributeValues(): HasMany
    {
        return $this->hasMany(
            AssetAttributeValue::class
        );
    }
    public function custodyEmployee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class, 'custody_employee_id');
    }

    public function custodyDepartment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Department::class, 'custody_department_id');
    }

    public function currentSite(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Site::class, 'current_site_id');
    }

    public function currentLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Location::class, 'current_location_id');
    }

    public function codingSite(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Site::class,
            'coding_site_id'
        );
    }

    public function assetCodeIssuedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'asset_code_issued_by_user_id'
        );
    }
}
