<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorAssignment extends Model
{
    protected $fillable = [
        'person_id',
        'collector_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }
}
