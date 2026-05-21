<?php

use App\Models\User;
use App\Modules\Organization\Models\Membership;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

// ---------------------------------------------------------------------------
// Helper: bootstrap an owner with a company and full permissions
// ---------------------------------------------------------------------------
function bootstrapOwnerWithCompany(string $slug = 'test-company'): array
{
    $owner = User::factory()->create();

    $ownerToken = test()->postJson('/api/v1/auth/login', [
        'login' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $companyId = test()->withToken($ownerToken)
        ->postJson('/api/v1/organization/companies', [
            'name' => 'Test Company',
            'slug' => $slug,
        ])
        ->json('data.company.id');

    return [$owner, $ownerToken, $companyId];
}

// ---------------------------------------------------------------------------
// Helper: create a member without permissions and get their token
// ---------------------------------------------------------------------------
function bootstrapMember(int $companyId): array
{
    $member = User::factory()->create();

    Membership::create([
        'company_id' => $companyId,
        'user_id' => $member->id,
        'role' => 'member',
        'status' => 'active',
    ]);

    $memberToken = test()->postJson('/api/v1/auth/login', [
        'login' => $member->email,
        'password' => 'password',
    ])->json('data.access_token');

    return [$member, $memberToken];
}

// ---------------------------------------------------------------------------
// POST /api/v1/partners — Create partner
// ---------------------------------------------------------------------------
describe('POST /api/v1/partners', function () {
    it('creates a partner with inline contacts and addresses', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('create-partner-co');

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'type' => 'customer',
                'name' => 'PT Maju Jaya',
                'code' => 'MJY-001',
                'email' => 'info@majujaya.co.id',
                'phone' => '+6281234567890',
                'tax_identifier' => '01.234.567.8-999.000',
                'national_id' => '3201234567890001',
                'credit_limit' => 50000000,
                'transaction_limit' => 10000000,
                'notes' => 'Premium customer',
                'contacts' => [
                    [
                        'name' => 'Budi Santoso',
                        'role' => 'Finance Manager',
                        'email' => 'budi@majujaya.co.id',
                        'phone' => '+6281234567891',
                        'is_primary' => true,
                    ],
                    [
                        'name' => 'Siti Rahayu',
                        'role' => 'Purchasing',
                        'email' => 'siti@majujaya.co.id',
                        'phone' => '+6281234567892',
                        'is_primary' => false,
                    ],
                ],
                'addresses' => [
                    [
                        'type' => 'billing',
                        'label' => 'Head Office',
                        'address_line_1' => 'Jl. Sudirman No. 10',
                        'address_line_2' => 'Gedung Graha Lt. 5',
                        'city' => 'Jakarta',
                        'province' => 'DKI Jakarta',
                        'postal_code' => '10110',
                        'country' => 'ID',
                        'is_default' => true,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'partner' => [
                        'id',
                        'company_id',
                        'type',
                        'name',
                        'code',
                        'email',
                        'phone',
                        'tax_identifier',
                        'national_id',
                        'credit_limit',
                        'transaction_limit',
                        'status',
                        'notes',
                        'contacts' => [
                            '*' => ['id', 'partner_id', 'name', 'role', 'email', 'phone', 'is_primary'],
                        ],
                        'addresses' => [
                            '*' => ['id', 'partner_id', 'type', 'label', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country', 'is_default'],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.partner.name', 'PT Maju Jaya')
            ->assertJsonPath('data.partner.type', 'customer')
            ->assertJsonPath('data.partner.company_id', $companyId);

        $partnerId = $response->json('data.partner.id');

        $this->assertDatabaseHas('partners', [
            'id' => $partnerId,
            'company_id' => $companyId,
            'type' => 'customer',
            'name' => 'PT Maju Jaya',
            'code' => 'MJY-001',
        ]);

        expect($response->json('data.partner.contacts'))->toHaveCount(2);
        expect($response->json('data.partner.addresses'))->toHaveCount(1);

        $this->assertDatabaseHas('partner_contacts', [
            'partner_id' => $partnerId,
            'name' => 'Budi Santoso',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('partner_addresses', [
            'partner_id' => $partnerId,
            'type' => 'billing',
            'city' => 'Jakarta',
            'is_default' => true,
        ]);
    });

    it('rejects a partner without a name', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('no-name-co');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'type' => 'customer',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('rejects a partner with invalid type', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('invalid-type-co');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Invalid Type Partner',
                'type' => 'distributor',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('rejects duplicate code within the same company', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('dup-code-co');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'First Partner',
                'type' => 'customer',
                'code' => 'DUP-001',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Second Partner',
                'type' => 'supplier',
                'code' => 'DUP-001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('returns 403 without partners.create permission', function () {
        [$owner, $ownerToken, $companyId] = bootstrapOwnerWithCompany('no-create-perm-co');
        [$member, $memberToken] = bootstrapMember($companyId);

        $this->withToken($memberToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Unauthorized Partner',
                'type' => 'customer',
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// GET /api/v1/partners — List partners
// ---------------------------------------------------------------------------
describe('GET /api/v1/partners', function () {
    it('lists partners for the active company', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('list-partners-co');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Partner Alpha',
                'type' => 'customer',
                'code' => 'ALPHA',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Partner Beta',
                'type' => 'supplier',
                'code' => 'BETA',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/partners')
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'partners',
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ])
            ->assertJsonPath('data.pagination.total', 2);
    });

    it('filters partners by type', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('filter-type-co');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Customer One',
                'type' => 'customer',
                'code' => 'CUST-1',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Supplier One',
                'type' => 'supplier',
                'code' => 'SUPP-1',
            ])
            ->assertCreated();

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/partners?type=customer')
            ->assertSuccessful()
            ->assertJsonPath('data.pagination.total', 1);

        expect($response->json('data.partners.0.type'))->toBe('customer');
    });

    it('searches partners by name', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('search-name-co');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'PT Sumber Makmur',
                'type' => 'customer',
                'code' => 'SM-001',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'CV Karya Utama',
                'type' => 'supplier',
                'code' => 'KU-001',
            ])
            ->assertCreated();

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/partners?search=Sumber')
            ->assertSuccessful()
            ->assertJsonPath('data.pagination.total', 1);

        expect($response->json('data.partners.0.name'))->toBe('PT Sumber Makmur');
    });

    it('only shows partners for the active company', function () {
        [$ownerA, $tokenA, $companyA] = bootstrapOwnerWithCompany('isolation-co-a');
        [$ownerB, $tokenB, $companyB] = bootstrapOwnerWithCompany('isolation-co-b');

        $this->withToken($tokenA)
            ->withHeader('X-Company-Id', (string) $companyA)
            ->postJson('/api/v1/partners', [
                'name' => 'Company A Partner',
                'type' => 'customer',
                'code' => 'A-001',
            ])
            ->assertCreated();

        $this->withToken($tokenB)
            ->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/partners', [
                'name' => 'Company B Partner',
                'type' => 'supplier',
                'code' => 'B-001',
            ])
            ->assertCreated();

        $responseA = $this->withToken($tokenA)
            ->withHeader('X-Company-Id', (string) $companyA)
            ->getJson('/api/v1/partners')
            ->assertSuccessful()
            ->assertJsonPath('data.pagination.total', 1);

        expect($responseA->json('data.partners.0.name'))->toBe('Company A Partner');

        $responseB = $this->withToken($tokenB)
            ->withHeader('X-Company-Id', (string) $companyB)
            ->getJson('/api/v1/partners')
            ->assertSuccessful()
            ->assertJsonPath('data.pagination.total', 1);

        expect($responseB->json('data.partners.0.name'))->toBe('Company B Partner');
    });

    it('returns 403 without partners.view permission', function () {
        [$owner, $ownerToken, $companyId] = bootstrapOwnerWithCompany('no-view-perm-co');
        [$member, $memberToken] = bootstrapMember($companyId);

        $this->withToken($memberToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/partners')
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// GET /api/v1/partners/{partner} — Show partner
// ---------------------------------------------------------------------------
describe('GET /api/v1/partners/{partner}', function () {
    it('shows a partner with contacts and addresses', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('show-partner-co');

        $partnerId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'PT Detail Partner',
                'type' => 'both',
                'code' => 'DTL-001',
                'email' => 'detail@partner.co.id',
                'contacts' => [
                    [
                        'name' => 'Primary Contact',
                        'role' => 'Director',
                        'email' => 'director@partner.co.id',
                        'phone' => '+6281000000001',
                        'is_primary' => true,
                    ],
                ],
                'addresses' => [
                    [
                        'type' => 'shipping',
                        'label' => 'Warehouse',
                        'address_line_1' => 'Jl. Industri No. 5',
                        'city' => 'Surabaya',
                        'province' => 'Jawa Timur',
                        'postal_code' => '60111',
                        'country' => 'ID',
                        'is_default' => true,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data.partner.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->getJson("/api/v1/partners/{$partnerId}")
            ->assertSuccessful()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'partner' => [
                        'id',
                        'company_id',
                        'type',
                        'name',
                        'code',
                        'email',
                        'phone',
                        'tax_identifier',
                        'national_id',
                        'credit_limit',
                        'transaction_limit',
                        'status',
                        'notes',
                        'contacts' => [
                            '*' => ['id', 'partner_id', 'name', 'role', 'email', 'phone', 'is_primary'],
                        ],
                        'addresses' => [
                            '*' => ['id', 'partner_id', 'type', 'label', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country', 'is_default'],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.partner.id', $partnerId)
            ->assertJsonPath('data.partner.name', 'PT Detail Partner')
            ->assertJsonPath('data.partner.type', 'both');

    });

    it('returns 404 for a partner in another company', function () {
        [$ownerA, $tokenA, $companyA] = bootstrapOwnerWithCompany('cross-co-a');
        [$ownerB, $tokenB, $companyB] = bootstrapOwnerWithCompany('cross-co-b');

        $partnerInB = $this->withToken($tokenB)
            ->withHeader('X-Company-Id', (string) $companyB)
            ->postJson('/api/v1/partners', [
                'name' => 'Company B Only Partner',
                'type' => 'customer',
                'code' => 'B-ONLY',
            ])
            ->assertCreated()
            ->json('data.partner.id');

        $this->withToken($tokenA)
            ->withHeader('X-Company-Id', (string) $companyA)
            ->getJson("/api/v1/partners/{$partnerInB}")
            ->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// PATCH /api/v1/partners/{partner} — Update partner
// ---------------------------------------------------------------------------
describe('PATCH /api/v1/partners/{partner}', function () {
    it('updates a partner', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('update-partner-co');

        $partnerId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Original Name',
                'type' => 'customer',
                'code' => 'ORIG-001',
                'notes' => 'Original notes',
            ])
            ->assertCreated()
            ->json('data.partner.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/partners/{$partnerId}", [
                'name' => 'Updated Name',
                'notes' => 'Updated notes',
                'credit_limit' => 75000000,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.partner.name', 'Updated Name')
            ->assertJsonPath('data.partner.notes', 'Updated notes')
            ->assertJsonPath('data.partner.credit_limit', '75000000.00');

        $this->assertDatabaseHas('partners', [
            'id' => $partnerId,
            'name' => 'Updated Name',
            'notes' => 'Updated notes',
        ]);
    });

    it('returns 403 without partners.update permission', function () {
        [$owner, $ownerToken, $companyId] = bootstrapOwnerWithCompany('no-update-perm-co');
        [$member, $memberToken] = bootstrapMember($companyId);

        $partnerId = $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Update Target',
                'type' => 'customer',
                'code' => 'UPD-TARGET',
            ])
            ->assertCreated()
            ->json('data.partner.id');

        $this->withToken($memberToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/partners/{$partnerId}", [
                'name' => 'Hacked Name',
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// DELETE /api/v1/partners/{partner} — Delete partner
// ---------------------------------------------------------------------------
describe('DELETE /api/v1/partners/{partner}', function () {
    it('deletes a partner and cascades contacts and addresses', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('delete-partner-co');

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Doomed Partner',
                'type' => 'supplier',
                'code' => 'DOOM-001',
                'contacts' => [
                    [
                        'name' => 'Doomed Contact',
                        'role' => 'Staff',
                        'email' => 'doomed@partner.co.id',
                        'phone' => '+6281000000099',
                        'is_primary' => true,
                    ],
                ],
                'addresses' => [
                    [
                        'type' => 'billing',
                        'label' => 'Doomed Office',
                        'address_line_1' => 'Jl. Hapus No. 1',
                        'city' => 'Bandung',
                        'province' => 'Jawa Barat',
                        'postal_code' => '40111',
                        'country' => 'ID',
                        'is_default' => true,
                    ],
                ],
            ])
            ->assertCreated();

        $partnerId = $response->json('data.partner.id');
        $contactId = $response->json('data.partner.contacts.0.id');
        $addressId = $response->json('data.partner.addresses.0.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/partners/{$partnerId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('partners', ['id' => $partnerId]);
        $this->assertDatabaseMissing('partner_contacts', ['id' => $contactId]);
        $this->assertDatabaseMissing('partner_addresses', ['id' => $addressId]);
    });

    it('returns 403 without partners.delete permission', function () {
        [$owner, $ownerToken, $companyId] = bootstrapOwnerWithCompany('no-delete-perm-co');
        [$member, $memberToken] = bootstrapMember($companyId);

        $partnerId = $this->withToken($ownerToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Protected Partner',
                'type' => 'customer',
                'code' => 'PROT-001',
            ])
            ->assertCreated()
            ->json('data.partner.id');

        $this->withToken($memberToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/partners/{$partnerId}")
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// POST /api/v1/partners/{partner}/contacts — Add contact
// ---------------------------------------------------------------------------
describe('POST /api/v1/partners/{partner}/contacts', function () {
    it('adds a contact to a partner', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('add-contact-co');

        $partnerId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Contact Host Partner',
                'type' => 'customer',
                'code' => 'CHP-001',
            ])
            ->assertCreated()
            ->json('data.partner.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/partners/{$partnerId}/contacts", [
                'name' => 'New Contact Person',
                'role' => 'Accountant',
                'email' => 'accountant@host.co.id',
                'phone' => '+6281234500001',
                'is_primary' => false,
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'contact' => ['id', 'partner_id', 'name', 'role', 'email', 'phone', 'is_primary'],
                ],
            ])
            ->assertJsonPath('data.contact.name', 'New Contact Person')
            ->assertJsonPath('data.contact.partner_id', $partnerId);

        $this->assertDatabaseHas('partner_contacts', [
            'partner_id' => $partnerId,
            'name' => 'New Contact Person',
            'role' => 'Accountant',
        ]);
    });
});

// ---------------------------------------------------------------------------
// PATCH /api/v1/partners/{partner}/contacts/{contact} — Update contact
// ---------------------------------------------------------------------------
describe('PATCH /api/v1/partners/{partner}/contacts/{contact}', function () {
    it('updates a partner contact', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('update-contact-co');

        $partnerResponse = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Contact Update Partner',
                'type' => 'supplier',
                'code' => 'CUP-001',
                'contacts' => [
                    [
                        'name' => 'Old Contact Name',
                        'role' => 'Manager',
                        'email' => 'old@contact.co.id',
                        'phone' => '+6281234500002',
                        'is_primary' => true,
                    ],
                ],
            ])
            ->assertCreated();

        $partnerId = $partnerResponse->json('data.partner.id');
        $contactId = $partnerResponse->json('data.partner.contacts.0.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/partners/{$partnerId}/contacts/{$contactId}", [
                'name' => 'Updated Contact Name',
                'role' => 'Senior Manager',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.contact.name', 'Updated Contact Name')
            ->assertJsonPath('data.contact.role', 'Senior Manager');

        $this->assertDatabaseHas('partner_contacts', [
            'id' => $contactId,
            'name' => 'Updated Contact Name',
            'role' => 'Senior Manager',
        ]);
    });
});

// ---------------------------------------------------------------------------
// DELETE /api/v1/partners/{partner}/contacts/{contact} — Delete contact
// ---------------------------------------------------------------------------
describe('DELETE /api/v1/partners/{partner}/contacts/{contact}', function () {
    it('deletes a partner contact', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('delete-contact-co');

        $partnerResponse = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Contact Delete Partner',
                'type' => 'customer',
                'code' => 'CDP-001',
                'contacts' => [
                    [
                        'name' => 'Expendable Contact',
                        'role' => 'Intern',
                        'email' => 'intern@partner.co.id',
                        'phone' => '+6281234500003',
                        'is_primary' => false,
                    ],
                ],
            ])
            ->assertCreated();

        $partnerId = $partnerResponse->json('data.partner.id');
        $contactId = $partnerResponse->json('data.partner.contacts.0.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/partners/{$partnerId}/contacts/{$contactId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('partner_contacts', ['id' => $contactId]);
    });
});

// ---------------------------------------------------------------------------
// POST /api/v1/partners/{partner}/addresses — Add address
// ---------------------------------------------------------------------------
describe('POST /api/v1/partners/{partner}/addresses', function () {
    it('adds an address to a partner', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('add-address-co');

        $partnerId = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Address Host Partner',
                'type' => 'both',
                'code' => 'AHP-001',
            ])
            ->assertCreated()
            ->json('data.partner.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/partners/{$partnerId}/addresses", [
                'type' => 'shipping',
                'label' => 'Distribution Center',
                'address_line_1' => 'Jl. Logistik No. 88',
                'address_line_2' => 'Kawasan Industri MM2100',
                'city' => 'Bekasi',
                'province' => 'Jawa Barat',
                'postal_code' => '17530',
                'country' => 'ID',
                'is_default' => false,
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'address' => ['id', 'partner_id', 'type', 'label', 'address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country', 'is_default'],
                ],
            ])
            ->assertJsonPath('data.address.label', 'Distribution Center')
            ->assertJsonPath('data.address.partner_id', $partnerId);

        $this->assertDatabaseHas('partner_addresses', [
            'partner_id' => $partnerId,
            'label' => 'Distribution Center',
            'city' => 'Bekasi',
        ]);
    });
});

// ---------------------------------------------------------------------------
// PATCH /api/v1/partners/{partner}/addresses/{address} — Update address
// ---------------------------------------------------------------------------
describe('PATCH /api/v1/partners/{partner}/addresses/{address}', function () {
    it('updates a partner address', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('update-address-co');

        $partnerResponse = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Address Update Partner',
                'type' => 'customer',
                'code' => 'AUP-001',
                'addresses' => [
                    [
                        'type' => 'billing',
                        'label' => 'Old Office',
                        'address_line_1' => 'Jl. Lama No. 1',
                        'city' => 'Semarang',
                        'province' => 'Jawa Tengah',
                        'postal_code' => '50111',
                        'country' => 'ID',
                        'is_default' => true,
                    ],
                ],
            ])
            ->assertCreated();

        $partnerId = $partnerResponse->json('data.partner.id');
        $addressId = $partnerResponse->json('data.partner.addresses.0.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->patchJson("/api/v1/partners/{$partnerId}/addresses/{$addressId}", [
                'label' => 'New Office',
                'address_line_1' => 'Jl. Baru No. 99',
                'city' => 'Yogyakarta',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.address.label', 'New Office')
            ->assertJsonPath('data.address.city', 'Yogyakarta');

        $this->assertDatabaseHas('partner_addresses', [
            'id' => $addressId,
            'label' => 'New Office',
            'address_line_1' => 'Jl. Baru No. 99',
            'city' => 'Yogyakarta',
        ]);
    });
});

// ---------------------------------------------------------------------------
// DELETE /api/v1/partners/{partner}/addresses/{address} — Delete address
// ---------------------------------------------------------------------------
describe('DELETE /api/v1/partners/{partner}/addresses/{address}', function () {
    it('deletes a partner address', function () {
        [$owner, $token, $companyId] = bootstrapOwnerWithCompany('delete-address-co');

        $partnerResponse = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/partners', [
                'name' => 'Address Delete Partner',
                'type' => 'supplier',
                'code' => 'ADP-001',
                'addresses' => [
                    [
                        'type' => 'shipping',
                        'label' => 'Temp Warehouse',
                        'address_line_1' => 'Jl. Sementara No. 7',
                        'city' => 'Medan',
                        'province' => 'Sumatera Utara',
                        'postal_code' => '20111',
                        'country' => 'ID',
                        'is_default' => false,
                    ],
                ],
            ])
            ->assertCreated();

        $partnerId = $partnerResponse->json('data.partner.id');
        $addressId = $partnerResponse->json('data.partner.addresses.0.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/partners/{$partnerId}/addresses/{$addressId}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('partner_addresses', ['id' => $addressId]);
    });
});
