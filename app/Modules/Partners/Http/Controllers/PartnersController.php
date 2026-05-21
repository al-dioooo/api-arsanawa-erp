<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Partners\Actions\CreatePartner;
use App\Modules\Partners\Actions\DeletePartner;
use App\Modules\Partners\Actions\GetPartner;
use App\Modules\Partners\Actions\ListPartners;
use App\Modules\Partners\Actions\ManagePartnerAddresses;
use App\Modules\Partners\Actions\ManagePartnerContacts;
use App\Modules\Partners\Actions\UpdatePartner;
use App\Modules\Partners\Http\Requests\DeletePartnerRequest;
use App\Modules\Partners\Http\Requests\ListPartnersRequest;
use App\Modules\Partners\Http\Requests\ShowPartnerRequest;
use App\Modules\Partners\Http\Requests\StorePartnerAddressRequest;
use App\Modules\Partners\Http\Requests\StorePartnerContactRequest;
use App\Modules\Partners\Http\Requests\StorePartnerRequest;
use App\Modules\Partners\Http\Requests\UpdatePartnerAddressRequest;
use App\Modules\Partners\Http\Requests\UpdatePartnerContactRequest;
use App\Modules\Partners\Http\Requests\UpdatePartnerRequest;
use App\Modules\Partners\Http\Resources\PartnerAddressResource;
use App\Modules\Partners\Http\Resources\PartnerContactResource;
use App\Modules\Partners\Http\Resources\PartnerResource;
use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerAddress;
use App\Modules\Partners\Models\PartnerContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnersController extends Controller
{
    public function index(ListPartnersRequest $request, ListPartners $action): JsonResponse
    {
        $paginator = $action->execute(
            (int) $request->attributes->get('active_company_id'),
            $request->validated(),
        );

        return $this->success(
            [
                'partners' => PartnerResource::collection($paginator->getCollection()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            __('Partners retrieved.'),
        );
    }

    public function store(StorePartnerRequest $request, CreatePartner $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            (int) $request->attributes->get('active_company_id'),
            $request->validated(),
        );

        return $this->success(
            ['partner' => new PartnerResource($result['partner'])],
            __('Partner created.'),
            201,
        );
    }

    public function show(ShowPartnerRequest $request, GetPartner $action, int $partner): JsonResponse
    {
        $result = $action->execute(
            $partner,
            (int) $request->attributes->get('active_company_id'),
        );

        return $this->success(
            ['partner' => new PartnerResource($result['partner'])],
            __('Partner retrieved.'),
        );
    }

    public function update(UpdatePartnerRequest $request, UpdatePartner $action, int $partner): JsonResponse
    {
        $resolved = $this->resolvePartner($request, $partner);

        $result = $action->execute($request->user(), $resolved, $request->validated());

        return $this->success(
            ['partner' => new PartnerResource($result['partner'])],
            __('Partner updated.'),
        );
    }

    public function destroy(DeletePartnerRequest $request, DeletePartner $action, int $partner): JsonResponse
    {
        $resolved = $this->resolvePartner($request, $partner);

        $action->execute($resolved);

        return $this->success(null, __('Partner deleted.'));
    }

    public function storeContact(
        StorePartnerContactRequest $request,
        ManagePartnerContacts $action,
        int $partner,
    ): JsonResponse {
        $resolved = $this->resolvePartner($request, $partner);

        $contact = $action->addContact($resolved, $request->validated());

        return $this->success(
            ['contact' => new PartnerContactResource($contact)],
            __('Contact added.'),
            201,
        );
    }

    public function updateContact(
        UpdatePartnerContactRequest $request,
        ManagePartnerContacts $action,
        int $partner,
        int $contact,
    ): JsonResponse {
        $resolved = $this->resolvePartner($request, $partner);
        $resolvedContact = $this->resolveContact($resolved, $contact);

        $updated = $action->updateContact($resolvedContact, $request->validated());

        return $this->success(
            ['contact' => new PartnerContactResource($updated)],
            __('Contact updated.'),
        );
    }

    public function destroyContact(
        UpdatePartnerContactRequest $request,
        ManagePartnerContacts $action,
        int $partner,
        int $contact,
    ): JsonResponse {
        $resolved = $this->resolvePartner($request, $partner);
        $resolvedContact = $this->resolveContact($resolved, $contact);

        $action->deleteContact($resolvedContact);

        return $this->success(null, __('Contact deleted.'));
    }

    public function storeAddress(
        StorePartnerAddressRequest $request,
        ManagePartnerAddresses $action,
        int $partner,
    ): JsonResponse {
        $resolved = $this->resolvePartner($request, $partner);

        $address = $action->addAddress($resolved, $request->validated());

        return $this->success(
            ['address' => new PartnerAddressResource($address)],
            __('Address added.'),
            201,
        );
    }

    public function updateAddress(
        UpdatePartnerAddressRequest $request,
        ManagePartnerAddresses $action,
        int $partner,
        int $address,
    ): JsonResponse {
        $resolved = $this->resolvePartner($request, $partner);
        $resolvedAddress = $this->resolveAddress($resolved, $address);

        $updated = $action->updateAddress($resolvedAddress, $request->validated());

        return $this->success(
            ['address' => new PartnerAddressResource($updated)],
            __('Address updated.'),
        );
    }

    public function destroyAddress(
        UpdatePartnerAddressRequest $request,
        ManagePartnerAddresses $action,
        int $partner,
        int $address,
    ): JsonResponse {
        $resolved = $this->resolvePartner($request, $partner);
        $resolvedAddress = $this->resolveAddress($resolved, $address);

        $action->deleteAddress($resolvedAddress);

        return $this->success(null, __('Address deleted.'));
    }

    private function resolvePartner(Request $request, int $partnerId): Partner
    {
        return Partner::where('id', $partnerId)
            ->where('company_id', $request->attributes->get('active_company_id'))
            ->firstOrFail();
    }

    private function resolveContact(Partner $partner, int $contactId): PartnerContact
    {
        return $partner->contacts()->where('id', $contactId)->firstOrFail();
    }

    private function resolveAddress(Partner $partner, int $addressId): PartnerAddress
    {
        return $partner->addresses()->where('id', $addressId)->firstOrFail();
    }
}
