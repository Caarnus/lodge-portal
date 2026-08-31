<?php

namespace App\Models;

use Database\Factories\RitualCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RitualCategory extends Model
{
    /** @use HasFactory<RitualCategoryFactory> */
    use HasFactory;

    protected $guarded = [];

    public function degree()
    {
        return $this->belongsTo(MasonicDegree::class, 'masonic_degree_id');
    }

    public function parts()
    {
        return $this->hasMany(RitualPart::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
