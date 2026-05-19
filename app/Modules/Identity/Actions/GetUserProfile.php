<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\UserProfile;
use App\Modules\Identity\Models\UserStatus;

class GetUserProfile
{
    /**
     * Load the profile and status for a user, creating defaults if absent.
     *
     * @return array{user: User, profile: UserProfile, status: UserStatus}
     */
    public function execute(User $user): array
    {
        $profile = UserProfile::firstOrNew(['user_id' => $user->id]);
        $status = UserStatus::firstOrNew(['user_id' => $user->id], ['status' => 'active']);

        return compact('user', 'profile', 'status');
    }
}
