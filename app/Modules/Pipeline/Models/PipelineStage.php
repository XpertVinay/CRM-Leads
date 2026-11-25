<?php

namespace App\Modules\Pipeline\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use HasFactory;

    public const CATEGORIES = ['open', 'won', 'lost'];

    protected $fillable = [
        'pipeline_id',
        'name',
        'order_index',
        'win_probability',
        'category',
        'stale_days',
    ];

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'win_probability' => 'integer',
            'stale_days' => 'integer',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pipeline_stage_id');
    }

    public function isOpen(): bool
    {
        return $this->category === 'open';
    }

    public function isWon(): bool
    {
        return $this->category === 'won';
    }

    public function isLost(): bool
    {
        return $this->category === 'lost';
    }
}
