<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Lodge;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, ?Model $subject = null, ?Lodge $lodge = null, ?array $before = null, ?array $after = null): AuditEvent
    {
        return AuditEvent::create(['actor_id' => auth()->id(), 'lodge_id' => $lodge?->id, 'action' => $action, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'before' => $before, 'after' => $after, 'ip_address' => request()?->ip()]);
    }
}
