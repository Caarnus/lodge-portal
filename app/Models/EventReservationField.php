<?php

namespace App\Models;

use App\Enums\ReservationFieldType;
use Illuminate\Database\Eloquent\Model;

class EventReservationField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => ReservationFieldType::class, 'is_required' => 'boolean', 'is_active' => 'boolean', 'options' => 'array'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }
}
