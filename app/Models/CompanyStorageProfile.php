<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class CompanyStorageProfile extends Model
{
    use BelongsToCompany;
    public const DRIVER_S3='s3';
    protected $fillable=['company_id','name','driver','bucket','region','endpoint','url','root_prefix','access_key','secret_key','use_path_style_endpoint','is_default','is_active','last_tested_at','last_test_status'];
    protected function casts(): array {
        return [
            'access_key'=>'encrypted',
            'secret_key'=>'encrypted',
            'use_path_style_endpoint'=>'boolean',
            'is_default'=>'boolean',
            'is_active'=>'boolean',
            'last_tested_at'=>'datetime',
        ];
    }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}