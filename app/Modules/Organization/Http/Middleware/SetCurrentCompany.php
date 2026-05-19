<?php

namespace App\Modules\Organization\Http\Middleware;

use App\Modules\Organization\Models\Membership;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    /**
     * Set Spatie's active team id from the authenticated user's company context.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            setPermissionsTeamId(null);

            return $next($request);
        }

        $membership = $this->resolveMembership($request);

        if ($request->headers->has('X-Company-Id') && ! $membership) {
            return new JsonResponse([
                'message' => __('Company access denied.'),
                'data' => null,
            ], 403);
        }

        setPermissionsTeamId($membership?->company_id);

        $request->attributes->set('active_company_id', $membership?->company_id);
        $request->attributes->set('active_membership', $membership);

        return $next($request);
    }

    private function resolveMembership(Request $request): ?Membership
    {
        $requestedCompanyId = $request->header('X-Company-Id');

        if ($requestedCompanyId !== null) {
            if (! ctype_digit((string) $requestedCompanyId)) {
                return null;
            }

            return Membership::query()
                ->where('company_id', (int) $requestedCompanyId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->first();
        }

        return Membership::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereHas('company', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->first();
    }
}
