<?php

namespace App\Modules\Organization\Http\Middleware;

use App\Modules\Organization\Models\Branch;
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

        $branch = $this->resolveBranch($request, $membership?->company_id);

        if ($request->headers->has('X-Branch-Id') && ! $branch) {
            return new JsonResponse([
                'message' => __('Branch access denied.'),
                'data' => null,
            ], 403);
        }

        $request->attributes->set('active_branch_id', $branch?->id);

        return $next($request);
    }

    private function resolveBranch(Request $request, ?int $companyId): ?Branch
    {
        $requestedBranchId = $request->header('X-Branch-Id');

        if ($requestedBranchId === null) {
            return null;
        }

        if ($companyId === null || ! ctype_digit((string) $requestedBranchId)) {
            return null;
        }

        return Branch::query()
            ->where('id', (int) $requestedBranchId)
            ->where('company_id', $companyId)
            ->first();
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
