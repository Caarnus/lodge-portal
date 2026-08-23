<?php

namespace App\Models;

use App\Enums\LodgeCommunicationStatus;
use Database\Factories\LodgeCommunicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LodgeCommunication extends Model
{
    /** @use HasFactory<LodgeCommunicationFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => LodgeCommunicationStatus::class, 'degree_keys' => 'array', 'membership_status_keys' => 'array', 'membership_ids' => 'array', 'relation_person_ids' => 'array', 'send_requested_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function lodge()
    {
        return $this->belongsTo(Lodge::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastEditor()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function distributionRuns()
    {
        return $this->hasMany(CommunicationDistributionRun::class);
    }
}
