<?php

namespace App\Http\Controllers\App;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\DompetFilterRequest;
use App\Models\Bank;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionEditLog;
use App\Services\TransactionFeedService;
use App\Services\WalletService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private TransactionFeedService $transactionFeed,
    ) {}

    public function index(DompetFilterRequest $request): Response
    {
        $user = $request->user();
        $range = $this->resolveRange($request);

        $transactions = $this->transactionFeed->buildQuery($user, $request, $range)
            ->paginate(30)
            ->through(fn ($row) => $this->transactionFeed->mapRow($row));

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

        $transactions = $this->transactionFeed->buildQuery($user, $request, $range)
            ->limit(5000)
            ->get()
            ->map(fn ($row) => $this->transactionFeed->mapRow($row));

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Tanggal', 'Tipe', 'Kategori', 'Dompet', 'Catatan', 'Jumlah']);

        foreach ($transactions as $t) {
            fputcsv($handle, [
                Carbon::parse($t['transacted_at'])->format('d/m/Y'),
                match ($t['type']) {
                    'income' => 'Pemasukan',
                    'expense' => 'Pengeluaran',
                    'transfer' => 'Transfer',
                    default => throw new \RuntimeException("Unexpected transaction type: {$t['type']}"),
                },
                $t['category'] ?? '-',
                $t['wallet'] ?? '-',
                $t['note'] ?? '-',
                $t['amount'],
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
}
