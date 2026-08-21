<?php

namespace App\Http\Controllers\Platform;

use App\Enums\LodgeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LodgeRequest;
use App\Models\Feature;
use App\Models\Lodge;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use App\Services\LodgeRoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LodgeController extends Controller
{
    public function index()
    {
        $lodges = Lodge::query()
            ->withExists(['websitePages as has_published_home_page' => fn ($query) => $query
                ->whereHas('published', fn ($version) => $version->where('is_home', true))])
            ->orderBy('name')
            ->get()
            ->each(function (Lodge $lodge) {
                $lodge->setAttribute(
                    'public_site_url',
                    $lodge->status === LodgeStatus::Active && $lodge->has_published_home_page
                        ? route('public.website.home', ['lodge' => $lodge->slug], absolute: false)
                        : null,
                );
                $lodge->makeHidden('has_published_home_page');
            });

        return Inertia::render('platform/Lodges', ['lodges' => $lodges]);
    }

    public function create()
    {
        return Inertia::render('platform/LodgeForm', []);
    }

    public function store(LodgeRequest $r, LodgeRoleCatalog $roles)
    {
        $data = $r->safe()->except('logo');
        if ($r->hasFile('logo')) {
            $data['logo_path'] = $r->file('logo')->store('lodges', 'public');
        }
        $lodge = Lodge::create($data);
        $roles->ensureFor($lodge);
        Audit::record('lodge.created', $lodge, $lodge, null, $lodge->toArray());

        return redirect()->route('platform.lodges.edit', $lodge);
    }

    public function edit(Lodge $lodge)
    {
        return Inertia::render('platform/LodgeForm', ['lodge' => $lodge, 'admins' => $lodge->users()->select('users.id', 'users.name', 'users.email')->get(), 'features' => Feature::orderBy('name')->get()->map(fn ($f) => $f->setAttribute('enabled', $lodge->features()->where('features.id', $f->id)->wherePivot('enabled', true)->exists()))]);
    }

    public function update(LodgeRequest $r, Lodge $lodge)
    {
        $before = $lodge->toArray();
        $data = $r->safe()->except('logo');
        if ($r->hasFile('logo')) {
            $data['logo_path'] = $r->file('logo')->store('lodges', 'public');
        }
        $lodge->update($data);
        Audit::record('lodge.updated', $lodge, $lodge, $before, $lodge->fresh()->toArray());

        return back();
    }

    public function assignAdmin(Request $r, Lodge $lodge, LodgeRoleCatalog $roles)
    {
        $data = $r->validate(['email' => 'required|email', 'name' => 'nullable|string|max:255']);
        $email = strtolower($data['email']);
        $u = User::firstOrCreate(['email' => $email], ['name' => $data['name'] ?? Str::before($email, '@'), 'password' => Hash::make(Str::random(40)), 'approval_status' => 'approved', 'approved_at' => now(), 'approved_by' => $r->user()->id]);
        if ($u->wasRecentlyCreated) {
            Password::sendResetLink(['email' => $u->email]);
        } elseif ($u->approval_status !== 'approved') {
            $u->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $r->user()->id,
                'rejection_reason' => null,
            ]);
        }
        $roles->ensureFor($lodge);
        $role = Role::query()->where('lodge_id', $lodge->id)->where('name', 'Administrator')->firstOrFail();
        DB::table('lodge_user_roles')->updateOrInsert(['lodge_id' => $lodge->id, 'user_id' => $u->id, 'role_id' => $role->id], ['created_at' => now(), 'updated_at' => now()]);
        Audit::record('lodge.admin_assigned', $u, $lodge, null, ['user_id' => $u->id]);

        return back();
    }

    public function features(Request $r, Lodge $lodge)
    {
        $ids = $r->validate(['features' => 'array', 'features.*' => 'integer|exists:features,id'])['features'] ?? [];
        foreach (Feature::all() as $f) {
            $lodge->features()->syncWithoutDetaching([$f->id => ['enabled' => in_array($f->id, $ids)]]);
        }
        Audit::record('lodge.features_updated', $lodge, $lodge, null, ['feature_ids' => $ids]);

        return back();
    }
}
