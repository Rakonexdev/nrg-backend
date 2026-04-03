<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDeduction extends Model
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'deducted_at',
        'reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deducted_at' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
