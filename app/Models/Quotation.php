<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Setting;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_code',
        'project_id',
        'title',
        'quoted_amount',
        'scope_summary',
        'manual_reference_number',
        'document_url',
        'status',
        'quoted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quoted_amount' => 'decimal:2',
            'quoted_at' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files()
    {
        return $this->hasMany(QuotationFile::class);
    }

    public static function generateCode(): string
    {
        $prefix = (string) Setting::getValue('prefixes.quotation', 'QTN') . '-' . now()->format('Ym');
        $last = static::where('quotation_code', 'like', $prefix . '%')->orderByDesc('quotation_code')->first();
        $seq = 1;
        if ($last) {
            $seq = (int) last(explode('-', $last->quotation_code)) + 1;
        }

        return $prefix . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
