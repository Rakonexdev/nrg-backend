<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'project_code',
        'person_id',
        'collector_id',
        'type',
        'status',
        'project_date',
        'lpo_url',
        'fixed_total_amount',
        'fixed_description',
        'reference_number',
        'bill_frequency',
        'payment_credit_days',
        'operationally_completed_at',
        'financially_closed_at',
        'withdrawal_reason',
    ];

    protected function casts(): array
    {
        return [
            'fixed_total_amount' => 'decimal:2',
            'project_date' => 'date',
            'operationally_completed_at' => 'date',
            'financially_closed_at' => 'date',
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

    public function company()
    {
        return $this->hasOneThrough(Company::class, Person::class, 'id', 'id', 'person_id', 'company_id');
    }

    public function professions()
    {
        return $this->hasMany(ProjectProfession::class);
    }

    public function contacts()
    {
        return $this->hasMany(ProjectContact::class);
    }

    public function activeContact()
    {
        return $this->hasOne(ProjectContact::class)->where('is_active', true);
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function files()
    {
        return $this->hasMany(ProjectFile::class);
    }

    /**
     * Generate the next project code: PRJ-YYYY-MM-Seq
     */
    public static function generateCode(): string
    {
        $prefix = 'PRJ-' . now()->format('Y-m');
        $lastProject = static::where('project_code', 'LIKE', $prefix . '%')
            ->orderByDesc('project_code')
            ->first();

        $seq = 1;
        if ($lastProject) {
            $parts = explode('-', $lastProject->project_code);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
