<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonFile extends Model
{
    protected $fillable = [
        'person_id',
        'category',
        'file_url',
        'original_name',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}
