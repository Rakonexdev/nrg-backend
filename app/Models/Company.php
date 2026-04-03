<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name'];

    public function persons()
    {
        return $this->hasMany(Person::class);
    }

    public function projects()
    {
        return $this->hasManyThrough(Project::class, Person::class);
    }
}
