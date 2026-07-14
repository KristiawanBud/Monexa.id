<?php

namespace App\Http\Controllers\App;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\DompetFilterRequest;
use App\Models\Bank;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionEditLog;
use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\WalletService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function index(DompetFilterRequest $request): Response
    {
        $user = $request->user();
        $range = $this->resolveRange($request);

        $transactions = $this->buildTransactionsPage($request, $user, $range);

        $period = $request->period ?? now()->format('Y-m');

        // ── Ringkasan sesuai range yang dipilih (Masuk / Keluar) ──
        $rangeSummaryQuery = $user->transactions();
        $this->applyDateFilter($rangeSummaryQuery, $request, $range);
        $rangeSummary = $rangeSummaryQuery->get();
        $rangeIncome = (float) $rangeSummary->where('type', 'income')->sum('amount');
        $rangeExpense = (float) $rangeSummary->where('type', 'expense')->sum('amount');

        $rangeLabel = $this->resolveRangeLabel($request, $range);

        // Dompet (wallets) dengan saldo — untuk tab "Dompet"
        $walletsRaw = $user->wallets()
            ->with('bank:id,short_name,logo_color,logo_initial,type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $wallets = $walletsRaw->map(fn ($w) => [
            'id' => $w->id,
            'display_name' => $w->display_name,
            'account_number' => $w->account_number,
            'type' => $w->type,
            'balance' => (float) $w->balance,
            'is_saham' => $w->is_saham,
            'bank_id' => $w->bank_id,
            'bank_name' => $w->bank?->short_name,
            'bank_color' => $w->bank?->logo_color ?? '#2563EB',
            'bank_initial' => $w->bank?->logo_initial ?? strtoupper(substr($w->display_name, 0, 1)),
            'logo_url' => $w->bank?->logo_url ? Storage::url($w->bank->logo_url) : null,
        ]);

        // ── Breakdown saldo: Cash / Bank / E-Wallet ──
        $cashTotal = (float) $walletsRaw->filter(fn ($w) => ! $w->bank_id)->sum('balance');
        $ewalletTotal = (float) $walletsRaw->filter(fn ($w) => $w->bank?->type === 'digital')->sum('balance');
        $bankTotal = (float) $walletsRaw->filter(fn ($w) => $w->bank_id && $w->bank?->type !== 'digital')->sum('balance');

        // Tagihan aktif — untuk tab "Tagihan"
        $bills = $user->bills()
            ->where('is_active', true)
            ->orderBy('type')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'emoji' => $b->emoji,
                'type' => $b->type,
                'amount' => (float) $b->amount,
                'due_day' => $b->due_day,
                'due_date' => $b->due_date?->format('Y-m-d'),
                'remind_days' => $b->remind_days,
                'days_until_due' => $b->days_until_due,
                'status_color' => $b->status_color,
                'last_paid_at' => $b->last_paid_at?->format('d M Y'),
                'is_paid_this_month' => $b->last_paid_at?->isCurrentMonth() ?? false,
            ]);

        return Inertia::render('App/Dompet', [
            'transactions' => $transactions,
            'wallets' => $wallets,
            'bills' => $bills,
            'banks' => Bank::where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => TransactionCategory::forUser($user->id),
            'period' => $period,
            'range' => $range,
            'range_label' => $rangeLabel,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_income' => $rangeIncome,
            'total_expense' => $rangeExpense,
            'total_balance' => (float) $wallets->sum('balance'),
            'active_wallets_count' => $wallets->count(),
            'cash_total' => $cashTotal,
            'bank_total' => $bankTotal,
            'ewallet_total' => $ewalletTotal,
            'active_tab' => $request->input('tab', 'transaksi'),
            'search_query' => $request->search,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:1',
            'wallet_id' => 'required|exists:user_wallets,id',
            'category_id' => 'nullable|exists:transaction_categories,id',
            'note' => 'nullable|string|max:255',
            'transacted_at' => 'required|date',
        ]);

        $user = $request->user();

        try {
            DB::transaction(function () use ($request, $user) {
                $transaction = $user->transactions()->create([
                    'wallet_id' => $request->wallet_id,
                    'category_id' => $request->category_id,
                    'type' => $request->type,
                    'amount' => $request->amount,
                    'note' => $request->note,
                    'transacted_at' => $request->transacted_at,
                    'source' => 'manual',
                    'created_by' => $user->id,
                ]);

                $this->walletService->applyTransaction($transaction);

                TransactionEditLog::create([
                    'transaction_id' => $transaction->id,
                    'edited_by' => $user->id,
                    'action' => 'create',
                    'old_data' => [],
                    'new_data' => $transaction->toArray(),
                    'edited_at' => now(),
                ]);
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi berhasil disimpan!');
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);

        $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:1',
            'wallet_id' => 'required|exists:user_wallets,id',
            'category_id' => 'nullable|exists:transaction_categories,id',
            'note' => 'nullable|string|max:255',
            'transacted_at' => 'required|date',
        ]);

        $user = $request->user();
        $oldData = $transaction->toArray();

        try {
            DB::transaction(function () use ($request, $transaction, $user, $oldData) {
                $this->walletService->reverseTransaction($transaction);

                $transaction->update([
                    'wallet_id' => $request->wallet_id,
                    'category_id' => $request->category_id,
                    'type' => $request->type,
                    'amount' => $request->amount,
                    'note' => $request->note,
                    'transacted_at' => $request->transacted_at,
                ]);

                $transaction->refresh();

                $this->walletService->applyTransaction($transaction);

                TransactionEditLog::create([
                    'transaction_id' => $transaction->id,
                    'edited_by' => $user->id,
                    'action' => 'update',
                    'old_data' => $oldData,
                    'new_data' => $transaction->toArray(),
                    'edited_at' => now(),
                ]);
            });
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi berhasil diupdate!');
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);

        $user = $request->user();

        DB::transaction(function () use ($transaction, $user) {
            $this->walletService->reverseTransaction($transaction);

            TransactionEditLog::create([
                'transaction_id' => $transaction->id,
                'edited_by' => $user->id,
                'action' => 'delete',
                'old_data' => $transaction->toArray(),
                'new_data' => [],
                'edited_at' => now(),
            ]);

            $transaction->delete();
        });

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }

    public function editLogs(Transaction $transaction)
    {
        return response()->json(
            TransactionEditLog::where('transaction_id', $transaction->id)
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function exportCsv(DompetFilterRequest $request)
    {
        $user = $request->user();
        $range = $this->resolveRange($request);

        $transactions = $this->buildFilteredQuery($request, $user, $range)
            ->limit(5000)
            ->get();

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Tanggal', 'Tipe', 'Kategori', 'Dompet', 'Catatan', 'Jumlah']);

        foreach ($transactions as $t) {
            fputcsv($handle, [
                $t->transacted_at->format('d/m/Y'),
                $t->type === 'income' ? 'Pemasukan' : 'Pengeluaran',
                $t->category?->name ?? '-',
                $t->wallet?->display_name ?? '-',
                $t->note ?? '-',
                (float) $t->amount,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'transaksi-dompet-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function resolveRange(Request $request): string
    {
        return in_array($request->range, ['today', 'week', 'month']) ? $request->range : 'today';
    }

    private function applyDateFilter(Builder $query, Request $request, string $range): void
    {
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('transacted_at', [$request->start_date, $request->end_date]);
        } elseif ($request->period) {
            $query->forPeriod($request->period);
        } else {
            match ($range) {
                'week' => $query->whereBetween('transacted_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->forPeriod(now()->format('Y-m')),
                default => $query->whereDate('transacted_at', now()->toDateString()),
            };
        }
    }

    private function resolveRangeLabel(Request $request, string $range): string
    {
        if ($request->start_date && $request->end_date) {
            $start = Carbon::parse($request->start_date)->translatedFormat('d M');
            $end = Carbon::parse($request->end_date)->translatedFormat('d M Y');

            return "{$start} - {$end}";
        }

        return match ($range) {
            'week' => 'Minggu Ini',
            'month' => 'Bulan Ini',
            default => 'Hari Ini',
        };
    }

    private function buildFilteredQuery(Request $request, User $user, string $range): Builder
    {
        $query = $user->transactions()
            ->with(['wallet:id,display_name', 'category:id,name,emoji,type,icon_path'])
            ->orderByDesc('transacted_at')
            ->orderByDesc('created_at');

        $this->applyDateFilter($query, $request, $range);

        if ($request->wallet_id) {
            $query->where('wallet_id', $request->wallet_id);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->search) {
            $query->where('note', 'like', '%'.$request->search.'%');
        }
        if ($request->min_amount) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->max_amount) {
            $query->where('amount', '<=', $request->max_amount);
        }

        return $query;
    }

    // ─────────────────────────────────────────────
    // Gabungkan transactions + wallet_transfers jadi satu riwayat.
    // Sumbernya 2 tabel berbeda, jadi tidak bisa pakai paginate() bawaan
    // Eloquent di union query — diambil semua baris yang cocok filter,
    // digabung, diurutkan, baru di-slice manual per halaman.
    // ─────────────────────────────────────────────
    private function buildTransactionsPage(Request $request, User $user, string $range): LengthAwarePaginator
    {
        $transactionRows = $this->buildFilteredQuery($request, $user, $range)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'note' => $t->note,
                'category' => $t->category?->name,
                'category_emoji' => $t->category?->emoji,
                'category_icon_url' => $t->category?->icon_path ? Storage::url($t->category->icon_path) : null,
                'wallet' => $t->wallet?->display_name,
                'wallet_id' => $t->wallet_id,
                'category_id' => $t->category_id,
                'transacted_at' => $t->transacted_at->format('Y-m-d'),
                'transacted_at_label' => $t->transacted_at->translatedFormat('d M Y'),
                'transacted_at_time' => $t->created_at?->format('H:i'),
                'source' => $t->source,
                'transfer_id' => null,
                'counterparty_wallet' => null,
                '_sort_date' => $t->transacted_at->format('Y-m-d'),
                '_sort_time' => $t->created_at?->timestamp ?? $t->transacted_at->timestamp,
            ]);

        $merged = $transactionRows->concat($this->buildTransferRows($request, $user, $range))
            ->sort(fn ($a, $b) => $b['_sort_date'] <=> $a['_sort_date'] ?: $b['_sort_time'] <=> $a['_sort_time'])
            ->values();

        $perPage = 30;
        $page = max((int) $request->input('page', 1), 1);

        $items = $merged->slice(($page - 1) * $perPage, $perPage)
            ->map(fn ($row) => Arr::except($row, ['_sort_date', '_sort_time']))
            ->values();

        return new LengthAwarePaginator(
            $items,
            $merged->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    // ─────────────────────────────────────────────
    // Baris virtual dari wallet_transfers — satu transfer jadi 2 baris
    // (transfer_out di sisi from_wallet, transfer_in di sisi to_wallet)
    // supaya muncul di riwayat kedua dompet dengan penanda arah.
    // ─────────────────────────────────────────────
    private function buildTransferRows(Request $request, User $user, string $range): Collection
    {
        // Transfer tidak punya kategori — kalau filter category_id aktif, tidak relevan
        if ($request->category_id) {
            return collect();
        }
        if ($request->type && ! in_array($request->type, ['transfer_in', 'transfer_out'])) {
            return collect();
        }

        $query = WalletTransfer::where('user_id', $user->id)
            ->with(['fromWallet:id,display_name', 'toWallet:id,display_name']);

        $this->applyTransferDateFilter($query, $request, $range);

        if ($request->wallet_id) {
            $query->where(function ($q) use ($request) {
                $q->where('from_wallet_id', $request->wallet_id)
                    ->orWhere('to_wallet_id', $request->wallet_id);
            });
        }
        if ($request->min_amount) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->max_amount) {
            $query->where('amount', '<=', $request->max_amount);
        }
        if ($request->search) {
            $query->where('note', 'like', '%'.$request->search.'%');
        }

        $rows = collect();

        foreach ($query->get() as $transfer) {
            $showOut = ! $request->wallet_id || $request->wallet_id === $transfer->from_wallet_id;
            $showIn = ! $request->wallet_id || $request->wallet_id === $transfer->to_wallet_id;

            if ($showOut && (! $request->type || $request->type === 'transfer_out')) {
                $rows->push($this->mapTransferRow($transfer, 'transfer_out'));
            }
            if ($showIn && (! $request->type || $request->type === 'transfer_in')) {
                $rows->push($this->mapTransferRow($transfer, 'transfer_in'));
            }
        }

        return $rows;
    }

    private function mapTransferRow(WalletTransfer $transfer, string $type): array
    {
        $isOut = $type === 'transfer_out';
        $wallet = $isOut ? $transfer->fromWallet : $transfer->toWallet;
        $counterparty = $isOut ? $transfer->toWallet : $transfer->fromWallet;

        return [
            'id' => 'transfer-'.$transfer->id.'-'.($isOut ? 'out' : 'in'),
            'type' => $type,
            'amount' => (float) $transfer->amount,
            'note' => $transfer->note,
            'category' => null,
            'category_emoji' => null,
            'category_icon_url' => null,
            'wallet' => $wallet?->display_name,
            'wallet_id' => $isOut ? $transfer->from_wallet_id : $transfer->to_wallet_id,
            'category_id' => null,
            'transacted_at' => $transfer->transferred_at->format('Y-m-d'),
            'transacted_at_label' => $transfer->transferred_at->translatedFormat('d M Y'),
            'transacted_at_time' => $transfer->created_at?->format('H:i'),
            'source' => 'wallet_transfer',
            'transfer_id' => $transfer->id,
            'counterparty_wallet' => $counterparty?->display_name,
            '_sort_date' => $transfer->transferred_at->format('Y-m-d'),
            '_sort_time' => $transfer->created_at?->timestamp ?? $transfer->transferred_at->timestamp,
        ];
    }

    private function applyTransferDateFilter(Builder $query, Request $request, string $range): void
    {
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('transferred_at', [$request->start_date, $request->end_date]);
        } elseif ($request->period) {
            [$year, $month] = explode('-', $request->period);
            $query->whereYear('transferred_at', $year)->whereMonth('transferred_at', $month);
        } else {
            match ($range) {
                'week' => $query->whereBetween('transferred_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->whereYear('transferred_at', now()->year)->whereMonth('transferred_at', now()->month),
                default => $query->whereDate('transferred_at', now()->toDateString()),
            };
        }
    }
}
