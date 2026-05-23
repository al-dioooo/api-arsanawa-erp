<?php

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

describe('Finance accounting periods', function () {
    it('creates a period and lists it', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.period.name', 'January 2026')
            ->assertJsonPath('data.period.status', 'open');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->getJson('/api/v1/finance/periods')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data.periods');
    });

    it('closes a period', function (): void {
        [, $token, $companyId] = financeActor();

        $periodId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])
            ->json('data.period.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/periods/{$periodId}/close")
            ->assertSuccessful()
            ->assertJsonPath('data.period.status', 'closed');
    });

    it('reopens a closed period', function (): void {
        [, $token, $companyId] = financeActor();

        $periodId = $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])
            ->json('data.period.id');

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/periods/{$periodId}/close");

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson("/api/v1/finance/periods/{$periodId}/reopen")
            ->assertSuccessful()
            ->assertJsonPath('data.period.status', 'open');
    });

    it('rejects overlapping periods', function (): void {
        [, $token, $companyId] = financeActor();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'January 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])
            ->assertCreated();

        $this->withToken($token)->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'Mid-January 2026',
                'start_date' => '2026-01-15',
                'end_date' => '2026-02-15',
            ])
            ->assertUnprocessable();
    });

    it('rejects period creation without permission', function (): void {
        $user = User::factory()->create();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/finance/periods', [
                'name' => 'Test Period',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])
            ->assertForbidden();
    });
});
