<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;

final class AssetCodePolicySetting extends Model
{
    use BelongsToCompany;

    public const MODE_SEMANTIC = 'semantic';
    public const MODE_LEGACY = 'legacy';


    protected $fillable = [
        'company_id',
        'mode',
        'fallback_prefix',
        'padding',
        'separator',
        'include_category',
        'include_type',
    ];


    protected function casts(): array
    {
        return [
            'company_id' =>
                'integer',

            'padding' =>
                'integer',

            'include_category' =>
                'boolean',

            'include_type' =>
                'boolean',
        ];
    }


    public static function defaultsFor(
        int $companyId
    ): self {

        $setting = new self();

        $setting->company_id =
            $companyId;

        $setting->mode =
            self::MODE_SEMANTIC;

        $setting->fallback_prefix =
            'AST';

        $setting->padding =
            6;

        $setting->separator =
            '-';

        $setting->include_category =
            true;

        $setting->include_type =
            true;

        return $setting;
    }
}