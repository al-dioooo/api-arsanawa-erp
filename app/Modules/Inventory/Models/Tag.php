<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'company_id',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
        ];
    }
}
