<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardTarget extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'reward_id',
        'target_type',
        'target_id',
    ];

    protected function casts(): array
    {
        return [
            'reward_id' => 'integer',
            'target_id' => 'integer',
        ];
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }
}
