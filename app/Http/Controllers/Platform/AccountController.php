<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string)$request->query('search'));
        $accounts = User::query()
            ->when($search !== '', fn(Builder $query) => $query->where(fn(Builder $filter) => $filter
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%'])))
            ->orderBy('name')
            ->paginate(50, ['id', 'name', 'email', 'approval_status', 'is_platform_admin', 'created_at'])
            ->withQueryString();

        return Inertia::render('platform/Accounts', [
            'accounts' => $accounts,
            'filters' => ['search' => $search],
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['account' => 'You cannot remove your own account.']);
        }

        $before = $user->only(['id', 'name', 'email', 'approval_status', 'is_platform_admin', 'person_id']);
        DB::transaction(function () use ($user, $before) {
            Audit::record('user.deleted', $user, null, $before);
            $user->delete();
        });

        return back();
    }
}
