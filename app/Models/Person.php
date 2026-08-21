<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $appends = ['display_name'];

    protected function casts(): array
    {
        return [
            'email' => 'string',
            'birth_date' => 'date',
            'is_deceased' => 'boolean',
            'death_date' => 'date',
            'merged_at' => 'datetime',
        ];
    }

    public function setEmailAttribute(?string $email): void
    {
        $this->attributes['email'] = filled($email) ? strtolower(trim($email)) : null;
    }

    public function getDisplayNameAttribute(): string
    {
        $legal = collect([
            $this->legal_first_name,
            $this->legal_middle_name,
            $this->legal_last_name,
            $this->legal_suffix,
        ])->filter()->implode(' ');

        if ($this->preferred_name && $this->legal_last_name) {
            return trim($this->preferred_name.' '.$this->legal_last_name);
        }

        return $legal ?: $this->name;
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function pastMasterTerms()
    {
        return $this->hasMany(PastMasterTerm::class);
    }

    public function relationshipsFrom()
    {
        return $this->hasMany(PersonRelationship::class, 'person_one_id');
    }

    public function relationshipsTo()
    {
        return $this->hasMany(PersonRelationship::class, 'person_two_id');
    }

    public function mergedInto()
    {
        return $this->belongsTo(self::class, 'merged_into_person_id');
    }
}
