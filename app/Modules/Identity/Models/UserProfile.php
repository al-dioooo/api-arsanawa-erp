<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $display_name
 * @property string|null $avatar
 * @property string|null $locale
 * @property string|null $timezone
 * @property-read User $user
 */
class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'avatar',
        'locale',
        'timezone',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
