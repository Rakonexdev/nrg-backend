<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialEntry extends Model
{
    protected $fillable = [
        'financial_head_id',
        'type',
        'amount',
        'entry_date',
        'project_id',
        'person_id',
        'settlement_id',
        'document_url',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function head()
    {
        return $this->belongsTo(FinancialHead::class, 'financial_head_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
