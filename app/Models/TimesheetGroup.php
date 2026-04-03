<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetGroup extends Model
{
    protected $fillable = [
        'group_code',
        'project_id',
        'invoice_id',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public static function generateCode(): string
    {
        $prefix = 'TSG-' . now()->format('Y-m');
        $last = static::where('group_code', 'LIKE', $prefix . '%')->orderByDesc('group_code')->first();
        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->group_code);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
