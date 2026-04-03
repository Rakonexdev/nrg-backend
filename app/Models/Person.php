<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $table = 'persons';

    protected $fillable = [
        'name',
        'phone',
        'qatar_id',
        'id_expiration_date',
        'id_photo_url',
        'company_id',
        'date_of_birth',
        'nationality',
        'passport_number',
        'passport_validity',
    ];

    protected function casts(): array
    {
        return [
            'id_expiration_date' => 'date',
            'date_of_birth' => 'date',
            'passport_validity' => 'date',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function collectorAssignments()
    {
        return $this->hasMany(CollectorAssignment::class);
    }

    public function activeCollectorAssignment()
    {
        return $this->hasOne(CollectorAssignment::class)->where('is_active', true);
    }

    public function renewals()
    {
        return $this->hasMany(QidRenewal::class);
    }

    public function files()
    {
        return $this->hasMany(PersonFile::class);
    }
}
