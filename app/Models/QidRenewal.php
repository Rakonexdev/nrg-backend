<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QidRenewal extends Model
{
    protected $fillable = [
        'person_id',
        'assigned_to',
        'current_expiry_date',
        'status',
        'renewed_on',
        'latest_document_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_expiry_date' => 'date',
            'renewed_on' => 'date',
        ];
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
