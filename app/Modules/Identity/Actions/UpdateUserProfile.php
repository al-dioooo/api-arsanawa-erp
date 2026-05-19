<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\UserProfile;
use App\Modules\Identity\Models\UserStatus;

class UpdateUserProfile
{
    /**
     * Upsert the profile fields for a user.
     *
     * @param  array{display_name?: string, avatar?: string, locale?: string, timezone?: string}  $data
     * @return array{user: User, profile: UserProfile, status: UserStatus}
     */
    public function execute(User $user, array $data): array
    {
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );

        $status = UserStatus::firstOrNew(['user_id' => $user->id], ['status' => 'active']);

        return compact('user', 'profile', 'status');
    }
}
