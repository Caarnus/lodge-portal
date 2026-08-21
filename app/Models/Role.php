<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'lodge_user_roles')->withPivot('lodge_id')->withTimestamps();
    }
}
