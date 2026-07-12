<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Subscription::with('user:id,name,email')->orderByDesc('created_at');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->plan) {
            $query->where('plan', $request->plan);
        }
        if ($request->search) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            );
        }

        $subscriptions = $query->paginate(30)->through(fn ($s) => [
            'id' => $s->id,
            'user_id' => $s->user_id,
            'user_name' => $s->user?->name,
            'user_email' => $s->user?->email,
            'plan' => $s->plan,
            'status' => $s->status,
            'starts_at' => $s->starts_at?->format('Y-m-d'),
            'ends_at' => $s->ends_at?->format('Y-m-d'),
            'trial_ends_at' => $s->trial_ends_at?->format('d M Y'),
            'amount' => (float) $s->amount,
            'payment_method' => $s->payment_method,
        ]);

        $summary = [
            'total_active' => Subscription::where('status', 'active')->count(),
            'total_trial' => Subscription::where('plan', 'trial')->where('status', 'active')->count(),
            'total_paid' => Subscription::whereIn('plan', ['monthly', 'yearly'])->where('status', 'active')->count(),
            'total_expired' => Subscription::where('status', 'expired')->count(),
        ];

        return Inertia::render('Admin/Subscriptions', [
            'subscriptions' => $subscriptions,
            'summary' => $summary,
            'filters' => $request->only('status', 'plan', 'search'),
        ]);
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'in:trial,monthly,yearly'],
            'status' => ['required', 'in:active,expired,cancelled'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $subscription->update($validated);

        return back()->with('success', "Subscription {$subscription->user?->name} berhasil diupdate!");
    }
}
