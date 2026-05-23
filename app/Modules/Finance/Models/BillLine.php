<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLine extends Model
{
    protected $fillable = [
        'bill_id',
        'description',
        'product_variant_id',
        'expense_account_id',
        'quantity',
        'unit_price',
        'discount',
        'line_subtotal',
        'tax_amount',
        'withholding_amount',
        'line_total',
        'tax_rate_id',
    ];

    protected function casts(): array
    {
        return [
            'bill_id' => 'integer',
            'product_variant_id' => 'integer',
            'expense_account_id' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'withholding_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'tax_rate_id' => 'integer',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }
}
