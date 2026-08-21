<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonRelationship extends Model
{
    protected $guarded = [];

    public function owningLodge()
    {
        return $this->belongsTo(Lodge::class, 'owning_lodge_id');
    }

    public function personOne()
    {
        return $this->belongsTo(Person::class, 'person_one_id');
    }

    public function personTwo()
    {
        return $this->belongsTo(Person::class, 'person_two_id');
    }

    public function type()
    {
        return $this->belongsTo(RelationshipType::class, 'relationship_type_id');
    }
}
