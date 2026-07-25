<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SavingDeposit;
use App\Models\SavingGoal;
use App\Models\UserWallet;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SavingGoalController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $goals = $user->savingGoals()
            ->orderBy('status')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'emoji' => $g->emoji,
                'target_amount' => (float) $g->target_amount,
                'current_amount' => (float) $g->current_amount,
                'deadline' => $g->deadline?->format('M Y'),
                'status' => $g->status,
                'progress_percent' => $g->progress_percent,
            ]);

        return Inertia::render('App/Saving', [
            'goals' => $goals,
            'wallets' => $user->activeWallets(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'target_amount' => ['required', 'numeric', 'min:1'],
            'deadline' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->savingGoals()->create($validated);

        return back()->with('success', 'Goal tabungan berhasil dibuat!');
    }

    public function deposit(Request $request, SavingGoal $goal)
    {
        abort_if($goal->user_id !== $request->user()->id, 403);

        $request->validate([
            'wallet_id' => 'required|exists:user_wallets,id',
            'amount' => 'required|numeric|min:1',
            'deposited_at' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $goal) {
            $deposit = SavingDeposit::create([
                'saving_goal_id' => $goal->id,
                'wallet_id' => $request->wallet_id,
                'amount' => $request->amount,
                'deposited_at' => $request->deposited_at,
            ]);

            $wallet = UserWallet::find($request->wallet_id);
            $this->walletService->depositToSaving($wallet, $request->amount, $deposit);

            $goal->increment('current_amount', $request->amount);

            if ($goal->fresh()->current_amount >= $goal->target_amount) {
                $goal->update(['status' => 'completed', 'completed_at' => now()]);
            }
        });

        return back()->with('success', 'Setoran berhasil disimpan!');
    }

    public function destroy(Request $request, SavingGoal $goal)
    {
        abort_if($goal->user_id !== $request->user()->id, 403);
        $goal->update(['status' => 'cancelled']);

        return back()->with('success', 'Goal dibatalkan.');
    }

    // ─────────────────────────────────────────────
    // PRIORITAS #4: Riwayat setoran per goal
    // ─────────────────────────────────────────────
    public function deposits(Request $request, SavingGoal $goal)
    {
        abort_if($goal->user_id !== $request->user()->id, 403);

        $deposits = $goal->deposits()
            ->with('wallet:id,display_name')
            ->orderByDesc('deposited_at')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'amount' => (float) $d->amount,
                'wallet' => $d->wallet?->display_name,
                'deposited_at' => $d->deposited_at->translatedFormat('d M Y'),
                'note' => $d->note,
            ]);

        return response()->json(['deposits' => $deposits]);
    }
}
