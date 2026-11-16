<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public const ROLES = ['user', 'venue_partner', 'safety_admin', 'super_admin'];

    public function index()
    {
        return view('admin.users', ['users' => User::with('roles')->orderBy('id')->paginate(25), 'roles' => self::ROLES]);
    }

    public function update(Request $request, User $user)
    {
        $d = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'is_banned' => ['nullable', 'boolean'],
        ]);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role or ban yourself.');

        $user->syncRoles([Role::findOrCreate($d['role'])]);
        $user->update(['is_banned' => $request->boolean('is_banned')]);
        AuditLog::record($request->user()->id, 'user.updated', ['user_id' => $user->id, 'role' => $d['role'], 'banned' => $user->is_banned]);

        return back()->with('status', __('User updated.'));
    }
}
