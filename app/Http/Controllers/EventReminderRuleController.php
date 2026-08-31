<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReminderRule;
use App\Models\Lodge;
use Illuminate\Http\Request;

class EventReminderRuleController extends Controller
{
    public function store(Request $request, Lodge $lodge, Event $event)
    {
        $this->allow($lodge, $event);
        $data = $request->validate(['offset_minutes' => ['required', 'integer', 'min:1', 'max:525600']]);
        $event->reminderRules()->firstOrCreate(['offset_minutes' => $data['offset_minutes']], ['lodge_id' => $lodge->id]);

        return back();
    }

    private function allow(Lodge $lodge, Event $event): void
    {
        abort_unless($event->lodge_id === $lodge->id && request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }

    public function destroy(Lodge $lodge, Event $event, EventReminderRule $rule)
    {
        $this->allow($lodge, $event);
        abort_unless($rule->event_id === $event->id && $rule->lodge_id === $lodge->id, 404);
        $rule->delete();

        return back();
    }
}
