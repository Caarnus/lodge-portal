<?php

namespace App\Http\Middleware;

use App\Models\Lodge;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();
        $lodges = collect();
        if ($user instanceof User) {
            $lodgeQuery = Lodge::query();
            if (! $user->is_platform_admin) {
                $lodgeQuery->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id));
            }
            $lodges = $lodgeQuery->orderBy('name')->get(['id', 'name', 'slug'])
                ->map(fn (Lodge $lodge) => $lodge
                    ->setAttribute('can_manage_lodge', $user->hasLodgePermission($lodge, 'lodge.manage'))
                    ->setAttribute('can_manage_website', $user->hasLodgePermission($lodge, 'website.manage'))
                    ->setAttribute('can_view_people', $user->hasLodgePermission($lodge, 'people.view'))
                    ->setAttribute('can_manage_officers', $user->hasLodgePermission($lodge, 'officers.manage'))
                    ->setAttribute('can_manage_roles', $user->hasLodgePermission($lodge, 'roles.manage'))
                    ->setAttribute('can_manage_events', $user->hasLodgePermission($lodge, 'events.manage')));
        }
        $canReviewRegistrations = $user && ($user->is_platform_admin || DB::table('lodge_user_roles')
            ->join('permission_role', 'lodge_user_roles.role_id', '=', 'permission_role.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('lodge_user_roles.user_id', $user->id)
            ->where('permissions.key', 'registration.review')
            ->exists());

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'lodges' => $lodges,
                'can_review_registrations' => (bool) $canReviewRegistrations,
            ],
            'flash' => [
                'notice' => fn () => $request->session()->get('notice'),
                'officer_role_prompt' => fn () => $request->session()->get('officer_role_prompt'),
            ],
        ]);
    }
}
