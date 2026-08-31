<?php

namespace App\Models;

use Database\Factories\PersonRitualLevelAchievementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonRitualLevelAchievement extends Model
{
    /** @use HasFactory<PersonRitualLevelAchievementFactory> */
    use HasFactory;

    protected $guarded = [];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function level()
    {
        return $this->belongsTo(RitualProgramLevel::class, 'ritual_program_level_id');
    }

    protected function casts(): array
    {
        return ['achieved_at' => 'datetime'];
    }
}
