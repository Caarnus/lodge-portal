<?php

namespace App\Models;

use App\Enums\RitualVisibilityScope;
use Database\Factories\PersonRitualSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonRitualSetting extends Model
{
    /** @use HasFactory<PersonRitualSettingFactory> */
    use HasFactory;

    public $incrementing = false;
    protected $guarded = [];
    protected $primaryKey = 'person_id';

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['visibility_scope' => RitualVisibilityScope::class];
    }
}
