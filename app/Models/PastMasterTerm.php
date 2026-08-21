<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PastMasterTerm extends Model
{
    protected $guarded = [];

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}
