<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\UserAsset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->load(['wallets', 'savingGoals', 'profile']);
        $assets = $user->assets()->where('is_active', true)->orderBy('sort_order')->get();

        // ── Total dari dompet & rekening ──
        $walletTotal = $user->wallets->where('is_active', true)->where('is_saham', false)->sum('balance');

        // ── Total saham ──
        $sahamTotal = $user->wallets->where('is_active', true)->where('is_saham', true)->sum(fn ($w) => $w->saham_nilai_sekarang ?? $w->balance
        );

        // ── Total aset tambahan (aset tetap, investasi, piutang) ──
        $assetByType = $assets->groupBy('type')->map->sum('value');

        $liquidAssets = ($walletTotal + ($assetByType['liquid'] ?? 0));
        $fixedAssets = $assetByType['fixed'] ?? 0;
        $investAssets = ($sahamTotal + ($assetByType['investment'] ?? 0));
        $receivable = $assetByType['receivable'] ?? 0;
        $totalKekayaan = $liquidAssets + $fixedAssets + $investAssets + $receivable;

        // ── Runway dana darurat ──
        // Runway = total likuid / rata-rata pengeluaran bulanan 3 bulan terakhir
        $avgMonthlyExpense = $this->getAvgMonthlyExpense($user, 3);
        $runwayBulan = $avgMonthlyExpense > 0
            ? round($liquidAssets / $avgMonthlyExpense, 1)
            : null;

        // ── Target dana darurat ──
        $targetBulan = $user->profile?->dana_darurat_bulan ?? 6;
        $targetAmount = $avgMonthlyExpense * $targetBulan;
        $darutPct = $targetAmount > 0
            ? min(100, round(($liquidAssets / $targetAmount) * 100, 1))
            : 0;

        // ── Level kekayaan ──
        $level = $this->getWealthLevel($totalKekayaan);

        return Inertia::render('App/Asset', [
            'wallets' => $user->wallets->where('is_active', true)->values(),
            'assets' => $assets,
            'wallet_total' => (float) $walletTotal,
            'saham_total' => (float) $sahamTotal,
            'liquid_total' => (float) $liquidAssets,
            'fixed_total' => (float) $fixedAssets,
            'invest_total' => (float) $investAssets,
            'receivable_total' => (float) $receivable,
            'total_kekayaan' => (float) $totalKekayaan,
            'runway_bulan' => $runwayBulan,
            'avg_expense' => (float) $avgMonthlyExpense,
            'darurat_target_bulan' => $targetBulan,
            'darurat_target_amount' => (float) $targetAmount,
            'darurat_pct' => $darutPct,
            'level' => $level,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'emoji' => 'nullable|string|max:10',
            'type' => 'required|in:liquid,fixed,investment,receivable',
            'value' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'type' => ['required', 'in:liquid,fixed,investment,receivable'],
            'value' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->assets()->create($validated);

        return back()->with('success', 'Aset berhasil ditambahkan!');
    }

    public function update(Request $request, UserAsset $asset)
    {
        abort_if($asset->user_id !== $request->user()->id, 403);

        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:liquid,fixed,investment,receivable',
            'value' => 'required|numeric|min:0',
        ]);

        $asset->update($request->only('name', 'emoji', 'type', 'value', 'note'));

        return back()->with('success', 'Aset diupdate!');
    }

    public function destroy(Request $request, UserAsset $asset)
    {
        abort_if($asset->user_id !== $request->user()->id, 403);
        $asset->update(['is_active' => false]);

        return back()->with('success', 'Aset dihapus.');
    }

    private function getAvgMonthlyExpense($user, int $months = 3): float
    {
        $total = 0;
        for ($i = 0; $i < $months; $i++) {
            $d = now()->subMonths($i);
            $total += $user->transactions()
                ->where('type', 'expense')
                ->whereYear('transacted_at', $d->year)
                ->whereMonth('transacted_at', $d->month)
                ->sum('amount');
        }

        return $months > 0 ? ($total / $months) : 0;
    }

    private function getWealthLevel(float $total): array
    {
        $levels = [
            ['name' => 'Mulai Bangkit',   'min' => 0,            'max' => 10_000_000,   'next' => 10_000_000],
            ['name' => 'Fondasi Kokoh',   'min' => 10_000_000,   'max' => 50_000_000,   'next' => 50_000_000],
            ['name' => 'Tumbuh Stabil',   'min' => 50_000_000,   'max' => 200_000_000,  'next' => 200_000_000],
            ['name' => 'Mandiri Finansial', 'min' => 200_000_000,  'max' => 1_000_000_000, 'next' => 1_000_000_000],
            ['name' => 'Bebas Finansial', 'min' => 1_000_000_000, 'max' => PHP_FLOAT_MAX, 'next' => null],
        ];

        foreach ($levels as $i => $level) {
            if ($total >= $level['min'] && $total < $level['max']) {
                $pct = $level['max'] !== PHP_FLOAT_MAX
                    ? round((($total - $level['min']) / ($level['max'] - $level['min'])) * 100)
                    : 100;

                return [
                    'name' => $level['name'],
                    'next' => $level['next'],
                    'percent' => $pct,
                    'index' => $i + 1,
                    'total' => count($levels),
                ];
            }
        }

        return ['name' => 'Bebas Finansial', 'next' => null, 'percent' => 100, 'index' => 5, 'total' => 5];
    }
}
