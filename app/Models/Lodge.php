<?php

namespace App\Models;

use App\Enums\LodgeStatus;
use Database\Factories\LodgeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lodge extends Model
{
    /** @use HasFactory<LodgeFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => LodgeStatus::class];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'lodge_user_roles')->withPivot('role_id')->withTimestamps();
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class)->withPivot('enabled')->withTimestamps();
    }
}
