<?php

namespace App\Modules\Finance\Actions;

use App\Models\User;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\TaxReturn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateTaxReturn
{
    /**
     * Generate a draft tax return.
     *
     * @param  array{tax_type: string, period_start: string, period_end: string}  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): TaxReturn
    {
        if ($data['period_start'] > $data['period_end']) {
            throw ValidationException::withMessages([
                'period_start' => [__('The start date must be before or equal to the end date.')],
            ]);
        }

        $periodStart = Carbon::parse($data['period_start']);
        $periodEnd = Carbon::parse($data['period_end']);

        // Check if an identical tax return already exists
        $exists = TaxReturn::query()
            ->where('company_id', $companyId)
            ->where('tax_type', $data['tax_type'])
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period_start' => [__('A tax return already exists for this period.')],
            ]);
        }

        return DB::transaction(function () use ($companyId, $user, $data): TaxReturn {
            $totalOutput = '0.0000';
            $totalInput = '0.0000';
            $totalPayable = '0.0000';
            $linesToCreate = [];

            if ($data['tax_type'] === 'vat') {
                foreach ($this->unfiledSources(Invoice::class, $companyId, $data, 'invoice_date', 'tax_total') as $invoice) {
                    $totalOutput = bcadd($totalOutput, (string) $invoice->tax_total, 4);
                    $linesToCreate[] = [
                        'source_type' => (new Invoice)->getMorphClass(),
                        'source_id' => $invoice->id,
                        'tax_amount' => $invoice->tax_total,
                    ];
                }

                foreach ($this->unfiledSources(Bill::class, $companyId, $data, 'bill_date', 'tax_total') as $bill) {
                    $totalInput = bcadd($totalInput, (string) $bill->tax_total, 4);
                    $linesToCreate[] = [
                        'source_type' => (new Bill)->getMorphClass(),
                        'source_id' => $bill->id,
                        'tax_amount' => $bill->tax_total,
                    ];
                }

                $totalPayable = bcsub($totalOutput, $totalInput, 4);

            } elseif ($data['tax_type'] === 'withholding') {
                foreach ($this->unfiledSources(Bill::class, $companyId, $data, 'bill_date', 'withholding_total') as $bill) {
                    $totalPayable = bcadd($totalPayable, (string) $bill->withholding_total, 4);
                    $linesToCreate[] = [
                        'source_type' => (new Bill)->getMorphClass(),
                        'source_id' => $bill->id,
                        'tax_amount' => $bill->withholding_total,
                    ];
                }
            } else {
                throw ValidationException::withMessages([
                    'tax_type' => [__('Invalid tax type.')],
                ]);
            }

            $taxReturn = TaxReturn::create([
                'company_id' => $companyId,
                'tax_type' => $data['tax_type'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => 'draft',
                'total_output' => $totalOutput,
                'total_input' => $totalInput,
                'total_payable' => $totalPayable,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($linesToCreate !== []) {
                $now = now();
                $taxReturn->lines()->insert(array_map(
                    fn (array $line): array => $line + [
                        'tax_return_id' => $taxReturn->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $linesToCreate,
                ));
            }

            return $taxReturn;
        });
    }

    /**
     * Postable documents in the period carrying the given tax amount that are
     * not yet on a finalized return, with only the columns the return needs.
     *
     * @param  class-string<Invoice|Bill>  $modelClass
     * @param  array{tax_type: string, period_start: string, period_end: string}  $data
     * @return Collection<int, Invoice|Bill>
     */
    private function unfiledSources(string $modelClass, int $companyId, array $data, string $dateColumn, string $amountColumn)
    {
        $model = new $modelClass;

        return $modelClass::query()
            ->forCompany($companyId)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween($dateColumn, [$data['period_start'], $data['period_end']])
            ->where($amountColumn, '>', 0)
            ->whereNotExists(function ($query) use ($companyId, $model): void {
                $query->select(DB::raw(1))
                    ->from('tax_return_lines')
                    ->join('tax_returns', 'tax_return_lines.tax_return_id', '=', 'tax_returns.id')
                    ->where('tax_returns.company_id', $companyId)
                    ->where('tax_returns.status', 'finalized')
                    ->where('tax_return_lines.source_type', $model->getMorphClass())
                    ->whereColumn('tax_return_lines.source_id', $model->getTable().'.id');
            })
            ->get(['id', $amountColumn]);
    }
}
