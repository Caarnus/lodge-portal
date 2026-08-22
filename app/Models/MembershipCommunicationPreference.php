<?php

namespace App\Models;

use Database\Factories\MembershipCommunicationPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipCommunicationPreference extends Model
{
    /** @use HasFactory<MembershipCommunicationPreferenceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['receives_lodge_email' => 'boolean', 'receives_print_newsletter' => 'boolean'];
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
