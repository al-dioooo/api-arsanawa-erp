<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\GetUserProfile;
use App\Modules\Identity\Actions\UpdateUserProfile;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Requests\ViewUserRequest;
use App\Modules\Identity\Http\Resources\IdentityUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentityController extends Controller
{
    public function profile(Request $request, GetUserProfile $action): JsonResponse
    {
        $result = $action->execute($request->user());

        return $this->success(
            new IdentityUserResource($result['user'], $result['profile'], $result['status']),
            __('Profile retrieved.'),
        );
    }

    public function updateProfile(UpdateProfileRequest $request, UpdateUserProfile $action): JsonResponse
    {
        $result = $action->execute($request->user(), $request->validated());

        return $this->success(
            new IdentityUserResource($result['user'], $result['profile'], $result['status']),
            __('Profile updated.'),
        );
    }

    public function show(ViewUserRequest $request, GetUserProfile $action, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return $this->error(__('User not found.'), 404);
        }

        $result = $action->execute($user);

        return $this->success(
            new IdentityUserResource($result['user'], $result['profile'], $result['status']),
            __('User profile retrieved.'),
        );
    }
}
