<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelationshipType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_symmetric' => 'boolean', 'is_active' => 'boolean'];
    }
}
