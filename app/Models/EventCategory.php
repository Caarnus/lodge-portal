<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCategory extends Model
{
    protected $guarded = [];

    public function lodges()
    {
        return $this->belongsToMany(Lodge::class)->withTimestamps();
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
