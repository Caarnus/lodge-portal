<?php

namespace App\Models;

use App\Enums\DirectoryVisibilityScope;
use Database\Factories\PersonDirectoryPrivacySettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonDirectoryPrivacySetting extends Model
{
    /** @use HasFactory<PersonDirectoryPrivacySettingFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $primaryKey = 'person_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'scope' => DirectoryVisibilityScope::class,
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
            'show_address' => 'boolean',
            'show_profile_photo' => 'boolean',
            'show_degree' => 'boolean',
        ];
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
