<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Actions\CreateBranch;
use App\Modules\Organization\Actions\CreateCompany;
use App\Modules\Organization\Actions\CreateMembership;
use App\Modules\Organization\Actions\GetOrganizationContext;
use App\Modules\Organization\Actions\ListBranches;
use App\Modules\Organization\Actions\ListMemberships;
use App\Modules\Organization\Actions\ListModuleEntitlements;
use App\Modules\Organization\Actions\ListUserCompanies;
use App\Modules\Organization\Actions\UpdateModuleEntitlements;
use App\Modules\Organization\Http\Requests\CreateBranchRequest;
use App\Modules\Organization\Http\Requests\CreateCompanyRequest;
use App\Modules\Organization\Http\Requests\CreateMembershipRequest;
use App\Modules\Organization\Http\Requests\ListBranchesRequest;
use App\Modules\Organization\Http\Requests\ListCompaniesRequest;
use App\Modules\Organization\Http\Requests\ListMembershipsRequest;
use App\Modules\Organization\Http\Requests\ListModuleEntitlementsRequest;
use App\Modules\Organization\Http\Requests\ShowOrganizationContextRequest;
use App\Modules\Organization\Http\Requests\UpdateModuleEntitlementsRequest;
use App\Modules\Organization\Http\Resources\BranchResource;
use App\Modules\Organization\Http\Resources\CompanyMembershipResource;
use App\Modules\Organization\Http\Resources\CompanyResource;
use App\Modules\Organization\Http\Resources\MembershipResource;
use App\Modules\Organization\Http\Resources\ModuleEntitlementResource;
use App\Modules\Organization\Models\Company;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function index(ListCompaniesRequest $request, ListUserCompanies $action): JsonResponse
    {
        $memberships = $action->execute($request->user());

        return $this->success(
            ['companies' => CompanyMembershipResource::collection($memberships)],
            __('Companies retrieved.'),
        );
    }

    public function store(CreateCompanyRequest $request, CreateCompany $action): JsonResponse
    {
        $result = $action->execute($request->user(), $request->validated());

        return $this->success(
            [
                'company' => new CompanyResource($result['company']),
                'primary_branch' => new BranchResource($result['primaryBranch']),
                'membership' => new MembershipResource($result['membership']),
            ],
            __('Company created.'),
            201,
        );
    }

    public function context(ShowOrganizationContextRequest $request, GetOrganizationContext $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            $request->attributes->get('active_company_id'),
        );

        return $this->success(
            [
                'company' => $result['company'] ? new CompanyResource($result['company']) : null,
                'membership' => $result['membership'] ? new MembershipResource($result['membership']) : null,
                'branches' => BranchResource::collection($result['branches']),
            ],
            __('Organization context retrieved.'),
        );
    }

    public function branches(ListBranchesRequest $request, ListBranches $action, Company $company): JsonResponse
    {
        $branches = $action->execute($company);

        return $this->success(
            ['branches' => BranchResource::collection($branches)],
            __('Branches retrieved.'),
        );
    }

    public function storeBranch(CreateBranchRequest $request, CreateBranch $action, Company $company): JsonResponse
    {
        $branch = $action->execute($company, $request->user(), $request->validated());

        return $this->success(
            ['branch' => new BranchResource($branch)],
            __('Branch created.'),
            201,
        );
    }

    public function memberships(ListMembershipsRequest $request, ListMemberships $action, Company $company): JsonResponse
    {
        $memberships = $action->execute($company);

        return $this->success(
            ['memberships' => MembershipResource::collection($memberships)],
            __('Memberships retrieved.'),
        );
    }

    public function storeMembership(CreateMembershipRequest $request, CreateMembership $action, Company $company): JsonResponse
    {
        $membership = $action->execute($company, $request->user(), $request->validated());

        return $this->success(
            ['membership' => new MembershipResource($membership)],
            __('Membership created.'),
            201,
        );
    }

    public function entitlements(
        ListModuleEntitlementsRequest $request,
        ListModuleEntitlements $action,
        Company $company,
    ): JsonResponse {
        $entitlements = $action->execute($company);

        return $this->success(
            ['entitlements' => ModuleEntitlementResource::collection($entitlements)],
            __('Module entitlements retrieved.'),
        );
    }

    public function updateEntitlements(
        UpdateModuleEntitlementsRequest $request,
        UpdateModuleEntitlements $action,
        Company $company,
    ): JsonResponse {
        $entitlements = $action->execute($company, $request->user(), $request->validated());

        return $this->success(
            ['entitlements' => ModuleEntitlementResource::collection($entitlements)],
            __('Module entitlements updated.'),
        );
    }
}
