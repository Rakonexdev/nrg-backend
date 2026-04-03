<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectContact extends Model
{
    protected $fillable = [
        'project_id',
        'person_id',
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
}
