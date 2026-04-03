<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = [
        'invoice_id',
        'user_id',
        'amount',
        'collection_date',
        'next_due_date',
        'method',
        'verified',
        'verified_by',
        'verified_at',
        'settlement_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'collection_date' => 'date',
            'next_due_date' => 'date',
            'amount' => 'decimal:2',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function settlements()
    {
        return $this->belongsToMany(Settlement::class, 'collection_settlement');
    }
}
