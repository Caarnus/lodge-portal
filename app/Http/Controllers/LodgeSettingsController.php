<?php

namespace App\Http\Controllers;

use App\Enums\LodgeStatus;
use App\Http\Requests\LodgeRequest;
use App\Models\Lodge;
use App\Services\Audit;
use Inertia\Inertia;

class LodgeSettingsController extends Controller
{
    private function allow(Lodge $l)
    {
        abort_unless(request()->user()->hasLodgePermission($l, 'lodge.manage'), 403);
    }

    public function edit(Lodge $lodge)
    {
        $this->allow($lodge);

        return Inertia::render('lodge/Settings', ['lodge' => $lodge]);
    }

    public function update(LodgeRequest $r, Lodge $lodge)
    {
        $this->allow($lodge);
        if ($lodge->status === LodgeStatus::DisabledLocked && $r->status === 'active' && ! $r->user()->is_platform_admin) {
            abort(403);
        }
        $before = $lodge->toArray();
        $data = $r->safe()->except('logo');
        if ($r->hasFile('logo')) {
            $data['logo_path'] = $r->file('logo')->store('lodges', 'public');
        }
        $lodge->update($data);
        Audit::record('lodge.updated', $lodge, $lodge, $before, $lodge->fresh()->toArray());

        return back();
    }
}
