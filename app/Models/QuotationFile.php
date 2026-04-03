<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationFile extends Model
{
    protected $fillable = [
        'quotation_id',
        'file_url',
        'original_name',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
