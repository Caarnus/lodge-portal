<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventVolunteerPosition;
use App\Models\Lodge;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $position = EventVolunteerPosition::create($data + ['event_id' => $event->id, 'lodge_id' => $lodge->id, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        Audit::record('volunteer_position.created', $position, $lodge, null, $position->toArray());

        return back();
    }

    public function update(Request $request, Lodge $lodge, Event $event, EventVolunteerPosition $position)
    {
        $this->authorizeEvent($request, $lodge, $event);
        abort_unless($position->event_id === $event->id && $position->lodge_id === $lodge->id, 404);
        $data = $this->data($request);
        if (($data['event_occurrence_id'] ?? null) !== $position->event_occurrence_id) {
            throw ValidationException::withMessages(['event_occurrence_id' => 'Position scope cannot be changed. Create a new position instead.']);
        }
        DB::transaction(function () use ($position, $data, $request, $lodge): void {
            $position = EventVolunteerPosition::query()->lockForUpdate()->findOrFail($position->id);
            $before = $position->toArray();
            $counts = $position->commitments()->where('status', 'committed')->selectRaw('event_occurrence_id, count(*) as committed')->groupBy('event_occurrence_id')->orderByDesc('committed')->get();
            $blocking = $counts->first();
            if ($blocking && $data['needed_count'] < $blocking->committed) {
                $when = EventOccurrence::query()->whereKey($blocking->event_occurrence_id)->value('starts_at');
                throw ValidationException::withMessages(['needed_count' => "Needed count cannot be below {$blocking->committed} active commitments".($when ? " on {$when}." : '.')]);
            }
            $position->update($data + ['updated_by' => $request->user()->id]);
            Audit::record('volunteer_position.updated', $position, $lodge, $before, $position->fresh()->toArray());
        });

        return back();
    }

    public function deactivate(Request $request, Lodge $lodge, Event $event, EventVolunteerPosition $position)
    {
        $this->authorizeEvent($request, $lodge, $event);
        abort_unless($position->event_id === $event->id && $position->lodge_id === $lodge->id, 404);
        $before = $position->toArray();
        $position->update(['is_active' => false, 'updated_by' => $request->user()->id]);
        Audit::record('volunteer_position.deactivated', $position, $lodge, $before, $position->fresh()->toArray());

        return back();
    }

    public function destroy(Request $request, Lodge $lodge, Event $event, EventVolunteerPosition $position)
    {
        $this->authorizeEvent($request, $lodge, $event);
        abort_unless($position->event_id === $event->id && $position->lodge_id === $lodge->id, 404);
        abort_unless($event->status->value === 'draft' && ! $position->commitments()->exists(), 422);
        $before = $position->toArray();
        $position->delete();
        Audit::record('volunteer_position.deleted', $position, $lodge, $before, null);

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
