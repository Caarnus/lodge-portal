<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerPosition;
use App\Models\Lodge;
use Illuminate\Http\Request;

class EventVolunteerPositionController extends Controller
{
    public function store(Request $request, Lodge $lodge, Event $event)
    {
        $this->authorizeEvent($request, $lodge, $event);
        $data = $this->data($request);
        $occurrenceId = $data['event_occurrence_id'] ?? null;
        if ($occurrenceId) {
            abort_unless(EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $event->id)->where('lodge_id', $lodge->id)->exists(), 404);
        }
        EventVolunteerPosition::create($data + ['event_id' => $event->id, 'lodge_id' => $lodge->id, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return back();
    }

    public function update(Request $request, Lodge $lodge, Event $event, EventVolunteerPosition $position)
    {
        $this->authorizeEvent($request, $lodge, $event);
        abort_unless($position->event_id === $event->id && $position->lodge_id === $lodge->id, 404);
        $data = $this->data($request);
        abort_unless(($data['event_occurrence_id'] ?? null) === $position->event_occurrence_id, 422);
        $active = $position->commitments()->where('status', 'committed')->count();
        if ($data['needed_count'] < $active) {
            return back()->withErrors(['needed_count' => 'Needed count cannot be below active commitments.']);
        }
        $position->update($data + ['updated_by' => $request->user()->id]);

        return back();
    }

    public function deactivate(Request $request, Lodge $lodge, Event $event, EventVolunteerPosition $position)
    {
        $this->authorizeEvent($request, $lodge, $event);
        abort_unless($position->event_id === $event->id && $position->lodge_id === $lodge->id, 404);
        $position->update(['is_active' => false, 'updated_by' => $request->user()->id]);

        return back();
    }

    public function destroy(Request $request, Lodge $lodge, Event $event, EventVolunteerPosition $position)
    {
        $this->authorizeEvent($request, $lodge, $event);
        abort_unless($position->event_id === $event->id && $position->lodge_id === $lodge->id, 404);
        abort_unless($event->status->value === 'draft' && ! $position->commitments()->exists(), 422);
        $position->delete();

        return back();
    }

    private function authorizeEvent(Request $request, Lodge $lodge, Event $event): void
    {
        abort_unless($event->lodge_id === $lodge->id, 404);
        abort_unless($request->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }

    private function data(Request $request): array
    {
        return $request->validate(['event_occurrence_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'needed_count' => ['required', 'integer', 'min:1'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'is_active' => ['nullable', 'boolean']]);
    }
}
