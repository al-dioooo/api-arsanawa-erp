<?php

namespace App\Modules\Organization\Http\Middleware;

use App\Modules\Organization\Models\ExternalApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalApiKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextKey = $request->header('X-API-Key');

        if (! is_string($plainTextKey)) {
            return $this->unauthorized();
        }

        $apiKey = ExternalApiKey::findValidKey($plainTextKey);

        if (! $apiKey) {
            return $this->unauthorized();
        }

        $apiKey->touchLastUsedAt();

        setPermissionsTeamId($apiKey->company_id);

        $request->attributes->set('active_company_id', $apiKey->company_id);
        $request->attributes->set('external_api_key', $apiKey);
        $request->attributes->set('external_source_channel', $apiKey->source_channel);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse([
            'message' => __('Invalid or expired API key.'),
            'data' => null,
        ], 401);
    }
}
