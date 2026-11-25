<?php

namespace App\Modules\Pipeline\Models;

use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class, 'pipeline_id')
            ->orderBy('order_index')
            ->orderBy('id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pipeline_id');
    }
}
