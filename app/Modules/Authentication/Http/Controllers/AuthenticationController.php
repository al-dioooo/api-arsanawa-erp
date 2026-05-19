<?php

namespace App\Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\Actions\AuthenticateUser;
use App\Modules\Authentication\Actions\RefreshAccessToken;
use App\Modules\Authentication\Actions\ResetUserPassword;
use App\Modules\Authentication\Actions\RevokeAccessToken;
use App\Modules\Authentication\Actions\SendPasswordResetLink;
use App\Modules\Authentication\Http\Requests\ForgotPasswordRequest;
use App\Modules\Authentication\Http\Requests\LoginRequest;
use App\Modules\Authentication\Http\Requests\ResetPasswordRequest;
use App\Modules\Authentication\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationController extends Controller
{
    public function login(LoginRequest $request, AuthenticateUser $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return $this->success(
            [
                'token_type' => 'Bearer',
                'access_token' => $result['plainTextToken'],
                'expires_at' => $result['accessToken']->expires_at?->toISOString(),
                'user' => new AuthenticatedUserResource($result['user']),
            ],
            __('Login successful.'),
        );
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new AuthenticatedUserResource($request->user()),
            __('Authenticated user retrieved.'),
        );
    }

    public function logout(Request $request, RevokeAccessToken $action): JsonResponse
    {
        $action->execute($request->user());

        return $this->success(message: __('Logged out.'));
    }

    public function refresh(Request $request, RefreshAccessToken $action): JsonResponse
    {
        $result = $action->execute($request->user());

        return $this->success(
            [
                'token_type' => 'Bearer',
                'access_token' => $result['plainTextToken'],
                'expires_at' => $result['accessToken']->expires_at?->toISOString(),
                'user' => new AuthenticatedUserResource($result['user']),
            ],
            __('Token refreshed.'),
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request, SendPasswordResetLink $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return $result['succeeded']
            ? $this->success(message: $result['message'])
            : $this->error($result['message']);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetUserPassword $action): JsonResponse
    {
        $result = $action->execute(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        return $result['succeeded']
            ? $this->success(message: $result['message'])
            : $this->error($result['message']);
    }
}
