<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;

final class AssetCodeFormulaSetting extends Model
{
    use BelongsToCompany;

    public const SEGMENT_SITE = 'site';
    public const SEGMENT_CATEGORY = 'category';
    public const SEGMENT_TYPE = 'type';
    public const SEGMENT_SERIAL = 'serial';

    public const SCOPE_FAMILY = 'family';
    public const SCOPE_SITE = 'site';
    public const SCOPE_CATEGORY = 'category';
    public const SCOPE_COMPANY = 'company';

    protected $fillable = [
        'company_id',
        'segment_order',
        'separator',
        'site_length',
        'category_length',
        'type_length',
        'serial_length',
        'sequence_scope',
        'enforce_segment_lengths',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'segment_order' => 'array',
            'site_length' => 'integer',
            'category_length' => 'integer',
            'type_length' => 'integer',
            'serial_length' => 'integer',
            'enforce_segment_lengths' => 'boolean',
        ];
    }

    public static function defaultsFor(int $companyId): self
    {
        $setting = new self();
        $setting->company_id = $companyId;
        $setting->segment_order = [
            self::SEGMENT_SITE,
            self::SEGMENT_CATEGORY,
            self::SEGMENT_TYPE,
            self::SEGMENT_SERIAL,
        ];
        $setting->separator = '-';
        $setting->site_length = 2;
        $setting->category_length = 2;
        $setting->type_length = 3;
        $setting->serial_length = 4;
        $setting->sequence_scope = self::SCOPE_FAMILY;
        $setting->enforce_segment_lengths = false;

        return $setting;
    }

    public static function allowedSegments(): array
    {
        return [
            self::SEGMENT_SITE,
            self::SEGMENT_CATEGORY,
            self::SEGMENT_TYPE,
            self::SEGMENT_SERIAL,
        ];
    }

    public static function allowedScopes(): array
    {
        return [
            self::SCOPE_FAMILY,
            self::SCOPE_SITE,
            self::SCOPE_CATEGORY,
            self::SCOPE_COMPANY,
        ];
    }
}
