<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::with(['subscription'])
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->when($request->role, fn ($q) => $q->where('role', $request->role))
            ->when($request->plan, fn ($q) => $q->whereHas('subscription', fn ($sq) => $sq->where('plan', $request->plan))
            )
            ->orderByDesc('created_at');

        $users = $query->paginate(30)->through(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'is_active' => $u->is_active,
            'wa_number' => $u->wa_number,
            'plan' => $u->subscription?->plan,
            'sub_status' => $u->subscription?->status,
            'created_at' => $u->created_at->format('d M Y'),
        ]);

        return Inertia::render('Admin/Users', ['users' => $users]);
    }

    public function suspend(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(), 403, 'Tidak bisa suspend Super Admin.');

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'diaktifkan' : 'disuspend';

        return back()->with('success', "User {$user->name} berhasil {$status}.");
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|in:user,admin,super_admin']);

        abort_if($user->id === $request->user()->id, 403, 'Tidak bisa ubah role sendiri.');

        $user->update(['role' => $request->role]);

        return back()->with('success', "Role {$user->name} diubah ke {$request->role}.");
    }

    public function show(User $user): Response
    {
        $user->load(['profile', 'subscription', 'wallets.bank']);

        return Inertia::render('Admin/UserDetail', [
            'user' => $user,
            'stats' => [
                'total_transactions' => $user->transactions()->count(),
                'total_income' => (float) $user->transactions()->income()->sum('amount'),
                'total_expense' => (float) $user->transactions()->expense()->sum('amount'),
                'wallets_count' => $user->wallets()->count(),
                'saving_goals_count' => $user->savingGoals()->count(),
                'bills_count' => $user->bills()->count(),
            ],
        ]);
    }
}
