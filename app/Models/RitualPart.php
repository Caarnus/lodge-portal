<?php

namespace App\Models;

use Database\Factories\RitualPartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RitualPart extends Model
{
    /** @use HasFactory<RitualPartFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'counts_toward_program' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(RitualCategory::class, 'ritual_category_id');
    }

    public function proficiencies()
    {
        return $this->hasMany(PersonRitualProficiency::class);
    }
}
