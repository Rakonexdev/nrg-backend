<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectProfession extends Model
{
    protected $fillable = [
        'project_id',
        'profession_name',
        'name',
        'hourly_rate',
        'date_of_join',
        'no_of_persons',
        'status',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'date_of_join' => 'date',
            'deactivated_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class, 'project_profession_id');
    }

    /**
     * Computed: hourly_rate * no_of_persons
     */
    public function getTotalAttribute(): float
    {
        return round($this->hourly_rate * $this->no_of_persons, 2);
    }

    protected $appends = ['total'];
}
