<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\RegistrationDecision;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RegistrationReviewController extends Controller
{
    public function index()
    {
        $u = request()->user();
        $q = User::where('approval_status', 'pending')->with('homeLodge');
        if (!$u->is_platform_admin) {
            $lodgeIds = DB::table('lodge_user_roles')
                ->join('permission_role', 'lodge_user_roles.role_id', '=', 'permission_role.role_id')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('lodge_user_roles.user_id', $u->id)
                ->where('permissions.key', 'registration.review')
                ->pluck('lodge_user_roles.lodge_id');
            abort_if($lodgeIds->isEmpty(), 403);
            $q->whereIn('home_lodge_id', $lodgeIds);
        }

        return Inertia::render('platform/Registrations', ['registrations' => $q->get()]);
    }

    public function decide(Request $r, User $user)
    {
        $this->allow($user);
        $data = $r->validate(['decision' => 'required|in:approved,rejected', 'reason' => 'nullable|required_if:decision,rejected|string|max:1000']);
        $before = $user->only(['approval_status', 'approved_at', 'approved_by', 'rejection_reason']);
        $user->update(['approval_status' => $data['decision'], 'approved_at' => $data['decision'] === 'approved' ? now() : null, 'approved_by' => $r->user()->id, 'rejection_reason' => $data['reason'] ?? null]);
        Audit::record('registration.' . $data['decision'], $user, $user->homeLodge, $before, $user->fresh()->only(array_keys($before)));
        $user->notify(new RegistrationDecision($data['decision'], $user->homeLodge?->name, $data['reason'] ?? null));

        return back();
    }

    private function allow(User $u)
    {
        $actor = request()->user();
        abort_unless($actor->is_platform_admin || ($u->home_lodge_id && $actor->hasLodgePermission($u->homeLodge, 'registration.review')), 403);
    }
}
