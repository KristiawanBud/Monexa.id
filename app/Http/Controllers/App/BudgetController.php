<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\TransactionCategory;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // Tampilkan halaman budget management
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $request->input('period', now()->format('Y-m'));

        $categories = TransactionCategory::forUser($user->id)
            ->where('type', 'expense');

        $budgets = Budget::where('user_id', $user->id)
            ->where('period', $period)
            ->with('category')
            ->get()
            ->keyBy('category_id');

        // Pengeluaran aktual per kategori di periode ini
        $actuals = $user->transactions()
            ->where('type', 'expense')
            ->forPeriod($period)
            ->get()
            ->groupBy('category_id')
            ->map(fn ($txs) => (float) $txs->sum('amount'));

        $totalIncome = (float) $user->transactions()
            ->where('type', 'income')
            ->forPeriod($period)
            ->sum('amount');

        $totalBudget = (float) $budgets->sum('amount');
        $totalSpent = (float) $actuals->sum();

        // Bangun data budget per kategori
        $budgetData = $categories->map(fn ($cat) => [
            'category_id' => $cat->id,
            'category_name' => $cat->name,
            'category_emoji' => $cat->emoji,
            'allocation_group' => $cat->allocation_group,
            'budget' => (float) ($budgets->get($cat->id)?->amount ?? 0),
            'spent' => (float) ($actuals->get($cat->id) ?? 0),
            'percent' => $budgets->has($cat->id) && $budgets->get($cat->id)->amount > 0
                ? min(100, round(
                    ($actuals->get($cat->id, 0) / $budgets->get($cat->id)->amount) * 100
                ))
                : null,
            'status' => $this->getBudgetStatus(
                $actuals->get($cat->id, 0),
                $budgets->get($cat->id)?->amount ?? 0
            ),
        ])->values();

        // Kalkulasi 50/30/20 dinamis berdasarkan allocation_group dari DB
        $strategy = $this->calculate503020Dynamic($totalIncome, $actuals, $user->id);

        return Inertia::render('App/Budget', [
            'period' => $period,
            'periodLabel' => Carbon::createFromFormat('Y-m', $period)->translatedFormat('F Y'),
            'budgets' => $budgetData,
            'total_income' => $totalIncome,
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'strategy' => $strategy,
            'months' => $this->getLast12Months(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Simpan / update budget per kategori
    // ─────────────────────────────────────────────────────────────
    public function upsert(Request $request): RedirectResponse
    {
        $request->validate([
            'budgets' => ['required', 'array'],
            'budgets.*.category_id' => ['required', 'exists:transaction_categories,id'],
            'budgets.*.amount' => ['required', 'numeric', 'min:0'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $user = $request->user();
        $period = $request->input('period');

        foreach ($request->input('budgets') as $b) {
            if ((float) $b['amount'] <= 0) {
                // Hapus budget jika nominal nol
                Budget::where('user_id', $user->id)
                    ->where('category_id', $b['category_id'])
                    ->where('period', $period)
                    ->delete();

                continue;
            }

            Budget::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category_id' => $b['category_id'],
                    'period' => $period,
                ],
                ['amount' => $b['amount']]
            );
        }

        return back()->with('success', 'Budget berhasil disimpan!');
    }

    // ─────────────────────────────────────────────────────────────
    // Salin budget dari bulan sebelumnya
    // ─────────────────────────────────────────────────────────────
    public function copyFromLastMonth(Request $request): RedirectResponse
    {
        $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $user = $request->user();
        $period = $request->input('period');
        $lastPeriod = Carbon::createFromFormat('Y-m', $period)
            ->subMonth()
            ->format('Y-m');

        $lastBudgets = Budget::where('user_id', $user->id)
            ->where('period', $lastPeriod)
            ->get();

        if ($lastBudgets->isEmpty()) {
            return back()->with('error', 'Tidak ada budget di bulan sebelumnya.');
        }

        foreach ($lastBudgets as $b) {
            Budget::updateOrCreate(
                ['user_id' => $user->id, 'category_id' => $b->category_id, 'period' => $period],
                ['amount' => $b->amount]
            );
        }

        return back()->with('success', "Budget bulan lalu berhasil disalin ke {$period}!");
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Kalkulasi 50/30/20 secara DINAMIS
    //
    // FIX: Sebelumnya menggunakan hardcode array nama kategori.
    // Sekarang menggunakan kolom allocation_group dari database
    // sehingga kategori kustom user juga ikut terhitung.
    //
    // Langkah:
    //   1. Ambil semua kategori expense milik user (sistem + kustom)
    //   2. Group berdasarkan allocation_group
    //   3. Hitung total spent per group dengan JOIN ke actuals
    // ─────────────────────────────────────────────────────────────
    private function calculate503020Dynamic(
        float $income,
        Collection $actuals,
        string $userId
    ): array {
        if ($income <= 0) {
            return [];
        }

        // Ambil semua kategori expense beserta allocation_group-nya
        $categories = TransactionCategory::where('type', 'expense')
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')        // kategori sistem
                    ->orWhere('user_id', $userId); // kategori kustom user
            })
            ->whereNotNull('allocation_group')
            ->get()
            ->keyBy('id'); // key by category_id untuk lookup cepat

        // Akumulasi pengeluaran per allocation_group
        $grouped = ['needs' => 0.0, 'wants' => 0.0, 'savings' => 0.0];

        foreach ($actuals as $catId => $amount) {
            $cat = $categories->get($catId);
            if ($cat && isset($grouped[$cat->allocation_group])) {
                $grouped[$cat->allocation_group] += $amount;
            }
            // Kategori tanpa allocation_group (null) tidak dihitung
        }

        // Bangun output
        return [
            'kebutuhan' => [
                'target' => $income * 0.50,
                'spent' => $grouped['needs'],
                'pct' => round(($grouped['needs'] / $income) * 100, 1),
            ],
            'keinginan' => [
                'target' => $income * 0.30,
                'spent' => $grouped['wants'],
                'pct' => round(($grouped['wants'] / $income) * 100, 1),
            ],
            'tabungan' => [
                'target' => $income * 0.20,
                'spent' => $grouped['savings'],
                'pct' => round(($grouped['savings'] / $income) * 100, 1),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Status budget per kategori
    // ─────────────────────────────────────────────────────────────
    private function getBudgetStatus(float $spent, float $budget): string
    {
        if ($budget <= 0) {
            return 'unset';
        }
        $pct = ($spent / $budget) * 100;
        if ($pct >= 100) {
            return 'over';
        }
        if ($pct >= 80) {
            return 'warn';
        }

        return 'ok';
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Daftar 12 bulan terakhir untuk selector periode
    // ─────────────────────────────────────────────────────────────
    private function getLast12Months(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $months[] = [
                'value' => $d->format('Y-m'),
                'label' => $d->format('M Y'),
            ];
        }

        return $months;
    }
}
