<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Access to the single fixed-asset warehouse of a site.
 *
 * A Site is deliberately the warehouse boundary; there is no second,
 * independently named warehouse layer to misconfigure.
 */
final class WarehouseSiteAccess extends Model
{
    protected $fillable = [
        'company_id', 'site_id', 'user_id',
        'can_view', 'can_issue', 'can_receive', 'can_transfer', 'can_stocktake',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean', 'can_issue' => 'boolean', 'can_receive' => 'boolean',
            'can_transfer' => 'boolean', 'can_stocktake' => 'boolean',
        ];
    }

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
