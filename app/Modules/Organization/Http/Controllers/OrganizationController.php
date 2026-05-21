<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organization\Actions\AssignBranchRole;
use App\Modules\Organization\Actions\CreateBranch;
use App\Modules\Organization\Actions\CreateCompany;
use App\Modules\Organization\Actions\CreateMembership;
use App\Modules\Organization\Actions\CreateRole;
use App\Modules\Organization\Actions\DeleteRole;
use App\Modules\Organization\Actions\GetOrganizationContext;
use App\Modules\Organization\Actions\ListBranchAssignments;
use App\Modules\Organization\Actions\ListBranches;
use App\Modules\Organization\Actions\ListMemberships;
use App\Modules\Organization\Actions\ListModuleEntitlements;
use App\Modules\Organization\Actions\ListRoles;
use App\Modules\Organization\Actions\ListUserCompanies;
use App\Modules\Organization\Actions\RevokeBranchRole;
use App\Modules\Organization\Actions\UpdateModuleEntitlements;
use App\Modules\Organization\Actions\UpdateRole;
use App\Modules\Organization\Http\Requests\AssignBranchRoleRequest;
use App\Modules\Organization\Http\Requests\CreateBranchRequest;
use App\Modules\Organization\Http\Requests\CreateCompanyRequest;
use App\Modules\Organization\Http\Requests\CreateMembershipRequest;
use App\Modules\Organization\Http\Requests\CreateRoleRequest;
use App\Modules\Organization\Http\Requests\ListBranchesRequest;
use App\Modules\Organization\Http\Requests\ListCompaniesRequest;
use App\Modules\Organization\Http\Requests\ListMembershipsRequest;
use App\Modules\Organization\Http\Requests\ListModuleEntitlementsRequest;
use App\Modules\Organization\Http\Requests\ManageBranchRolesRequest;
use App\Modules\Organization\Http\Requests\ManageRolesRequest;
use App\Modules\Organization\Http\Requests\ShowOrganizationContextRequest;
use App\Modules\Organization\Http\Requests\UpdateModuleEntitlementsRequest;
use App\Modules\Organization\Http\Requests\UpdateRoleRequest;
use App\Modules\Organization\Http\Resources\BranchAssignmentResource;
use App\Modules\Organization\Http\Resources\BranchResource;
use App\Modules\Organization\Http\Resources\CompanyMembershipResource;
use App\Modules\Organization\Http\Resources\CompanyResource;
use App\Modules\Organization\Http\Resources\MembershipResource;
use App\Modules\Organization\Http\Resources\ModuleEntitlementResource;
use App\Modules\Organization\Http\Resources\RoleResource;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Support\BuiltinRoles;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

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
                'branch_assignments' => BranchAssignmentResource::collection($result['branch_assignments']),
                'active_branch_id' => $request->attributes->get('active_branch_id'),
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

    public function permissions(): JsonResponse
    {
        return $this->success(
            ['permissions' => PermissionCatalog::grouped()],
            __('Permission catalog retrieved.'),
        );
    }

    public function roles(ManageRolesRequest $request, ListRoles $action, Company $company): JsonResponse
    {
        $paginator = $action->execute($company);

        return $this->success(
            [
                'roles' => RoleResource::collection($paginator->getCollection()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            __('Roles retrieved.'),
        );
    }

    public function storeRole(CreateRoleRequest $request, CreateRole $action, Company $company): JsonResponse
    {
        $role = $action->execute($company, $request->validated());

        return $this->success(
            ['role' => new RoleResource($role)],
            __('Role created.'),
            201,
        );
    }

    public function showRole(ManageRolesRequest $request, Company $company, Role $role): JsonResponse
    {
        abort_unless($role->team_id === $company->id, 404);

        return $this->success(
            ['role' => new RoleResource($role->load('permissions'))],
            __('Role retrieved.'),
        );
    }

    public function updateRole(UpdateRoleRequest $request, UpdateRole $action, Company $company, Role $role): JsonResponse
    {
        abort_unless($role->team_id === $company->id, 404);

        if (BuiltinRoles::isBuiltin($role->name)) {
            return $this->error(__('Built-in roles cannot be modified.'), 403);
        }

        $updated = $action->execute($company, $role, $request->validated());

        return $this->success(
            ['role' => new RoleResource($updated)],
            __('Role updated.'),
        );
    }

    public function destroyRole(ManageRolesRequest $request, DeleteRole $action, Company $company, Role $role): JsonResponse
    {
        abort_unless($role->team_id === $company->id, 404);

        if (BuiltinRoles::isBuiltin($role->name)) {
            return $this->error(__('Built-in roles cannot be deleted.'), 403);
        }

        $action->execute($role);

        return $this->success(null, __('Role deleted.'));
    }

    public function branchAssignments(
        ManageBranchRolesRequest $request,
        ListBranchAssignments $action,
        Company $company,
        Branch $branch,
    ): JsonResponse {
        abort_unless($branch->company_id === $company->id, 404);

        $paginator = $action->execute($branch);

        return $this->success(
            [
                'assignments' => BranchAssignmentResource::collection($paginator->getCollection()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            __('Branch assignments retrieved.'),
        );
    }

    public function storeBranchAssignment(
        AssignBranchRoleRequest $request,
        AssignBranchRole $action,
        Company $company,
        Branch $branch,
    ): JsonResponse {
        abort_unless($branch->company_id === $company->id, 404);

        $assignment = $action->execute($company, $branch, $request->user(), $request->validated());

        return $this->success(
            ['assignment' => new BranchAssignmentResource($assignment)],
            __('Branch role assigned.'),
            201,
        );
    }

    public function destroyBranchAssignment(
        ManageBranchRolesRequest $request,
        RevokeBranchRole $action,
        Company $company,
        Branch $branch,
        User $user,
    ): JsonResponse {
        abort_unless($branch->company_id === $company->id, 404);

        $action->execute($branch, $user->id);

        return $this->success(null, __('Branch role revoked.'));
    }
}
