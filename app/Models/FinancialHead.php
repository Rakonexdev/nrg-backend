<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialHead extends Model
{
    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(FinancialHead::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(FinancialHead::class, 'parent_id');
    }

    public function entries()
    {
        return $this->hasMany(FinancialEntry::class);
    }
}
