<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class, 'created_by');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function collections()
    {
        return $this->hasMany(Collection::class, 'user_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function assignedAccounts()
    {
        return $this->hasMany(CollectorAssignment::class, 'collector_id');
    }

    public function qidRenewalAssignments()
    {
        return $this->hasMany(QidRenewal::class, 'assigned_to');
    }
}
