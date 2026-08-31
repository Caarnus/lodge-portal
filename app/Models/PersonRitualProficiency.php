<?php

namespace App\Models;

use App\Enums\RitualProficiencyStatus;
use Database\Factories\PersonRitualProficiencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonRitualProficiency extends Model
{
    /** @use HasFactory<PersonRitualProficiencyFactory> */
    use HasFactory;

    protected $guarded = [];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function part()
    {
        return $this->belongsTo(RitualPart::class, 'ritual_part_id');
    }

    protected function casts(): array
    {
        return [
            'status' => RitualProficiencyStatus::class,
            'interested_in_learning' => 'boolean',
            'willing_to_assist' => 'boolean',
            'performed_for_credit' => 'boolean',
            'first_marked_proficient_on' => 'date',
        ];
    }
}
