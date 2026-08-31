<?php

namespace App\Models;

use App\Enums\RitualDaypart;
use Database\Factories\PersonRitualAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonRitualAvailability extends Model
{
    /** @use HasFactory<PersonRitualAvailabilityFactory> */
    use HasFactory;

    protected $guarded = [];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    protected function casts(): array
    {
        return [
            'daypart' => RitualDaypart::class,
            'is_enabled' => 'boolean',
        ];
    }
}
