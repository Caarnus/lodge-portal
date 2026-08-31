<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficerAssignment extends Model
{
    protected $guarded = [];

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function position()
    {
        return $this->belongsTo(OfficerPosition::class, 'officer_position_id');
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
        ];
    }
}
