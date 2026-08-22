<?php

namespace App\Models;

use Database\Factories\LodgeCommunicationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LodgeCommunicationSetting extends Model
{
    /** @use HasFactory<LodgeCommunicationSettingFactory> */
    use HasFactory;

    protected $guarded = [];

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
