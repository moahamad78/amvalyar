<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Workflow extends Model
{
    use BelongsToCompany;


    protected $fillable = [

        'company_id',
        'name',
        'code',
        'process_type',
        'description',
        'is_active',
        'is_default',
        'version',
        'created_by',
        'updated_by',
    ];


    protected $casts = [

        'is_active' =>
            'boolean',

        'is_default' =>
            'boolean',

        'version' =>
            'integer',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }


    public function steps(): HasMany
    {
        return $this->hasMany(
            WorkflowStep::class
        )
        ->orderBy('sort_order')
        ->orderBy('id');
    }


    public function activeSteps(): HasMany
    {
        return $this->steps()
            ->where(
                'is_active',
            true
        );
    }


    /**
     * Workflow instances keep their own step snapshot, so historical and
     * in-flight requests remain stable even when the designer changes.
     */
    public function instances(): HasMany
    {
        return $this->hasMany(
            WorkflowInstance::class
        );
    }


    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }


    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}
