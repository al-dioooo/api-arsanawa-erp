<?php

namespace App\Modules\Finance\Services;

use App\Models\User;
use App\Modules\Finance\Models\ApprovalMatrix;
use App\Modules\Finance\Models\ApprovalRequest;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    /**
     * Check if a document type and amount band in the company requires approval.
     */
    public function requires(string $documentType, string|float|int $amount, int $companyId): bool
    {
        return ApprovalMatrix::query()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->where('min_amount', '<=', (string) $amount)
            ->where('max_amount', '>=', (string) $amount)
            ->exists();
    }

    /**
     * Retrieve matching approval matrix rules ordered by level ascending.
     *
     * @return Collection<int, ApprovalMatrix>
     */
    public function getRequiredRules(string $documentType, string|float|int $amount, int $companyId): Collection
    {
        return ApprovalMatrix::query()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->where('min_amount', '<=', (string) $amount)
            ->where('max_amount', '>=', (string) $amount)
            ->orderBy('level', 'asc')
            ->get();
    }

    /**
     * Submit a bill or payment for approval.
     *
     * @throws ValidationException
     */
    public function submit(Model $document, User $user): ApprovalRequest
    {
        $documentType = $document instanceof Bill ? 'bill' : ($document instanceof Payment ? 'payment' : null);
        if (! $documentType) {
            throw new \InvalidArgumentException('Unsupported document type for approval.');
        }

        if ($document->status !== 'draft') {
            throw ValidationException::withMessages([
                'document' => [__('Only draft documents can be submitted for approval.')],
            ]);
        }

        $amount = $document instanceof Bill ? $document->total : ($document instanceof Payment ? $document->amount : 0);

        if (! $this->requires($documentType, $amount, $document->company_id)) {
            throw ValidationException::withMessages([
                'approval' => [__('This document does not match any approval rules and cannot be submitted.')],
            ]);
        }

        // Delete any existing approval request
        ApprovalRequest::query()
            ->where('approvable_type', $document->getMorphClass())
            ->where('approvable_id', $document->getKey())
            ->delete();

        return ApprovalRequest::create([
            'company_id' => $document->company_id,
            'approvable_type' => $document->getMorphClass(),
            'approvable_id' => $document->getKey(),
            'current_level' => 1,
            'status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Act on a pending approval request.
     *
     * @throws ValidationException
     */
    public function act(ApprovalRequest $request, User $user, string $action, ?string $remark = null): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'approval_request' => [__('This approval request is not pending.')],
            ]);
        }

        if (! in_array($action, ['approved', 'rejected'])) {
            throw new \InvalidArgumentException('Action must be approved or rejected.');
        }

        $document = $request->approvable;
        if (! $document) {
            throw new \InvalidArgumentException('Associated document not found.');
        }

        $documentType = $document instanceof Bill ? 'bill' : ($document instanceof Payment ? 'payment' : null);
        $amount = $document instanceof Bill ? $document->total : ($document instanceof Payment ? $document->amount : 0);

        $rules = $this->getRequiredRules($documentType, $amount, $request->company_id);
        $currentRule = $rules->firstWhere('level', $request->current_level);

        if (! $currentRule) {
            throw ValidationException::withMessages([
                'approval_request' => [__('No active approval rule matches the current level.')],
            ]);
        }

        if ($user->id !== $currentRule->approver_user_id) {
            throw ValidationException::withMessages([
                'approval_request' => [__('You are not the designated approver for the current level.')],
            ]);
        }

        // Record the action
        $request->actions()->create([
            'level' => $request->current_level,
            'user_id' => $user->id,
            'action' => $action,
            'remark' => $remark,
            'acted_at' => now(),
        ]);

        if ($action === 'rejected') {
            $request->update([
                'status' => 'rejected',
                'updated_by' => $user->id,
            ]);
        } else {
            // Find next level
            $nextRule = $rules->first(fn ($r) => $r->level > $request->current_level);

            if ($nextRule) {
                $request->update([
                    'current_level' => $nextRule->level,
                    'updated_by' => $user->id,
                ]);
            } else {
                $request->update([
                    'status' => 'approved',
                    'updated_by' => $user->id,
                ]);
            }
        }

        return $request;
    }

    /**
     * Check if a document is approved (or doesn't require approval).
     */
    public function isApproved(Model $document): bool
    {
        $documentType = $document instanceof Bill ? 'bill' : ($document instanceof Payment ? 'payment' : null);
        if (! $documentType) {
            return true;
        }

        $amount = $document instanceof Bill ? $document->total : ($document instanceof Payment ? $document->amount : 0);

        if (! $this->requires($documentType, $amount, $document->company_id)) {
            return true;
        }

        $request = ApprovalRequest::query()
            ->where('approvable_type', $document->getMorphClass())
            ->where('approvable_id', $document->getKey())
            ->first();

        return $request && $request->status === 'approved';
    }

    /**
     * Reset approval request when a document is modified.
     */
    public function reset(Model $document): void
    {
        ApprovalRequest::query()
            ->where('approvable_type', $document->getMorphClass())
            ->where('approvable_id', $document->getKey())
            ->delete();
    }
}
