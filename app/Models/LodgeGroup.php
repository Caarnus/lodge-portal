<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LodgeGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_public_landing_page' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function type()
    {
        return $this->belongsTo(LodgeGroupType::class, 'lodge_group_type_id');
    }

    public function lodges()
    {
        return $this->belongsToMany(Lodge::class, 'lodge_group_memberships')->withPivot('created_by')->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }

    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query->active()->where('has_public_landing_page', true);
    }
}
