<?php

namespace App\Http\Middleware;

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
        $lodges = $user?->lodges()->select('lodges.id', 'lodges.name', 'lodges.slug')->distinct()->get()
            ->map(fn ($lodge) => $lodge->setAttribute('can_manage_website', $user->hasLodgePermission($lodge, 'website.manage'))) ?? collect();
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
        ]);
    }
}
