<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservationField;
use App\Models\Lodge;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventReservationFieldController extends Controller
{
    public function store(Request $request, Lodge $lodge, Event $event)
    {
        $this->allow($lodge, $event);
        $data = $request->validate(['key' => ['required', 'alpha_dash', 'max:100', Rule::unique('event_reservation_fields')->where('event_id', $event->id)], 'label' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(['short_text', 'long_text', 'select', 'checkbox'])], 'is_required' => ['boolean'], 'options' => ['nullable', 'array']]);
        $event->reservationFields()->create($data + ['lodge_id' => $lodge->id, 'sort_order' => ((int) $event->reservationFields()->max('sort_order')) + 1, 'is_active' => true]);

        return back();
    }

    public function destroy(Lodge $lodge, Event $event, EventReservationField $field)
    {
        $this->allow($lodge, $event);
        abort_unless($field->event_id === $event->id && $field->lodge_id === $lodge->id, 404);
        $field->update(['is_active' => false]);

        return back();
    }

    private function allow(Lodge $lodge, Event $event): void
    {
        abort_unless($event->lodge_id === $lodge->id && request()->user()?->hasLodgePermission($lodge, 'events.manage'), 403);
    }
}
