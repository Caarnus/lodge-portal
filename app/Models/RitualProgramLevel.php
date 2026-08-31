<?php

namespace App\Models;

use Database\Factories\RitualProgramLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RitualProgramLevel extends Model
{
    /** @use HasFactory<RitualProgramLevelFactory> */
    use HasFactory;

    protected $guarded = [];

    public function achievements()
    {
        return $this->hasMany(PersonRitualLevelAchievement::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
