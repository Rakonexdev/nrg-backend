<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Setting;

class Settlement extends Model
{
    protected $fillable = [
        'settlement_code',
        'collector_id',
        'period_start',
        'period_end',
        'total_amount',
        'status',
        'notes',
        'submitted_at',
        'settled_at',
        'created_by',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'submitted_at' => 'datetime',
            'settled_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_settlement');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public static function generateCode(): string
    {
        $prefix = (string) Setting::getValue('prefixes.settlement', 'SET') . '-' . now()->format('Ym');
        $last = static::where('settlement_code', 'like', $prefix . '%')->orderByDesc('settlement_code')->first();
        $seq = 1;
        if ($last) {
            $seq = (int) last(explode('-', $last->settlement_code)) + 1;
        }

        return $prefix . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
