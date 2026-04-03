<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Setting;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_code',
        'reference_number',
        'project_id',
        'timesheet_group_id',
        'issued_at',
        'due_date',
        'total_amount',
        'discount_amount',
        'deduction_amount',
        'file_url',
        'evidence_url',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function timesheetGroup()
    {
        return $this->belongsTo(TimesheetGroup::class, 'timesheet_group_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collections()
    {
        return $this->hasMany(Collection::class);
    }

    public function deductions()
    {
        return $this->hasMany(InvoiceDeduction::class);
    }

    /**
     * Computed: sum of all collections for this invoice
     */
    public function getPaidAmountAttribute(): float
    {
        return round($this->collections()->sum('amount'), 2);
    }

    /**
     * Computed: total_amount - paid_amount
     */
    public function getOutstandingAmountAttribute(): float
    {
        return round(max(0, $this->total_amount - $this->discount_amount - $this->paid_amount - $this->deduction_amount), 2);
    }

    protected $appends = ['paid_amount', 'outstanding_amount'];

    /**
     * Generate the next invoice code: Inv-YYYY-MM-Seq
     */
    public static function generateCode(): string
    {
        $prefix = (string) Setting::getValue('prefixes.invoice', 'INV') . '-' . now()->format('Ym');
        $lastInvoice = static::where('invoice_code', 'LIKE', $prefix . '%')
            ->orderByDesc('invoice_code')
            ->first();

        $seq = 1;
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_code);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
