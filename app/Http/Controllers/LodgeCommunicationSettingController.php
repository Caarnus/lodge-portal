<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LodgeCommunicationSettingController extends Controller
{
    public function edit(Lodge $lodge)
    {
        $this->allowLodge($lodge, 'communications.settings');

        return Inertia::render('communications/Settings', ['lodge' => $lodge, 'settings' => $lodge->communicationSetting()->firstOrCreate()->only(['sender_display_name', 'reply_to_email', 'secretary_email', 'newsletter_contact_email'])]);
    }

    public function update(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'communications.settings');
        $data = $request->validate(['sender_display_name' => 'nullable|string|max:255', 'reply_to_email' => 'nullable|email:rfc,dns|max:255', 'secretary_email' => 'nullable|email:rfc,dns|max:255', 'newsletter_contact_email' => 'nullable|email:rfc,dns|max:255']);
        $setting = $lodge->communicationSetting()->firstOrCreate();
        $before = $setting->only(array_keys($data));
        $setting->fill(array_map(fn($value) => filled($value) ? strtolower(trim($value)) : null, $data) + ['updated_by' => $request->user()->id])->save();
        Audit::record('lodge_communication_setting.updated', $setting, $lodge, $before, $setting->fresh()->only(array_keys($data)));

        return back();
    }
}
